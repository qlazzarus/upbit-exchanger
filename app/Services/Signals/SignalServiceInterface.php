<?php

namespace App\Services\Signals;

use App\Enums\SignalConsumeReasonEnum;
use App\Enums\SignalSkipReasonEnum;
use App\Models\Signal;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

interface SignalServiceInterface
{
    /**
     * 시그널 생성 or 갱신 평가 후, 최근 대기 시그널 컬렉션 반환.
     * @return Collection<int, Signal>
     */
    public function generateOrFetch(): Collection;

    /**
     * 단일 심볼 규칙 평가 및 필요 시 시그널 생성.
     */
    public function evaluateAndMaybeCreate(string $symbol, CarbonInterface $now): void;

    /**
     * 시그널 생성(트랜잭션 보장).
     */
    public function createSignal(
        string          $symbol,
        CarbonInterface $triggeredAt,
        string          $ruleKey,
        float           $confidence,
        float           $refPrice
    ): Signal;

    /**
     * 최근 N분 내 대기 시그널.
     * @return Collection<int, Signal>
     */
    public function recentWaiting(int $minutes): Collection;

    /**
     * 시그널 소진(consumed) 처리.
     */
    public function markConsumed(Signal $signal, SignalConsumeReasonEnum|string|null $reason = null): Signal;

    /**
     * 시그널 스킵(skipped) 처리.
     */
    public function markSkipped(Signal $signal, SignalSkipReasonEnum|string|null $reason = null): Signal;

}
