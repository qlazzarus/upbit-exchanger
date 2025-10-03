<?php

namespace App\Services\Risk;

use App\Models\DailyLedger;
use App\Models\Position;
use App\Services\Watch\WatchListRepository;
use App\Services\Watch\WatchListRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use App\DTO\Risk\RiskDecision;

class RiskManager implements RiskManagerInterface
{
    private WatchListRepositoryInterface $watchListRepo;
    private string $tz;

    public function __construct(
        WatchListRepositoryInterface $watchListRepo,
        string $tz = 'Asia/Seoul',
    )
    {
        $this->watchListRepo = $watchListRepo;
        $this->tz = $tz;

    }

    /** 일일 예산 확인 / DD / 쿨다운 / 야간 금지 등을 종합 판단 (간단 버전) */
    public function canEnter(string $symbol, float $orderUsdt): RiskDecision
    {
        if ($this->shouldHaltTrading()) {
            return new RiskDecision(
                allowed: false,
                reasonCode: 'halt',
                cooldownSec: null,
                remainingBudgetUsdt: $this->remainingDailyBudgetUsdt(),
            );
        }

        // 남은 일일 예산
        $remain = $this->remainingDailyBudgetUsdt();
        if ($orderUsdt > $remain + 1e-9) {
            return new RiskDecision(
                allowed: false,
                reasonCode: 'budget',
                cooldownSec: null,
                remainingBudgetUsdt: $remain,
            );
        }

        // 심볼 재진입 쿨다운(예: 최근 20분 내 체결 시 거부)
        $cooldown = $this->cooldownRemaining($symbol);
        if ($cooldown !== null) {
            return new RiskDecision(
                allowed: false,
                reasonCode: 'cooldown',
                cooldownSec: $cooldown,
                remainingBudgetUsdt: $remain,
            );
        }


        $ccy = str_starts_with($symbol, 'KRW-') ? 'KRW' : 'USDT';
        $minQuote = config("exchange.upbit.min_quote_default.$ccy", 0);

        // WatchList 메타의 심볼별 min_total이 있으면 우선 사용
        $metaMin = $this->watchListRepo->getMetaMinQuote($symbol); // 없으면 null
        if ($metaMin && $metaMin > 0) {
            $minQuote = max($minQuote, $metaMin);
        }

        if ($orderUsdt < $minQuote) {
            return new RiskDecision(
                allowed: false,
                reasonCode: 'under_min',
                cooldownSec: null,
                remainingBudgetUsdt: $remain,
            );
        }

        // 야간 여부는 여기서 차단하지 않음.
        // DRY/REAL 분기는 OrderExecutor(DryFireGuard)에서 자동 처리.

        return new RiskDecision(
            allowed: true,
            reasonCode: null,
            cooldownSec: null,
            remainingBudgetUsdt: $remain,
        );
    }

    /**
     * 포지션 청산 가능한지 확인
     */
    public function canExit(Position $position, float $currentPrice): RiskDecision
    {
        $symbol = $position->symbol;

        // 심볼별 최소 주문금액 가져오기 (없으면 config 기본값 사용)
        $minQuote = $this->watchListRepo->getMetaMinQuote($symbol)
            ?? config('exchange.upbit.min_quote_default.KRW', 5000);

        $totalValue = $position->qty * $currentPrice;

        if ($totalValue < $minQuote) {
            return new RiskDecision(
                false,
                'UNDER_MIN_SELL',
                null,
                null
            );
        }

        return new RiskDecision(
            true,
            null,
            null,
            null
        );
    }

    /** 체결 후 사용액/손익 집계 업데이트 (간단 버전 예시) */
    public function registerFill(string $symbol, float $filledUsdt, float $pnlUsdt): void
    {
        $date = now($this->tz)->toDateString();
        // settings/daily usage를 settings 테이블로 관리하거나 Cache로 임시 관리
        $keyUsed = "risk:used:{$date}";
        $keyPnl = "risk:pnl:{$date}";
        Cache::increment($keyUsed, (int)round($filledUsdt * 1e6));
        Cache::increment($keyPnl, (int)round($pnlUsdt * 1e6));

        // 심볼 쿨다운 기록: 만료 시각(timestamp) 자체를 값으로 저장해 두고, 캐시 만료도 동일 시각으로 설정
        $coolMin = (int) config('bot.signal_cooldown_minutes', 20);
        $expireAt = now($this->tz)->addMinutes($coolMin);
        Cache::put("risk:cooldown:{$symbol}", $expireAt->timestamp, $expireAt);
    }

    /** 오늘 남은 일일 예산(USDT) */
    public function remainingDailyBudgetUsdt(): float
    {
        $budget = (float)config('bot.daily_budget_usdt', 10.0);
        $date = now($this->tz)->toDateString();
        $used = (int)Cache::get("risk:used:{$date}", 0) / 1e6;
        return max(0.0, $budget - $used);
    }

    /** 거래 중단 상태인지(일일 손실 한도 히트 등) */
    public function shouldHaltTrading(): bool
    {
        $ddStop = (float)config('bot.daily_drawdown_stop_pct', 2.0);
        if ($ddStop <= 0) return false;

        $date = now($this->tz)->toDateString();
        $pnl = (int)Cache::get("risk:pnl:{$date}", 0) / 1e6; // 손익 누적
        // 손익이 음수이고, 시작자본 대비 -dd% 이하일 때 중단 — 시작자본은 settings / ledger에서 로드
        $equityStart = (float)(DailyLedger::where('date', $date)->first()->equity_start_usdt ?? 0);
        if ($equityStart > 0) {
            $ddPct = ($pnl / $equityStart) * 100.0;
            if ($ddPct <= -$ddStop) return true;
        }
        return false;
    }

    /** 심볼 재진입 쿨다운(초) 남은 시간 */
    public function cooldownRemaining(string $symbol): ?int
    {
        $expireTs = Cache::get("risk:cooldown:{$symbol}");
        if (!$expireTs) {
            return null; // 쿨다운 없음
        }

        $nowTs = now($this->tz)->timestamp;
        $remain = (int) ($expireTs - $nowTs);
        return $remain > 0 ? $remain : null;
    }
}
