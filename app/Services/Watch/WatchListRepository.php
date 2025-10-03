<?php

namespace App\Services\Watch;

use App\Models\WatchList;

class WatchListRepository implements WatchListRepositoryInterface
{
    public function findBySymbol(string $symbol): ?WatchList
    {
        return WatchList::where('symbol', $symbol)->first();
    }

    public function getMetaMinQuote(string $symbol): ?float
    {
        $row = $this->findBySymbol($symbol);
        if (!$row) {
            return null;
        }
        $meta = $row->meta ?? [];
        $val  = $meta['min_total_quote'] ?? null;

        if ($val === null) return null;

        // 문자열로 저장되었을 수 있으니 안전 캐스팅
        $f = (float) $val;
        return $f > 0 ? $f : null;
    }

    public function mergeMeta(string $symbol, array $meta): void
    {
        $row = $this->findBySymbol($symbol);
        if (!$row) {
            return; // 없으면 스킵(필요하면 생성 로직 추가 가능)
        }
        $old = $row->meta ?? [];
        $new = array_replace($old, $meta);

        // 변경사항 있을 때만 저장
        if ($new !== $old) {
            $row->meta = $new;
            $row->save();
        }
    }
}
