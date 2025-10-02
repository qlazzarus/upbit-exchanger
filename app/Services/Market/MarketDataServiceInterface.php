<?php

namespace App\Services\Market;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

interface MarketDataServiceInterface
{
    /**
     * 관심 심볼들의 최신 분봉(또는 틱)을 가져와 market_snapshots에 저장
     * @return int upsert/insert된 스냅샷 개수
     */
    public function snapshot(?array $symbols = null, ?CarbonInterface $at = null): int;

    /** 최근 가격(가능하면 캐시 사용) */
    public function getLastPrice(string $symbol): ?float;

    /** 최근 N개 스냅샷 */
    public function getRecentSnapshots(string $symbol, int $limit = 60): Collection;

    /** 지표(EMA/SMA 등) 계산하여 스냅샷 행 업데이트 */
    public function computeIndicators(string $symbol, int $emaShort = 20, int $emaLong = 60, int $volSma = 20): void;

}
