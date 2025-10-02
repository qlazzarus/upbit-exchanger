<?php

namespace App\Services\Risk;

use App\DTO\Risk\RiskDecision;

interface RiskManagerInterface
{
    /**
     * 지금 이 주문을 승인해도 되는지 판단.
     * - 일일 예산/손실 한도/쿨다운/야간 금지(DRY) 등 종합 판단
     */
    public function canEnter(string $symbol, float $orderUsdt): RiskDecision;

    /** 일일 손실/이익/사용액 업데이트 (체결 후 호출) */
    public function registerFill(string $symbol, float $filledUsdt, float $pnlUsdt): void;

    /** 오늘 남은 일일 예산(USDT) */
    public function remainingDailyBudgetUsdt(): float;

    /** 거래 중단 상태인지(일일 손실 한도 히트 등) */
    public function shouldHaltTrading(): bool;

    /** 해당 심볼의 재진입 쿨다운(초) 남은 시간. 없으면 null */
    public function cooldownRemaining(string $symbol): ?int;
}
