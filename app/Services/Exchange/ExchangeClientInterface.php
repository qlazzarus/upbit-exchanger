<?php

namespace App\Services\Exchange;

interface ExchangeClientInterface
{
    /** 잔고 전부 [{asset:'USDT', free:..., locked:..., total:...}, ...] */
    public function fetchBalances(): array;

    /** 특정 자산의 가용액(예: USDT free) */
    public function fetchFree(string $asset): ?float;

    /** 마지막 체결가 */
    public function fetchLastPrice(string $symbol): ?float;

    /** 시장가 매수/매도 (실사용 시) */
    public function createMarketBuy(string $symbol, float $qty): array;

    public function createMarketSell(string $symbol, float $qty): array;

}
