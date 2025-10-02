<?php

namespace App\DTO\Risk;

final class RiskDecision
{
    public function __construct(
        public bool    $allowed,
        public ?string $reasonCode = null,      // e.g. 'BUDGET_EXCEEDED','DAILY_DD_HIT','COOLDOWN','NIGHT_MODE'
        public ?int    $cooldownSec = null,        // 남은 쿨다운(초)
        public ?float  $remainingBudgetUsdt = null,
        public bool    $tradingHalt = false
    )
    {
    }
}
