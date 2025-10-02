<?php

namespace App\Services\Reporting;

use App\Models\DailyLedger;
use Carbon\CarbonInterface;

interface DailyReportServiceInterface
{
    /** 당일(또는 지정일)의 모든 거래/포지션을 집계하여 Ledger upsert */
    public function summarizeForDate(CarbonInterface $date): DailyLedger;

    /** 요약을 외부 목적지(시트/이메일 등)로 전송 */
    public function dispatch(DailyLedger $ledger): void;
}
