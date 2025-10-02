<?php

namespace App\Services\Reporting;

use App\Models\DailyLedger;
use Carbon\Carbon;

class ReportingService
{
    public function summarizeForDate(Carbon $date): DailyLedger
    {
        // trades/positions 조회해서 wins/losses, pnl 계산 → DailyLedger upsert
        // (여기서는 스켈레톤만)
        return DailyLedger::firstOrCreate(['date' => $date->toDateString()]);
    }

    public function pushToGoogleSheets(DailyLedger $ledger): void
    {
        // Sheets API append 호출
    }

    public function sendDailyMail(DailyLedger $ledger): void
    {
        // Mailable or simple Mail::to(...)->send(...)
    }
}
