<?php

namespace App\Console\Commands;

use App\Services\Reporting\DailyReportServiceInterface;
use App\Services\Portfolio\PortfolioServiceInterface;
use Illuminate\Console\Command;

class ReportDailyCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'report:daily {--date=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Aggregate trades/positions and dispatch daily report';

    /**
     * Execute the console command.
     */
    public function handle(DailyReportServiceInterface $svc, PortfolioServiceInterface $portfolio): int
    {
        $tz = config('reporting.timezone', 'Asia/Seoul');
        $dateArg = $this->option('date');
        $date = $dateArg ? now($tz)->parse($dateArg) : now($tz);

        $ledger = $svc->summarizeForDate($date);
        $svc->dispatch($ledger);

        // Append portfolio snapshot to console output
        $free = $portfolio->freeUsdt();
        $remain = $portfolio->remainingDailyBudgetUsdt();
        $this->line(sprintf('Portfolio: free=%.4f USDT, remainDaily=%.4f USDT', $free, $remain));

        $this->info("Daily report dispatched for {$ledger->date}");
        return self::SUCCESS;
    }
}
