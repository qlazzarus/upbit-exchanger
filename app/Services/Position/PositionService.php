<?php

namespace App\Services\Position;

use App\Enums\PositionStatusEnum;
use App\Enums\TradeModeEnum;
use App\Enums\TradeSideEnum;
use App\Models\Position;
use App\Models\Trade;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class PositionService implements PositionServiceInterface
{
    /**
     * 포지션 생성 + 매수 체결 로그 기록
     * @throws Throwable
     */
    public function open(
        string        $symbol,
        float         $qty,
        float         $entryPrice,
        TradeModeEnum $mode,
        ?float        $tp = null,
        ?float        $sl = null,
        array         $meta = []
    ): Position
    {
        return DB::transaction(function () use ($symbol, $qty, $entryPrice, $mode, $tp, $sl, $meta) {
            $pos = Position::create([
                'symbol' => $symbol,
                'mode' => $mode,
                'qty' => $qty,
                'entry_price' => $entryPrice,
                'tp_price' => $tp,
                'sl_price' => $sl,
                'status' => PositionStatusEnum::OPEN,
                'opened_at' => now(),
                'notes' => $meta['notes'] ?? null,
            ]);

            Trade::create([
                'position_id' => $pos->id,
                'symbol' => $symbol,
                'mode' => $mode,
                'side' => TradeSideEnum::BUY,
                'price' => $entryPrice,
                'qty' => $qty,
                'fee' => (float)($meta['buy_fee'] ?? 0),
                'executed_at' => $meta['buy_executed_at'] ?? now(),
                'provider' => $meta['provider'] ?? 'bot',
            ]);

            return $pos->refresh();
        });
    }

    /**
     * 포지션 청산(시장가 매도 완료 후 호출) + 매도 체결 로그 기록
     * @throws Throwable
     */
    public function close(Position $position, float $exitPrice, array $meta = []): Position
    {
        return DB::transaction(function () use ($position, $exitPrice, $meta) {
            Trade::create([
                'position_id' => $position->id,
                'symbol' => $position->symbol,
                'mode' => $position->mode,
                'side' => TradeSideEnum::SELL,
                'price' => $exitPrice,
                'qty' => $position->qty,
                'fee' => (float)($meta['sell_fee'] ?? 0),
                'executed_at' => $meta['sell_executed_at'] ?? now(),
                'provider' => $meta['provider'] ?? 'bot',
            ]);

            $position->update([
                'status' => PositionStatusEnum::CLOSED,
                'closed_at' => now(),
            ]);

            return $position->refresh();
        });
    }

    /** 스탑/목표가 갱신 */
    public function updateStops(Position $position, ?float $tp = null, ?float $sl = null): Position
    {
        $position->update([
            'tp_price' => $tp,
            'sl_price' => $sl,
        ]);
        return $position->refresh();
    }

    /** 현재 오픈 포지션 반환 */
    public function getOpenPositions(): iterable
    {
        return Position::query()
            ->where('status', PositionStatusEnum::OPEN)
            ->orderBy('opened_at')
            ->get();
    }

    /** 체결 단건 기록(세부 제어 필요 시) */
    public function recordTrade(
        Position           $position,
        string             $side,
        float              $price,
        float              $qty,
        float              $fee = 0,
        string             $provider = 'bot',
        ?DateTimeInterface $executedAt = null
    ): Trade
    {
        // side 정규화 ('buy'|'sell')
        $sideEnum = match (strtolower($side)) {
            'buy' => TradeSideEnum::BUY,
            'sell' => TradeSideEnum::SELL,
            default => throw new InvalidArgumentException('side must be buy|sell'),
        };

        return Trade::create([
            'position_id' => $position->id,
            'symbol' => $position->symbol,
            'mode' => $position->mode,
            'side' => $sideEnum,
            'price' => $price,
            'qty' => $qty,
            'fee' => $fee,
            'executed_at' => $executedAt ?? now(),
            'provider' => $provider,
        ]);
    }

    /** 단일 포지션 PnL 계산(수수료 포함) */
    public function computePnl(Position $position): float
    {
        $buys = $position->trades()->where('side', TradeSideEnum::BUY)->get();
        $sells = $position->trades()->where('side', TradeSideEnum::SELL)->get();

        $buyCost = $buys->sum(fn($t) => (float)$t->price * (float)$t->qty + (float)$t->fee);
        $sellRev = $sells->sum(fn($t) => (float)$t->price * (float)$t->qty - (float)$t->fee);

        return (float)number_format($sellRev - $buyCost, 8, '.', '');
    }
}
