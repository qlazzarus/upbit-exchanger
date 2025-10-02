<?php

namespace App\Services\Execution;

use App\DTO\Execution\ExecutionResult;
use App\Enums\TradeModeEnum;
use App\Services\DryFireGuard;
use App\Services\Exchange\UpbitClient;
use App\Services\Market\MarketDataService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;
use DateTimeImmutable;

class OrderExecutor implements OrderExecutorInterface
{
    public function __construct(
        protected UpbitClient       $upbit,
        protected MarketDataService $md,
        protected DryFireGuard      $guard,
    )
    {
    }

    /** 현재가 조회
     * @throws ConnectionException
     */
    public function getLastPrice(string $symbol): ?float
    {
        return $this->md->getLastPrice($symbol) ?? $this->upbit->fetchLastPrice($symbol);
    }

    /** 견적통화 금액으로 시장가 매수
     * @throws ConnectionException
     */
    public function marketBuyByQuote(string $symbol, float $quoteAmount): ExecutionResult
    {
        $isDry = $this->guard->active();
        $nowMode = $isDry ? TradeModeEnum::DRY : TradeModeEnum::REAL;
        $executedAt = new DateTimeImmutable('now');

        if ($isDry) {
            $last = $this->getLastPrice($symbol) ?? 0.0;
            $qty = $last > 0 ? $quoteAmount / $last : 0.0;
            Log::info('[DRY] marketBuyByQuote', compact('symbol', 'quoteAmount', 'last', 'qty'));

            return new ExecutionResult(
                mode: $nowMode,
                executed: true,
                side: 'buy',
                symbol: $symbol,
                avgPrice: $last ?: null,
                filledQty: $qty ?: null,
                filledQuote: $quoteAmount,
                fee: null,
                orderId: null,
                executedAt: $executedAt,
                raw: null,
            );
        }

        // REAL
        $last = $this->getLastPrice($symbol) ?? null; // 참고용 평균가 추정
        $res = $this->upbit->createMarketBuy($symbol, $quoteAmount);

        return new ExecutionResult(
            mode: $nowMode,
            executed: true,
            side: 'buy',
            symbol: $symbol,
            avgPrice: $last,
            filledQty: null,            // 필요 시 체결 상세 조회로 보강
            filledQuote: $quoteAmount,
            fee: null,
            orderId: $res['uuid'] ?? null,
            executedAt: $executedAt,
            raw: $res,
        );
    }

    /** 베이스 수량으로 시장가 매도
     * @throws ConnectionException
     */
    public function marketSell(string $symbol, float $baseQty): ExecutionResult
    {
        $isDry = $this->guard->active();
        $nowMode = $isDry ? TradeModeEnum::DRY : TradeModeEnum::REAL;
        $executedAt = new DateTimeImmutable('now');

        if ($isDry) {
            $last = $this->getLastPrice($symbol) ?? 0.0;
            Log::info('[DRY] marketSell', compact('symbol', 'baseQty', 'last'));

            return new ExecutionResult(
                mode: $nowMode,
                executed: true,
                side: 'sell',
                symbol: $symbol,
                avgPrice: $last ?: null,
                filledQty: $baseQty,
                filledQuote: $last > 0 ? $last * $baseQty : null,
                fee: null,
                orderId: null,
                executedAt: $executedAt,
                raw: null,
            );
        }

        // REAL
        $last = $this->getLastPrice($symbol) ?? null; // 참고용 평균가 추정
        $res = $this->upbit->createMarketSell($symbol, $baseQty);

        return new ExecutionResult(
            mode: $nowMode,
            executed: true,
            side: 'sell',
            symbol: $symbol,
            avgPrice: $last,
            filledQty: $baseQty,
            filledQuote: $last ? $last * $baseQty : null,
            fee: null,
            orderId: $res['uuid'] ?? null,
            executedAt: $executedAt,
            raw: $res,
        );
    }

    public function cancel(string $orderId): bool
    {
        if ($this->guard->active()) {
            Log::info('[DRY] cancel', compact('orderId'));
            return true;
        }
        try {
            return (bool)$this->upbit->cancelOrder($orderId);
        } catch (\Throwable $e) {
            Log::warning('[OrderExecutor] cancel failed', ['orderId' => $orderId, 'error' => $e->getMessage()]);
            return false;
        }
    }
}
