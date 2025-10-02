<?php

namespace App\Enums;

enum SignalConsumeReasonEnum: string
{
    case ENTERED = 'ENTERED';          // 진입(체결)함
    case MANUAL_CONSUME = 'MANUAL_CONSUME';   // 운영자가 수동 소진 처리
    case BACKTEST_CONSUME = 'BACKTEST_CONSUME'; // 백테스트/리플레이 소진
}
