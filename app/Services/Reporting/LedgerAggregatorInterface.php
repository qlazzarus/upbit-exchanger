<?php

namespace App\Services\Reporting;

use App\Models\DailyLedger;
use Carbon\CarbonInterface;

interface LedgerAggregatorInterface
{
    public function aggregate(CarbonInterface $date): DailyLedger;
}
