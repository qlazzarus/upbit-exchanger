<?php

namespace App\Console\Commands;

use App\Services\Watch\WatchListServiceInterface;
use Illuminate\Console\Command;
use App\Services\Portfolio\PortfolioServiceInterface;
use App\Services\Risk\RiskManagerInterface;
use App\Services\Market\MarketDataServiceInterface;
use Throwable;

class BotMorningScanCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Options:
     *  --symbols=BTC/USDT,ETH/USDT  : 특정 심볼만 처리(콤마 구분)
     *  --recompute                  : 인디케이터 재계산 강제
     *
     * @var string
     */
    protected $signature = 'bot:morning-scan {--symbols=} {--recompute}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Morning prep: check balances/budget and refresh market snapshots & indicators';

    /**
     * Execute the console command.
     */
    public function handle(
        PortfolioServiceInterface  $portfolio,
        RiskManagerInterface       $risk,
        MarketDataServiceInterface $md,
        WatchListServiceInterface  $watch,
    ): int
    {
        $tz = config('reporting.timezone', 'Asia/Seoul');
        $onlySymbols = $this->parseSymbols((string)$this->option('symbols'));
        $forceRecompute = (bool)$this->option('recompute');

        // 1) 포트폴리오 상태 출력
        $free = $portfolio->freeUsdt();
        $remain = $portfolio->remainingDailyBudgetUsdt();
        $this->info(sprintf('[morning] free=%.4f USDT, remainDaily=%.4f USDT', $free, $remain));
        if ($risk->shouldHaltTrading()) {
            $this->warn('[morning] Trading HALT is ON (daily drawdown hit)');
        }

        // 2) 워치 대상 심볼 로드
        $symbols = $onlySymbols ?: $watch->enabledSymbols();

        if (empty($symbols)) {
            $this->warn('[morning] no symbols to scan');
            return self::SUCCESS;
        }

        // 3) 시세 스냅샷 (분봉 캔들 upsert)
        $this->line(sprintf('[morning] snapshot %d symbols …', count($symbols)));
        $snapCount = $md->snapshot($symbols);
        $this->line(sprintf('[morning] snapshot rows upserted: %d', $snapCount));

        // 4) 인디케이터 계산(EMA20/EMA60, volSMA20)
        $this->line('[morning] compute indicators …');
        $computed = 0;
        $errors = 0;
        foreach ($symbols as $sym) {
            try {
                // 현재 구현은 전체 재계산에 가깝지만, 추후 증분 계산으로 최적화 가능
                $md->computeIndicators($sym, 20, 60, 20);
                $computed++;
            } catch (Throwable $e) {
                $errors++;
                $this->warn(sprintf('indicator fail %s: %s', $sym, $e->getMessage()));
            }
        }
        $this->line(sprintf('[morning] indicators computed: %d (errors=%d)', $computed, $errors));

        $this->info('[morning] done.');
        return self::SUCCESS;
    }

    /** @return array<int,string> */
    private function parseSymbols(?string $csv): array
    {
        if (!$csv) return [];
        return array_values(array_filter(array_map(fn($s) => trim($s), explode(',', $csv))));
    }
}
