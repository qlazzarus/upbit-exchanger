<?php

namespace App\Enums;

enum SignalStatusEnum: string
{
    case WAITING = 'WAITING';
    case CONSUMED = 'CONSUMED';
    case SKIPPED = 'SKIPPED';
}
