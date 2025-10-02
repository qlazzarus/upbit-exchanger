<?php

namespace App\Console\Commands;

use App\Services\Watch\WatchListServiceInterface;
use Illuminate\Console\Command;

class BotBuildWatchListCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bot:build-watch-list
    {--take=30 : 워치리스트에 담을 심볼 개수}
    {--merge : 기존 워치리스트와 병합할지 여부}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(WatchListServiceInterface $watch): int
    {
        //
        $take = (int)($this->option('take') ?? 30);
        $merge = (bool)$this->option('merge'); // 기존과 병합 여부

        $count = $watch->rebuildDaily([
            'source' => 'top_volume', // or top_change / manual_merge ...
            'take'   => $take,
            'merge'  => $merge,
        ]);

        $updated = $watch->syncExchangeMeta(); // 틱/스텝/최소금액 등
        $watch->clearCache();

        $this->info("watchlist rebuilt: enabled={$count}, metaUpdated={$updated}");
        return self::SUCCESS;
    }
}
