<?php

namespace App\DTO\Watch;

final class TickReport
{
    public function __construct(
        public int $positionsScanned,
        public int $closedByTp,
        public int $closedBySl,
        public int $closedByTimeout,
        public int $errors = 0
    )
    {
    }
}
