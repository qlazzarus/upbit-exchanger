<?php

namespace App\Services\Reporting;

use App\Models\DailyLedger;

interface SheetAppenderInterface
{
    public function appendDailySummary(DailyLedger $ledger): void;

    public function appendTradeLog(DailyLedger $ledger): void; // 필요 시
}
