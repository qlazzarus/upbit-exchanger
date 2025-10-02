<?php

namespace App\DTO\Execution;

use App\Enums\TradeModeEnum;

final class ExecutionOptions
{
    public function __construct(
        public ?TradeModeEnum $modeOverride = null, // DRY 강제 등 (null이면 자동판정)
        public ?float         $maxSlippagePct = null,       // 체결 가격 허용 슬리피지 (%)
        public ?string        $providerTag = 'bot'         // 로깅/추적용 태그
    )
    {
    }
}
