<?php

namespace App\Services\Watch;

use App\DTO\Watch\TickReport;

interface PositionWatcherInterface
{
    /**
     * 1틱 수행: 오픈 포지션들을 스캔하여 TP/SL/시간초과 충족 시 청산 트리거
     * @return TickReport 처리 통계/결과
     */
    public function tick(): TickReport;
}
