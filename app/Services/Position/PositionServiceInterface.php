<?php

namespace App\Services\Position;

use App\Enums\TradeModeEnum;
use App\Models\Position;
use App\Models\Trade;

interface PositionServiceInterface
{
    /**
     * 포지션 생성 + 매수 체결 로그 기록
     * - 수수료/호가 반올림 등은 호출측 또는 내부 유틸에서 처리
     */
    public function open(
        string        $symbol,
        float         $qty,
        float         $entryPrice,
        TradeModeEnum $mode,
        ?float        $tp = null,
        ?float        $sl = null,
        array         $meta = []
    ): Position;

    /** 포지션 청산(시장가 매도 완료 후 호출) + 매도 체결 로그 기록 */
    public function close(Position $position, float $exitPrice, array $meta = []): Position;

    /**
    * 더스트 포지션 표시(최소 청산금액 미만 등으로 보류 상태로 전환)
    * - status 컬럼이 있다면 'dust' 등으로 변경하는 대신, 스키마 의존성을 줄이기 위해 메타에 플래그만 기록
    * - 이후 재진입 누적으로 청산 가능해지면 일반 close() 경로로 처리
    */
    public function markDust(Position $position, array $meta = []): Position;

    /** 스탑/목표가 갱신 */
    public function updateStops(Position $position, ?float $tp = null, ?float $sl = null): Position;

    /** 현재 오픈 포지션 반환 */
    public function getOpenPositions(): iterable;

    /** 체결 단건 기록(세부 제어 필요 시) */
    public function recordTrade(
        Position            $position,
        string              $side,               // 'buy' | 'sell'
        float               $price,
        float               $qty,
        float               $fee = 0,
        string              $provider = 'bot',
        ?\DateTimeInterface $executedAt = null
    ): Trade;

    /** 단일 포지션 PnL 계산(수수료 포함/미포함 옵션은 meta로) */
    public function computePnl(Position $position): float;
}
