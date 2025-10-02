<?php

namespace App\Services\Signals;

use App\Enums\SignalConsumeReasonEnum;
use App\Enums\SignalSkipReasonEnum;
use App\Enums\SignalStatusEnum;
use App\Models\MarketSnapshot;
use App\Models\Signal;
use App\Models\WatchList;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SignalService implements SignalServiceInterface
{
    /**
     * 기본 규칙 키 (EMA20 상향돌파 EMA60 + 거래량 20SMA의 2배)
     */
    public const string DEFAULT_RULE_KEY = 'ema20_cross_ema60_vol2x';

    /**
     * 최근 시그널 쿨다운(분) — 동일 심볼 재발행 제한
     */
    protected int $cooldownMinutes;

    /**
     * 후보 시그널 조회 시간창(분)
     */
    protected int $candidateWindowMinutes;

    public function __construct()
    {
        $this->cooldownMinutes = (int)config('bot.signal_cooldown_minutes', 20);
        $this->candidateWindowMinutes = (int)config('bot.signal_candidate_window_minutes', 120);
    }

    /**
     * 시그널 생성 또는 갱신 평가 후, 최근 대기 시그널을 반환합니다.
     *
     * @return Collection
     */
    public function generateOrFetch(): Collection
    {
        $now = Carbon::now('Asia/Seoul');

        // 1) 활성 워치리스트 심볼들 조회
        $symbols = WatchList::query()
            ->where('enabled', true)
            ->orderBy('priority')
            ->pluck('symbol');

        // 2) 각 심볼에 대해 규칙 평가 → 필요 시 시그널 생성
        foreach ($symbols as $symbol) {
            try {
                $this->evaluateAndMaybeCreate($symbol, $now);
            } catch (Throwable $e) {
                Log::warning('[SignalService] evaluate failed', [
                    'symbol' => $symbol,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 3) 최근 후보(대기) 시그널 반환
        return $this->recentWaiting($this->candidateWindowMinutes);
    }

    /**
     * 특정 심볼에 대해 규칙을 평가하고, 조건 충족 & 쿨다운 통과 시 시그널을 생성합니다.
     * @throws Throwable
     */
    public function evaluateAndMaybeCreate(string $symbol, Carbon|CarbonInterface $now): void
    {
        // 최신 스냅샷 1건 (분봉 기준 캐시)
        $snap = MarketSnapshot::query()
            ->where('symbol', $symbol)
            ->orderByDesc('captured_at')
            ->first();

        if (!$snap) {
            return; // 데이터 없으면 스킵
        }

        // 규칙: EMA20 > EMA60 AND volume > vol_sma20 * 2
        $ema20 = (float)($snap->ema20 ?? 0);
        $ema60 = (float)($snap->ema60 ?? 0);
        $vol   = (float)($snap->volume ?? 0);
        $vol20 = (float)($snap->vol_sma20 ?? 0);

        $crossUp = $ema20 > $ema60;
        $volOk   = $vol20 > 0 && $vol >= $vol20 * 2;

        if (!($crossUp && $volOk)) {
            return; // 조건 미충족
        }

        // 쿨다운: 최근 cooldownMinutes 이내 시그널(대기/소진 무관) 존재하면 생략
        if ($this->isInCooldown($symbol, Carbon::instance($now))) {
            return;
        }

        // 시그널 생성
        $signal = $this->createSignal(
            symbol: $symbol,
            triggeredAt: $now,
            ruleKey: self::DEFAULT_RULE_KEY,
            confidence: $this->calcConfidence($snap),
            refPrice: (float)$snap->price_last,
        );
    }

    /**
     * 최근 쿨다운 창 내 시그널 존재 여부
     */
    protected function isInCooldown(string $symbol, Carbon $now): bool
    {
        $since = (clone $now)->subMinutes($this->cooldownMinutes);

        return Signal::query()
            ->where('symbol', $symbol)
            ->where('triggered_at', '>=', $since)
            ->exists();
    }

    /**
     * 시그널 생성
     * @throws Throwable
     */
    public function createSignal(string $symbol, Carbon|CarbonInterface $triggeredAt, string $ruleKey, float $confidence, float $refPrice): Signal
    {
        return DB::transaction(function () use ($symbol, $triggeredAt, $ruleKey, $confidence, $refPrice) {
            return Signal::create([
                'symbol' => $symbol,
                'triggered_at' => $triggeredAt,
                'rule_key' => $ruleKey,
                'confidence' => $confidence,
                'status' => SignalStatusEnum::WAITING,
                'ref_price' => $refPrice,
                'reason' => null,
            ]);
        });
    }

    /**
     * 최근 N분 내의 대기 시그널 반환
     *
     * @param int $minutes
     * @return Collection
     */
    public function recentWaiting(int $minutes): Collection
    {
        $since = Carbon::now('Asia/Seoul')->subMinutes($minutes);
        $q = Signal::query()
            ->where('status', SignalStatusEnum::WAITING)
            ->where('triggered_at', '>=', $since)
            ->orderByDesc('triggered_at');
        return $q->get();
    }

    public function markConsumed(Signal $signal, SignalConsumeReasonEnum|string|null $reason = null): Signal
    {
        $signal->status = SignalStatusEnum::CONSUMED;
        $signal->reason = $reason instanceof SignalConsumeReasonEnum ? $reason->value : ($reason ?? null);
        $signal->save();
        return $signal;
    }

    public function markSkipped(Signal $signal, SignalSkipReasonEnum|string|null $reason = null): Signal
    {
        $signal->status = SignalStatusEnum::SKIPPED;
        $signal->reason = $reason instanceof SignalSkipReasonEnum ? $reason->value : ($reason ?? null);
        $signal->save();
        return $signal;
    }

    /**
     * 신뢰도 산정(간단 버전):
     *  - EMA 괴리(ema20-ema60)가 클수록 ↑
     *  - 거래량 배수(volume / vol_sma20)가 클수록 ↑
     */
    protected function calcConfidence(MarketSnapshot $s): float
    {
        $emaGap = max(0.0, (float)$s->ema20 - (float)$s->ema60);
        $volBase = (float)($s->vol_sma20 ?? 0.0);
        $volMul = $volBase > 0 ? ((float)$s->volume / $volBase) : 0.0;

        // 간단 정규화: (emaGap 비중 0.4) + (volMul 비중 0.6), 0~1로 클램프
        $normGap = min(1.0, $emaGap > 0 ? min(1.0, $emaGap / max(1.0, (float)$s->price_last * 0.005)) : 0.0);
        $normVol = min(1.0, $volMul / 3.0); // 3배 이상이면 최고점수

        return round(max(0.0, min(1.0, 0.4 * $normGap + 0.6 * $normVol)), 4);
    }
}
