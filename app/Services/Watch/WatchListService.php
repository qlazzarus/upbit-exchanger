<?php

namespace App\Services\Watch;

use App\Models\WatchList;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class WatchListService implements WatchListServiceInterface
{
    private string $tz;
    private int $ttlSec = 5;

    public function __construct(
        string $tz = 'Asia/Seoul',
        int $ttlSec = 5,
    ) {
        $this->tz = $tz;
        $this->ttlSec = $ttlSec;
    }

    public function enabledSymbols(): array
    {
        return Cache::remember('watchlist:enabled', $this->ttlSec, function () {
            return WatchList::query()
                ->where('enabled', true)
                ->orderBy('symbol')
                ->pluck('symbol')
                ->all();
        });
    }

    public function all(): Collection
    {
        return WatchList::query()
            ->orderByDesc('enabled')
            ->orderBy('symbol')
            ->get();
    }

    public function exists(string $symbol): bool
    {
        return WatchList::query()->where('symbol', $symbol)->exists();
    }

    public function isEnabled(string $symbol): bool
    {
        return WatchList::query()->where('symbol', $symbol)->where('enabled', true)->exists();
    }

    public function add(string $symbol, bool $enableIfExists = true): WatchList
    {
        $row = WatchList::query()->firstOrNew(['symbol' => $symbol]);
        if (!$row->exists) {
            $row->enabled = true;
            $row->save();
        } elseif ($enableIfExists && !$row->enabled) {
            $row->enabled = true;
            $row->save();
        }
        $this->clearCache();
        return $row->refresh();
    }

    public function remove(string $symbol): bool
    {
        $ok = (bool) WatchList::query()
            ->where('symbol', $symbol)
            ->update(['enabled' => false]);
        if ($ok) $this->clearCache();
        return $ok;
    }

    public function enable(string $symbol): bool
    {
        $ok = (bool) WatchList::query()->where('symbol', $symbol)->update(['enabled' => true]);
        if ($ok) $this->clearCache();
        return $ok;
    }

    public function disable(string $symbol): bool
    {
        $ok = (bool) WatchList::query()->where('symbol', $symbol)->update(['enabled' => false]);
        if ($ok) $this->clearCache();
        return $ok;
    }

    public function toggle(string $symbol): bool
    {
        $row = WatchList::query()->where('symbol', $symbol)->first();
        if (!$row) return false;
        $row->enabled = !$row->enabled;
        $row->save();
        $this->clearCache();
        return true;
    }

    /**
     * @throws Throwable
     */
    public function bulkAdd(array $symbols, bool $enableIfExists = true): int
    {
        $symbols = array_values(array_unique(array_map('strval', $symbols)));
        DB::transaction(function () use ($symbols, $enableIfExists) {
            foreach ($symbols as $s) {
                $this->add($s, $enableIfExists);
            }
        });
        return count($symbols);
    }

    public function bulkRemove(array $symbols): int
    {
        $symbols = array_values(array_unique(array_map('strval', $symbols)));
        $count = (int) WatchList::query()
            ->whereIn('symbol', $symbols)
            ->update(['enabled' => false]);
        if ($count > 0) $this->clearCache();
        return $count;
    }

    /**
     * @throws Throwable
     */
    public function rebuildDaily(array $options = []): int
    {
        $take  = (int)($options['take'] ?? 30);
        $merge = (bool)($options['merge'] ?? false);

        // 최신 스냅샷 기준 상위 거래량 심볼 선정 (각 심볼의 최신 1건)
        $latestPerSymbol = DB::table('market_snapshots')
            ->select('symbol', DB::raw('MAX(captured_at) AS max_captured'))
            ->groupBy('symbol');

        $top = DB::table('market_snapshots AS ms')
            ->joinSub($latestPerSymbol, 't', function ($j) {
                $j->on('ms.symbol', '=', 't.symbol')
                  ->on('ms.captured_at', '=', 't.max_captured');
            })
            ->orderByDesc('ms.volume')
            ->limit($take)
            ->pluck('ms.symbol')
            ->all();

        // 트랜잭션으로 enable/disable 적용
        DB::transaction(function () use ($top, $merge) {
            // 후보들 enable
            if (!empty($top)) {
                WatchList::query()->whereIn('symbol', $top)->update(['enabled' => true]);
                foreach ($top as $s) {
                    // 존재하지 않으면 생성(멱등)
                    WatchList::query()->firstOrCreate(['symbol' => $s], ['enabled' => true]);
                }
            }
            // merge=false면 후보 외는 disable
            if (!$merge) {
                WatchList::query()->whereNotIn('symbol', $top)->update(['enabled' => false]);
            }
        });

        $this->clearCache();
        return count($this->enabledSymbols());
    }

    public function syncExchangeMeta(array $symbols = []): int
    {
        // 업비트 공개 API에서는 최소주문금액/호가단위 정보를 직접 제공하지 않습니다.
        // (사설 API orders/chance를 심볼별 호출하면 가능하나 레이트리밋 이슈가 큽니다.)
        // 현재는 안전한 no-op으로 두고, 추후 UpbitClient에 메타 조회가 추가되면 반영합니다.
        if (!empty($symbols)) {
            $symbols = array_values(array_unique(array_map('strval', $symbols)));
        }
        logger()->info('[WatchListService] syncExchangeMeta skipped (no-op). Consider implementing via orders/chance per symbol with rate limiting.');
        return 0;
    }

    public function clearCache(): void
    {
        Cache::forget('watchlist:enabled');
    }
}
