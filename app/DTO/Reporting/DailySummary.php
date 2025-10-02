<?php

namespace App\DTO\Reporting;

final class DailySummary
{
    public function __construct(
        public string $date,                 // 'YYYY-MM-DD'
        public float  $equityStart,
        public float  $equityEnd,
        public float  $pnlUsdt,
        public float  $pnlPct,
        public int    $wins,
        public int    $losses,
        public int    $tradesCount
    )
    {
    }
}
