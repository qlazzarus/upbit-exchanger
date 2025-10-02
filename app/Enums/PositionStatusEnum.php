<?php

namespace App\Enums;

enum PositionStatusEnum: string
{
    case OPEN = 'OPEN';
    case CLOSED = 'CLOSED';
    case CANCELED = 'CANCELED';
}
