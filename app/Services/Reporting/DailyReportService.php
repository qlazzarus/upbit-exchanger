<?php

namespace App\Services\Reporting;

use App\Models\DailyLedger;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

class DailyReportService implements DailyReportServiceInterface
{
    private LedgerAggregatorInterface $aggregator;
    private SheetAppenderInterface $sheets;
    private MailNotifierInterface $mailer;

    public function __construct(
        LedgerAggregatorInterface $aggregator,
        SheetAppenderInterface    $sheets,
        MailNotifierInterface     $mailer,
    )
    {
        $this->mailer = $mailer;
        $this->sheets = $sheets;
        $this->aggregator = $aggregator;
    }

    public function summarizeForDate(CarbonInterface $date): DailyLedger
    {
        return $this->aggregator->aggregate($date);
    }

    public function dispatch(DailyLedger $ledger): void
    {
        try {
            $this->sheets->appendDailySummary($ledger);
        } catch (Throwable $e) {
            Log::warning('[DailyReportService] appendDailySummary failed', ['err' => $e->getMessage()]);
        }

        try {
            // (선택) 거래 상세 로그도 시트에 적고 싶다면 주석 해제
            $this->sheets->appendTradeLog($ledger);
        } catch (Throwable $e) {
            Log::warning('[DailyReportService] appendTradeLog failed', ['err' => $e->getMessage()]);
        }

        try {
            $this->mailer->sendDailyMail($ledger);
        } catch (Throwable $e) {
            Log::warning('[DailyReportService] sendDailyMail failed', ['err' => $e->getMessage()]);
        }
    }
}
