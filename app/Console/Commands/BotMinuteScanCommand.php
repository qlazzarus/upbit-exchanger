<?php

namespace App\Console\Commands;

use App\Enums\SignalSkipReasonEnum;
use App\Enums\TradeModeEnum;
use App\Services\Execution\OrderExecutorInterface;
use App\Services\Market\MarketDataServiceInterface;
use App\Services\Portfolio\PortfolioServiceInterface;
use App\Services\Position\PositionServiceInterface;
use App\Services\Risk\RiskManagerInterface;
use App\Services\Signals\SignalServiceInterface;
use App\Services\Watch\WatchListServiceInterface;
use Illuminate\Console\Command;
use Throwable;

class BotMinuteScanCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bot:minute-scan {--order= : 진입 1회 예산(USDT) override}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '매 분(주간)/5분(야간)마다 시그널을 평가하여 예산 한도 내에서 진입';

    public function __construct(
        protected MarketDataServiceInterface $md,
        protected SignalServiceInterface     $signalService,
        protected RiskManagerInterface       $risk,
        protected OrderExecutorInterface     $exec,
        protected PositionServiceInterface   $positions,
        protected PortfolioServiceInterface  $portfolio,
        protected WatchListServiceInterface  $watch,
    )
    {
        parent::__construct();
    }

    /**
     * @throws Throwable
     */
    public function handle(): int
    {
        // 1) (선택) 최신 분봉 스냅샷 업데이트
        //    - minute-scan이 분봉 수집까지 맡는 경우 유지. 이미 다른 잡에서 수집 시 주석처리 가능.
        $this->md->snapshot($this->watch->enabledSymbols());

        // 2) 시그널 생성/가져오기
        $this->info('minute-scan started ...');
        $candidates = $this->signalService->generateOrFetch();

        // 미리 열린 포지션 심볼 수집 → 중복 진입 방지
        $openSymbols = collect($this->positions->getOpenPositions())
            ->pluck('symbol')
            ->map(fn($s) => (string)$s)
            ->unique()
            ->values()
            ->all();

        $this->info('candidates count = ' . count($candidates));
        if (!$candidates->count()) {
            $this->line('no candidates');
            return self::SUCCESS;
        }

        // 3) 1회 주문 금액(USDT) 결정
        $orderUsdt = (float)($this->option('order') ?? config('bot.order_usdt', 5.0));
        if ($orderUsdt <= 0) {
            $this->warn('order amount must be > 0');
            return self::SUCCESS;
        }

        foreach ($candidates as $sig) {
            $symbol = $sig->symbol;

            // --- 중복 진입 방지: 이미 오픈 포지션이면 스킵 ---
            if (in_array($symbol, $openSymbols, true)) {
                $this->line("skip {$symbol}: already has open position");
                // 신호 스킵 기록
                if (method_exists($this->signalService, 'markSkipped')) {
                    $this->signalService->markSkipped($sig, SignalSkipReasonEnum::OPEN_POSITION);
                }
                continue;
            }

            // --- 3-1) 리스크/예산 확인 ---
            $decision = $this->risk->canEnter($symbol, $orderUsdt);
            if (!$decision->allowed) {
                $this->line(sprintf(
                    'skip %s: risk=%s remain=%.4f%s',
                    $symbol,
                    $decision->reasonCode ?? 'unknown',
                    $decision->remainingBudgetUsdt ?? 0.0,
                    $decision->cooldownSec ? " cooldown={$decision->cooldownSec}s" : ''
                ));
                if (method_exists($this->signalService, 'markSkipped')) {
                    $this->signalService->markSkipped($sig, SignalSkipReasonEnum::RISK_REJECTED);
                }
                continue;
            }

            if (!$this->portfolio->canAfford($orderUsdt)) {
                $this->line(sprintf(
                    'skip %s: cannot afford %.4f (free=%.4f, remainDaily=%.4f)',
                    $symbol,
                    $orderUsdt,
                    $this->portfolio->freeUsdt(),
                    $this->portfolio->remainingDailyBudgetUsdt()
                ));
                if (method_exists($this->signalService, 'markSkipped')) {
                    $this->signalService->markSkipped($sig, SignalSkipReasonEnum::INSUFFICIENT_FUNDS);
                }
                continue;
            }

            // --- 3-2) 정보 출력용 현재가 (실제 매수는 quote 기반) ---
            $price = $this->md->getLastPrice($symbol);
            if (!$price) {
                $this->line("skip {$symbol}: no price available");
                if (method_exists($this->signalService, 'markSkipped')) {
                    $this->signalService->markSkipped($sig, SignalSkipReasonEnum::NO_PRICE);
                }
                continue;
            }

            // --- 3-3) 시장가 매수(견적통화 금액 기준) ---
            try {
                $res = $this->exec->marketBuyByQuote($symbol, $orderUsdt);
            } catch (Throwable $e) {
                $this->error("buy fail {$symbol}: {$e->getMessage()}");
                if (method_exists($this->signalService, 'markSkipped')) {
                    $this->signalService->markSkipped($sig, SignalSkipReasonEnum::BUY_FAILED);
                }
                continue;
            }

            // ExecutionResult -> Position 오픈
            $mode = $res->mode ?? TradeModeEnum::DRY;
            $avg = $res->avgPrice ?? $price;
            $qty = $res->filledQty ?? ($avg > 0 ? round($orderUsdt / $avg, 6) : null);

            if (!$qty || $qty <= 0) {
                $this->warn("skip {$symbol}: qty unresolved");
                if (method_exists($this->signalService, 'markSkipped')) {
                    $this->signalService->markSkipped($sig, SignalSkipReasonEnum::QTY_UNRESOLVED);
                }
                continue;
            }

            $pos = $this->positions->open(
                symbol: $symbol,
                qty: (float)$qty,
                entryPrice: (float)$avg,
                mode: $mode,
                tp: round($avg * 1.01, 8),
                sl: round($avg * 0.99, 8),
                meta: [
                    'buy_fee' => $res->fee ?? 0.0,
                    'buy_executed_at' => $res->executedAt ?? now(),
                    'provider' => 'bot',
                ]
            );

            if (method_exists($this->signalService, 'markConsumed')) {
                $this->signalService->markConsumed($sig);
            }
            // 방금 진입한 심볼은 중복 진입 방지를 위해 목록에 추가
            $openSymbols[] = $symbol;

            // (선택) 채운 예산을 리스크 매니저에 기록
            $this->risk->registerFill($symbol, (float)($res->filledQuote ?? $orderUsdt), 0.0);

            $this->info(sprintf(
                'ENTER %s qty=%s @%s mode=%s (order=%.4f, fee=%.8f)',
                $symbol,
                number_format($qty, 6, '.', ''),
                number_format($avg, 8, '.', ''),
                $mode->value,
                $orderUsdt,
                (float)($res->fee ?? 0.0)
            ));
        }

        return self::SUCCESS;
    }
}
