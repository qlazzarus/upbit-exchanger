<?php

namespace App\Services\Market;

use App\Models\MarketSnapshot;
use App\Services\Exchange\UpbitClient;
use App\Services\Watch\WatchListServiceInterface;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

class MarketDataService implements MarketDataServiceInterface
{
    private UpbitClient $upbit;
    private WatchListServiceInterface $watch;
    private string $tz;

    public function __construct(
        UpbitClient $upbit,
        WatchListServiceInterface $watch,
        string      $tz = 'Asia/Seoul',

    )
    {
        $this->upbit = $upbit;
        $this->watch = $watch;
        $this->tz = $tz;
    }

    /** 심플 스냅샷: 활성 워치리스트 전수 현재가 수집
     */
    public function snapshot(?array $symbols = null, ?CarbonInterface $at = null): int
    {
        $when = $at ? $at->copy()->setTimezone($this->tz) : now($this->tz);
        $list = $symbols ?: $this->watch->enabledSymbols();
        $count = 0;

        foreach ($list as $symbol) {
            try {
                // 분봉 캔들: 1분봉 최근 60개 (필요 시 config로 변경 가능)
                $candles = $this->upbit->fetchMinuteCandles($symbol, unit: 1, count: 60);
                if (empty($candles)) {
                    continue;
                }

                $rows = [];
                foreach ($candles as $c) {
                    // Upbit response uses `candle_date_time_kst` for local time
                    $ts = Carbon::parse($c['candle_date_time_kst'], $this->tz)->second(0);
                    $rows[] = [
                        'symbol' => $symbol,
                        'captured_at' => $ts,
                        'price_last' => (float)($c['trade_price'] ?? $c['close'] ?? 0),
                        'volume' => (float)($c['candle_acc_trade_volume'] ?? $c['volume'] ?? 0),
                        'updated_at' => now(),
                    ];
                }

                if (!empty($rows)) {
                    DB::table('market_snapshots')->upsert(
                        $rows,
                        ['symbol', 'captured_at'],
                        ['price_last', 'volume', 'updated_at']
                    );
                    $count += count($rows);
                }
                // After saving snapshots, compute indicators for this symbol
                $this->computeIndicators($symbol);
            } catch (Throwable $e) {
                Log::warning('[MarketDataService] snapshot candles failed', [
                    'symbol' => $symbol,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        return $count;
    }

    /** 마지막 체결가(현재가)를 반환; 없으면 null
     */
    public function getLastPrice(string $symbol): ?float
    {
        // 1) 방금 찍은 스냅샷 우선
        $snap = MarketSnapshot::query()
            ->where('symbol', $symbol)
            ->orderByDesc('captured_at')
            ->first();

        if ($snap && $snap->captured_at >= now($this->tz)->subMinutes(2)) {
            return (float)$snap->price_last;
        }

        // 2) 거래소 직접 조회 (초단위 캐시 + 실패 시 그레이스)
        $cacheKey = 'ticker:' . strtoupper($symbol);
        $price = Cache::remember($cacheKey, 2, function () use ($symbol) {
            try {
                return $this->upbit->fetchLastPrice($symbol);
            } catch (Throwable $e) {
                Log::notice('[MarketDataService] fetchLastPrice failed', [
                    'symbol' => $symbol,
                    'error' => $e->getMessage(),
                ]);
                return null;
            }
        });

        if ($price !== null) {
            return (float)$price;
        }

        // 거래소가 실패해도 최근 스냅샷이 5분 내면 그 값을 사용
        if ($snap && $snap->captured_at >= now($this->tz)->subMinutes(5)) {
            return (float)$snap->price_last;
        }

        return null;
    }


    public function getRecentSnapshots(string $symbol, int $limit = 60): Collection
    {
        return MarketSnapshot::query()
            ->where('symbol', $symbol)
            ->orderByDesc('captured_at')
            ->limit($limit)
            ->get();
    }

    /**
     * 최근 스냅샷을 기반으로 EMA/SMA 지표를 계산하여 최신 스냅샷에 저장합니다.
     */
    public function computeIndicators(string $symbol, int $emaShort = 20, int $emaLong = 60, int $volSma = 20): void
    {
        $need = max($emaLong, $volSma);

        $rows = MarketSnapshot::where('symbol', $symbol)
            ->orderByDesc('captured_at')
            ->take($need)
            ->get()
            ->reverse(); // 과거 → 최신

        if ($rows->count() < min($emaShort, $need)) {
            // 데이터 부족 시 스킵
            Log::debug('[MarketDataService] indicators skipped (insufficient rows)', [
                'symbol' => $symbol,
                'rows' => $rows->count(),
                'emaShort' => $emaShort,
                'emaLong' => $emaLong,
                'volSma' => $volSma,
            ]);
            return;
        }

        // price_last / volume 컬럼 사용 (close/volume 아님)
        $closes = $rows->pluck('price_last')->map(fn($v) => (float)$v)->all();
        $vols   = $rows->pluck('volume')->map(fn($v) => (float)$v)->all();

        $emaShortVal = $this->calcEma($closes, $emaShort);
        $emaLongVal  = $this->calcEma($closes, $emaLong);
        $volSmaVal   = $this->calcSma($vols, $volSma);

        $latest = $rows->last();
        $latest->ema20      = $emaShortVal; // 컬럼은 고정(ema20/ema60)
        $latest->ema60      = $emaLongVal;
        $latest->vol_sma20  = $volSmaVal;
        $latest->save();
    }

    /**
     * EMA 계산 (지수이동평균)
     */
    protected function calcEma(array $values, int $period): float
    {
        if (count($values) < $period) {
            return 0.0;
        }
        $k = 2 / ($period + 1);
        $ema = $values[0];
        for ($i = 1; $i < count($values); $i++) {
            $ema = $values[$i] * $k + $ema * (1 - $k);
        }
        return round($ema, 8);
    }

    /**
     * SMA 계산 (단순이동평균)
     */
    protected function calcSma(array $values, int $period): float
    {
        if (count($values) < $period) {
            return 0.0;
        }
        $slice = array_slice($values, -$period);
        return round(array_sum($slice) / $period, 8);
    }
}
