<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Position\PositionServiceInterface;
use App\Services\Execution\OrderExecutorInterface;
use App\Services\Market\MarketDataServiceInterface;
use App\Services\DryFireGuard;
use Throwable;

class BotFlattenCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Options:
     *  --symbol=BTC/USDT,ETH/USDT : 특정 심볼만 청산(콤마 구분)
     *  --dry : DRY 모드 강제(실주문 없이 상태만 종료)
     *
     * @var string
     */
    protected $signature = 'bot:flatten {--symbol=} {--dry}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Flatten all open positions (DRY=close states only, REAL=market sell and close)';

    /**
     * Execute the console command.
     */
    public function handle(
        PositionServiceInterface   $positions,
        OrderExecutorInterface     $exec,
        MarketDataServiceInterface $md,
        DryFireGuard               $guard,
    ): int
    {
        $onlySymbols = $this->option('symbol');
        $only = [];
        if (is_string($onlySymbols) && $onlySymbols !== '') {
            $only = array_values(array_filter(array_map(fn($s) => trim($s), explode(',', $onlySymbols))));
        }

        $forceDry = (bool)$this->option('dry');
        $isDry = $forceDry || $guard->active();

        $open = $positions->getOpenPositions();
        $targets = collect($open)->filter(function ($p) use ($only) {
            if (empty($only)) return true;
            return in_array($p->symbol, $only, true);
        })->values();

        if ($targets->isEmpty()) {
            $this->info('[flatten] no open positions');
            return self::SUCCESS;
        }

        $this->info(sprintf('[flatten] starting: mode=%s, targets=%d', $isDry ? 'DRY' : 'REAL', $targets->count()));

        $ok = 0;
        $fail = 0;
        $skipped = 0;

        foreach ($targets as $pos) {
            try {
                $last = $md->getLastPrice($pos->symbol);
                if (!$last) {
                    $this->warn(sprintf('skip %s: no price', $pos->symbol));
                    $skipped++;
                    continue;
                }

                if ($isDry) {
                    $positions->close($pos, $last, ['reason' => 'FLATTEN_DRY']);
                    $this->line(sprintf('DRY close %s qty=%s @~%s', $pos->symbol, (string)$pos->qty, (string)$last));
                    $ok++;
                    continue;
                }

                $res = $exec->marketSell($pos->symbol, $pos->qty);
                $exit = $res->avgPrice ?? $last;
                $positions->close($pos, (float)$exit, [
                    'reason' => 'FLATTEN_REAL',
                    'sell_fee' => $res->fee ?? 0,
                    'provider' => 'bot',
                ]);
                $this->line(sprintf('REAL sell %s qty=%s avg~%s (order=%s)', $pos->symbol, (string)$pos->qty, (string)$exit, $res->orderId ?? 'n/a'));
                $ok++;
            } catch (Throwable $e) {
                $this->error(sprintf('fail %s: %s', $pos->symbol, $e->getMessage()));
                $fail++;
            }
        }

        $this->info(sprintf('[flatten] done: ok=%d, skipped=%d, fail=%d', $ok, $skipped, $fail));
        return $fail === 0 ? self::SUCCESS : self::FAILURE;
    }
}
