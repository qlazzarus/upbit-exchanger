<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Watch\PositionWatcherInterface;
use Random\RandomException;
use Throwable;

class BotWatchCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Options:
     *  --interval:  base sleep seconds between ticks (default 5)
     *  --jitter:    extra random seconds added to interval (default 2)
     *  --once:      run a single tick then exit
     *  --max-errors:consecutive errors allowed before exit (default 50)
     *
     * @var string
     */
    protected $signature = 'bot:watch
        {--interval=5 : Base sleep seconds between ticks}
        {--jitter=2 : Random extra seconds added to interval}
        {--once : Run a single tick and exit}
        {--max-errors=50 : Consecutive errors allowed before exit}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Watch open positions and auto-close by TP/SL/timeout on a short loop';

    /**
     * Execute the console command.
     * @throws RandomException
     */
    public function handle(PositionWatcherInterface $watcher): int
    {
        $interval = max(1, (int)$this->option('interval'));
        $jitter = max(0, (int)$this->option('jitter'));
        $once = (bool)$this->option('once');
        $maxErrors = max(1, (int)$this->option('max-errors'));

        $errors = 0;
        $tickNo = 0;

        $this->info(sprintf('[bot:watch] starting: interval=%ds jitter=%ds once=%s max-errors=%d', $interval, $jitter, $once ? 'yes' : 'no', $maxErrors));

        do {
            $tickNo++;
            try {
                $report = $watcher->tick();
                $this->line(sprintf(
                    '#%d scanned=%d tp=%d sl=%d timeout=%d err=%d',
                    $tickNo,
                    $report->positionsScanned,
                    $report->closedByTp,
                    $report->closedBySl,
                    $report->closedByTimeout,
                    $report->errors,
                ));
                $errors = 0; // reset on success
            } catch (Throwable $e) {
                $errors++;
                $this->error(sprintf('[bot:watch] tick error: %s', $e->getMessage()));
                if ($errors >= $maxErrors) {
                    $this->error('[bot:watch] too many consecutive errors — exiting');
                    return self::FAILURE;
                }
            }

            if ($once) {
                break;
            }

            // base interval + small jitter to avoid thundering herd / rate spikes
            $sleep = $interval + ($jitter > 0 ? random_int(0, $jitter) : 0);
            sleep($sleep);

            // Prevent memory growth on long runs
            if ($tickNo % 60 === 0 && function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        } while (true);

        $this->info('[bot:watch] done');
        return self::SUCCESS;
    }
}
