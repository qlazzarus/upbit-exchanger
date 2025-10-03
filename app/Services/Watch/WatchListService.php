<?php

namespace App\Services\Watch;

use App\Models\WatchList;
use App\Services\Exchange\UpbitClient;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class WatchListService implements WatchListServiceInterface
{
    private UpbitClient $upbit;
    private WatchListRepositoryInterface $watchListRepo;
    private string $tz;
    private int $ttlSec = 5;

    public function __construct(
        UpbitClient $upbit,
        WatchListRepositoryInterface $watchListRepo,
        string $tz = 'Asia/Seoul',
        int $ttlSec = 5,
    ) {
        $this->upbit = $upbit;
        $this->watchListRepo = $watchListRepo;
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

    public function syncExchangeMeta(array|string|null $symbols = null): int
    {
        try {
            // Normalize input → array of symbols
            if (is_null($symbols)) {
                $symbols = $this->enabledSymbols();
            } elseif (is_string($symbols)) {
                $symbols = [$symbols];
            } elseif (is_array($symbols)) {
                $symbols = array_values(array_unique(array_map('strval', $symbols)));
            } else {
                $symbols = [];
            }

            $updated = 0;
            foreach ($symbols as $symbol) {
                try {
                    $chance = $this->upbit->getOrdersChance($symbol);

                    if (!isset($chance['market'])) {
                        Log::warning('[WatchListService] syncExchangeMeta failed', [
                            'symbol' => $symbol,
                            'resp'   => $chance,
                        ]);
                        continue;
                    }

                    // Upbit orders/chance: market[bid|min_total], market[ask|min_total], bid_fee, ask_fee
                    $minTotalBid = $chance['market']['bid']['min_total'] ?? null; // 매수 최소 총액
                    $minTotalAsk = $chance['market']['ask']['min_total'] ?? null; // 매도 최소 총액
                    $bidFee      = $chance['bid_fee'] ?? ($chance['market']['bid_fee'] ?? null);
                    $askFee      = $chance['ask_fee'] ?? ($chance['market']['ask_fee'] ?? null);

                    $meta = [
                        'min_total_quote' => $minTotalBid ?? $minTotalAsk, // 우선순위: 매수 최소, 없으면 매도 최소
                        'min_total_bid'   => $minTotalBid,
                        'min_total_ask'   => $minTotalAsk,
                        'bid_fee'         => is_null($bidFee) ? null : (float)$bidFee,
                        'ask_fee'         => is_null($askFee) ? null : (float)$askFee,
                        'synced_at'       => now()->toDateTimeString(),
                    ];

                    $this->watchListRepo->mergeMeta($symbol, $meta);

                    Log::info('[WatchListService] syncExchangeMeta updated', [
                        'symbol'     => $symbol,
                        'min_bid'    => $meta['min_total_bid'],
                        'min_ask'    => $meta['min_total_ask'],
                        'min_quote'  => $meta['min_total_quote'],
                        'bid_fee'    => $meta['bid_fee'],
                        'ask_fee'    => $meta['ask_fee'],
                    ]);

                    $updated++;
                } catch (Throwable $e) {
                    Log::error('[WatchListService] syncExchangeMeta error(symbol)', [
                        'symbol' => $symbol,
                        'error'  => $e->getMessage(),
                    ]);
                }
            }

            return $updated;
        } catch (Throwable $e) {
            Log::error('[WatchListService] syncExchangeMeta error', [
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    public function clearCache(): void
    {
        Cache::forget('watchlist:enabled');
    }
}
