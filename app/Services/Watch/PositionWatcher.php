<?php

namespace App\Services\Watch;

use App\DTO\Watch\TickReport;
use App\Services\Execution\OrderExecutorInterface;
use App\Services\Market\MarketDataServiceInterface;
use App\Services\Position\PositionServiceInterface;
use App\Services\Risk\RiskManagerInterface;
use Log;
use Throwable;

class PositionWatcher implements PositionWatcherInterface
{
    public function __construct(
        protected PositionServiceInterface   $positions,
        protected OrderExecutorInterface     $executor,
        protected MarketDataServiceInterface $md,
        protected RiskManagerInterface       $risk,
        protected int                        $timeoutMinutes = 90,
    )
    {
    }

    /** @inheritDoc */
    public function tick(): TickReport
    {
        $scanned = $tpClosed = $slClosed = $timeoutClosed = $errors = 0;

        $openPositions = $this->positions->getOpenPositions();

        foreach ($openPositions as $pos) {
            $scanned++;

            try {
                $last = $this->md->getLastPrice($pos->symbol);
                if (!$last) continue;

                $now = now();
                $closed = false;

                // Take Profit
                if ($pos->tp_price && $last >= $pos->tp_price) {
                    // Exit guard: ensure sell total meets exchange minimum
                    $decision = $this->risk->canExit($pos, $last);
                    if (!$decision->allowed) {
                        Log::info('[PositionWatcher] hold as dust (TP)', [
                            'pos_id' => $pos->id ?? null,
                            'symbol' => $pos->symbol,
                            'qty'    => $pos->qty,
                            'last'   => $last,
                            'reason' => $decision->reasonCode ?? 'under_min_sell',
                        ]);
                        continue;
                    }

                    $res = $this->executor->marketSell($pos->symbol, $pos->qty);
                    $this->positions->close($pos, $res->avgPrice ?? $last, ['reason' => 'TP']);
                    $tpClosed++;
                    $closed = true;
                }

                // Stop Loss
                if (!$closed && $pos->sl_price && $last <= $pos->sl_price) {
                    // Exit guard: ensure sell total meets exchange minimum
                    $decision = $this->risk->canExit($pos, $last);
                    if (!$decision->allowed) {
                        Log::info('[PositionWatcher] hold as dust (SL)', [
                            'pos_id' => $pos->id ?? null,
                            'symbol' => $pos->symbol,
                            'qty'    => $pos->qty,
                            'last'   => $last,
                            'reason' => $decision->reasonCode ?? 'under_min_sell',
                        ]);
                        continue;
                    }

                    $res = $this->executor->marketSell($pos->symbol, $pos->qty);
                    $this->positions->close($pos, $res->avgPrice ?? $last, ['reason' => 'SL']);
                    $slClosed++;
                    $closed = true;
                }

                // Timeout
                if (!$closed && $pos->opened_at->lt($now->copy()->subMinutes($this->timeoutMinutes))) {
                    // Exit guard: ensure sell total meets exchange minimum
                    $decision = $this->risk->canExit($pos, $last);
                    if (!$decision->allowed) {
                        Log::info('[PositionWatcher] hold as dust (TIMEOUT)', [
                            'pos_id' => $pos->id ?? null,
                            'symbol' => $pos->symbol,
                            'qty'    => $pos->qty,
                            'last'   => $last,
                            'reason' => $decision->reasonCode ?? 'under_min_sell',
                        ]);
                        continue;
                    }

                    $res = $this->executor->marketSell($pos->symbol, $pos->qty);
                    $this->positions->close($pos, $res->avgPrice ?? $last, ['reason' => 'TIMEOUT']);
                    $timeoutClosed++;
                    $closed = true;
                }

            } catch (Throwable $e) {
                $errors++;
                Log::error('[PositionWatcher] tick error', [
                    'pos_id' => $pos->id ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return new TickReport(
            positionsScanned: $scanned,
            closedByTp: $tpClosed,
            closedBySl: $slClosed,
            closedByTimeout: $timeoutClosed,
            errors: $errors,
        );
    }
}
