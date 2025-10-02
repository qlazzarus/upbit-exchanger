<?php

namespace App\Services\Execution;

use App\DTO\Execution\ExecutionOptions;
use App\DTO\Execution\ExecutionResult;

interface OrderExecutorInterface
{
    /** 현재가 조회(가능하면 거래소/캐시 우선) */
    public function getLastPrice(string $symbol): ?float;

    /**
     * 시장가 매수(견적통화 금액으로; Upbit: ord_type=price)
     * - $quoteAmount: USDT/KRW 금액
     */
    public function marketBuyByQuote(string $symbol, float $quoteAmount): ExecutionResult;

    /**
     * 시장가 매도(베이스 수량으로; Upbit: ord_type=market)
     * - $baseQty: 코인 수량
     */
    public function marketSell(string $symbol, float $baseQty): ExecutionResult;

    /** (옵션) 주문 취소 */
    public function cancel(string $orderId): bool;
}
