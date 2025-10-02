<?php

namespace App\Services\Portfolio;

interface PortfolioServiceInterface
{
    /**
     * 거래 가능 잔액(USDT, free) — 거래소 실시간 기준, 짧게 캐시 가능.
     */
    public function freeUsdt(): float;

    /**
     * 오늘 남은 일일 예산(USDT) — 정책은 RiskManager가 결정하므로 여기선 위임.
     */
    public function remainingDailyBudgetUsdt(): float;

    /**
     * 해당 금액(usdt)으로 신규 진입이 가능한지 판단.
     * - 실보유잔고와 일일예산 잔여액 둘 다 충족해야 true
     */
    public function canAfford(float $usdt): bool;
}
