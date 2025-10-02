<?php

namespace App\Services\Reporting;

use App\Models\DailyLedger;
use App\Models\Position;
use App\Models\Trade;
use Carbon\CarbonInterface;

class LedgerAggregator implements LedgerAggregatorInterface
{
    private string $tz;

    public function __construct(
        string $tz = 'Asia/Seoul',
    )
    {
        $this->tz = $tz;
    }

    public function aggregate(CarbonInterface $date): DailyLedger
    {
        $start = $date->copy()->setTimezone($this->tz)->startOfDay();
        $end = $start->copy()->addDay();

        // Trades 집계 (당일 체결)
        $trades = Trade::query()
            ->whereBetween('executed_at', [$start, $end])
            ->get(['side', 'price', 'qty', 'fee']);

        $buyCost = 0.0;   // 매수 원가(수수료 포함)
        $sellRev = 0.0;   // 매도 수익(수수료 차감)
        foreach ($trades as $t) {
            $notional = (float)$t->price * (float)$t->qty;
            $fee = (float)$t->fee;
            if ($t->side->value === 'buy') {
                $buyCost += $notional + $fee;
            } else {
                $sellRev += $notional - $fee;
            }
        }
        $pnlUsdt = $sellRev - $buyCost;

        // Positions 집계 (당일 마감된 포지션 승/패)
        $closed = Position::query()
            ->whereBetween('closed_at', [$start, $end])
            ->with(['trades' => function ($q) {
                $q->select(['position_id', 'side', 'price', 'qty', 'fee']);
            }])
            ->get();

        $wins = 0;
        $losses = 0;
        foreach ($closed as $pos) {
            $b = 0.0;
            $s = 0.0;
            foreach ($pos->trades as $tr) {
                $amt = (float)$tr->price * (float)$tr->qty;
                if ($tr->side->value === 'buy') {
                    $b += $amt + (float)$tr->fee;
                } else {
                    $s += $amt - (float)$tr->fee;
                }
            }
            $p = $s - $b;
            if ($p > 0) $wins++; elseif ($p < 0) $losses++;
        }

        $tradesCount = $trades->count();

        // DailyLedger upsert (date 키를 자정으로 정규화)
        $keyDate = $start->copy()->startOfDay();

        // 기존 레코드(있으면) 우선 조회 → equity_start_usdt 보존
        $existing = DailyLedger::query()
            ->where('date', $keyDate)
            ->first();

        $equityStart = (float)($existing->equity_start_usdt ?? 0.0);
        $equityEnd   = $equityStart > 0 ? $equityStart + $pnlUsdt : ($existing->equity_end_usdt ?? null);

        DailyLedger::query()->updateOrCreate(
            ['date' => $keyDate],
            [
                'trades_count'       => $tradesCount,
                'wins'               => $wins,
                'losses'             => $losses,
                'pnl_usdt'           => round($pnlUsdt, 8),
                'pnl_pct'            => $equityStart > 0 ? round(($pnlUsdt / $equityStart) * 100, 4) : null,
                // reset-day에서 기록된 값 유지
                'equity_start_usdt'  => $existing->equity_start_usdt ?? null,
                'equity_end_usdt'    => $equityEnd,
            ]
        );

        // 최신 상태 반환
        return DailyLedger::query()->where('date', $keyDate)->firstOrFail();
    }
}
