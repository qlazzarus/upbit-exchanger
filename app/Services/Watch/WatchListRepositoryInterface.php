<?php

namespace App\Services\Watch;

use App\Models\WatchList;

interface WatchListRepositoryInterface
{
    /** 심볼로 1건 조회 (없으면 null) */
    public function findBySymbol(string $symbol): ?WatchList;

    /**
     * 메타에서 최소 주문 총액(KRW/USDT 기준)을 읽어온다.
     * - 없으면 null 반환 (호출부에서 config 기본값과 max() 처리)
     */
    public function getMetaMinQuote(string $symbol): ?float;

    /**
     * 메타 병합(덮어쓰기). ['min_total_quote'=>5000, ...] 같은 형태.
     * - 변경사항 있을 때만 save()
     */
    public function mergeMeta(string $symbol, array $meta): void;
}
