<?php

namespace App\DTO\Execution;

use App\Enums\TradeModeEnum;
use DateTimeImmutable;

final class ExecutionResult
{
    public function __construct(
        public TradeModeEnum      $mode,       // REAL/DRY
        public bool               $executed,            // 체결 성공 여부 (DRY면 true로 간주)
        public string             $side,              // 'buy' | 'sell'
        public string             $symbol,            // 입력 심볼(정규화 전 or 후 표기 정책 합의)
        public ?float             $avgPrice = null,
        public ?float             $filledQty = null,
        public ?float             $filledQuote = null, // 금액(USDT/KRW)
        public ?float             $fee = null,
        public ?string            $orderId = null,
        public ?DateTimeImmutable $executedAt = null,
        public mixed              $raw = null           // 거래소 원시 응답(로깅/디버깅)
    )
    {
    }
}
