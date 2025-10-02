<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Portfolio\PortfolioServiceInterface;
use App\Services\Risk\RiskManagerInterface;
use App\Services\Position\PositionServiceInterface;

class ReportHeartBeatCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'report:heartbeat';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Hourly heartbeat: balances, budget, and open positions';

    /**
     * Execute the console command.
     */
    public function handle(
        PortfolioServiceInterface $portfolio,
        RiskManagerInterface      $risk,
        PositionServiceInterface  $positions,
    ): int
    {
        $tz = config('reporting.timezone', 'Asia/Seoul');
        $now = now($tz);

        $free = $portfolio->freeUsdt();
        $remain = $portfolio->remainingDailyBudgetUsdt();
        $halt = $risk->shouldHaltTrading();

        $open = collect($positions->getOpenPositions());
        $openCount = $open->count();
        $symbols = $openCount > 0 ? $open->pluck('symbol')->unique()->values()->all() : [];
        $symStr = empty($symbols) ? '' : (' [' . implode(', ', $symbols) . ']');

        $line = sprintf('[HB %s] free=%.4f USDT remain=%.4f open=%d%s %s',
            $now->toDateTimeString(),
            $free,
            $remain,
            $openCount,
            $symStr,
            $halt ? 'HALT:on' : 'HALT:off'
        );

        $this->info($line);
        return self::SUCCESS;
    }
}
