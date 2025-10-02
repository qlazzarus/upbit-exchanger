<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use App\Services\Exchange\UpbitClient;
use App\Models\DailyLedger;
use App\Services\Portfolio\PortfolioServiceInterface;
use Illuminate\Support\Facades\DB;

class BotResetDayCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Options:
     *  --asset=USDT       : 기준 자산(기본 USDT)
     *  --force            : 기존 equity_start_usdt가 있어도 덮어쓰기
     *  --note=...         : 메모 기록
     *  --clear-cooldowns  : 심볼 재진입 쿨다운 키 제거(가능한 경우)
     *
     * @var string
     */
    protected $signature = 'bot:reset-day {--asset=USDT} {--force} {--note=} {--clear-cooldowns}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start a new trading day: set equity_start_usdt and reset daily risk counters';

    /**
     * Execute the console command.
     * @throws ConnectionException
     */
    public function handle(UpbitClient $upbit, PortfolioServiceInterface $portfolio): int
    {
        $tz = config('reporting.timezone', 'Asia/Seoul');
        $date = now($tz)->toDateString();
        $asset = strtoupper((string)$this->option('asset')) ?: 'USDT';
        $force = (bool)$this->option('force');
        $note = $this->option('note');
        $clearCooldowns = (bool)$this->option('clear-cooldowns');

        // 1) 현재 잔고 조회
        // 기본은 PortfolioService(USDT free) 기준으로 일관성 유지
        if ($asset === 'USDT') {
            $equityStart = (float)$portfolio->freeUsdt();
        } else {
            // 다른 기준자산 지정 시에는 직접 조회로 처리
            $balances = $upbit->fetchBalances();
            $row = collect($balances)->firstWhere(fn($b) => strtoupper($b['asset'] ?? '') === $asset);
            if (!$row) {
                $this->error("Asset {$asset} balance not found.");
                return self::FAILURE;
            }
            $equityStart = (float)($row['total'] ?? $row['free'] ?? 0.0);
        }

        // 2) DailyLedger upsert (오늘 날짜, 자정으로 정규화)
        $keyDate = now($tz)->startOfDay();

        // 기존 레코드 조회(있으면 start 값 보존 여부 판단)
        $existing = DailyLedger::query()->whereDate('date', '=', $keyDate->toDateString())->first();

        $payload = [
            // 집계 값은 초기화(집계는 report:daily에서 수행)
            'pnl_usdt'     => $existing->pnl_usdt ?? 0.0,
            'trades_count' => $existing->trades_count ?? 0,
        ];

        if ($note) {
            $payload['notes'] = trim((string) $note);
        }

        // equity_start_usdt는 --force 이거나 기존 값이 비어있을 때만 갱신
        if ($force || empty($existing?->equity_start_usdt)) {
            $payload['equity_start_usdt'] = $equityStart;
        } else {
            $this->warn("equity_start_usdt already set ({$existing->equity_start_usdt}). Use --force to overwrite.");
        }

        DailyLedger::query()->updateOrCreate(
            ['date' => $keyDate],
            $payload
        );

        // 3) 일일 리스크 카운터 초기화
        $dateStr = $keyDate->toDateString();
        $usedKey = "risk:used:{$dateStr}";
        $pnlKey  = "risk:pnl:{$dateStr}";
        Cache::forget($usedKey);
        Cache::forget($pnlKey);

        // (옵션) 심볼 재진입 쿨다운 제거
        if ($clearCooldowns) {
            $this->clearCooldownKeys();
        }

        // === Cleanup old market snapshots ===
        $yesterday = now($tz)->subDay()->startOfDay();
        DB::table('market_snapshots')->where('captured_at', '<', $yesterday)->delete();
        $this->info("Old market_snapshots before {$yesterday} deleted.");

        $this->info(sprintf('Reset-day done: date=%s asset=%s equity_start=%.8f', $date, $asset, $equityStart));
        return self::SUCCESS;
    }

    /**
     * Try to clear cooldown keys if cache store supports key iteration (e.g., Redis).
     */
    protected function clearCooldownKeys(): void
    {
        $store = Cache::getStore();
        $connection = method_exists($store, 'connection') ? $store->connection() : null;
        if (!$connection || !method_exists($connection, 'scan')) {
            $this->warn('[reset-day] clear-cooldowns skipped: cache driver does not support SCAN');
            return;
        }
        $deleted = 0;
        $it = null;
        while (true) {
            [$it, $keys] = $connection->scan($it, 'MATCH', 'risk:cooldown:*', 'COUNT', 1000) ?: [null, []];
            if (!empty($keys)) {
                foreach ($keys as $k) {
                    $connection->del($k);
                    $deleted++;
                }
            }
            if ($it === 0 || $it === '0' || $it === null) break;
        }
        $this->info("[reset-day] cleared cooldown keys: {$deleted}");
    }
}
