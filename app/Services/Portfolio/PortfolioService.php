<?php

namespace App\Services\Portfolio;

use App\Services\Exchange\UpbitClient;
use App\Services\Risk\RiskManagerInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class PortfolioService implements PortfolioServiceInterface
{
    private UpbitClient $upbit;
    private RiskManagerInterface $risk;
    private string $asset = 'USDT';
    private int $balanceTtlSec = 3;
    private string $tz = 'Asia/Seoul';

    public function __construct(
        UpbitClient          $upbit,
        RiskManagerInterface $risk,
        string               $asset = 'USDT',              // 기본 자산
        string               $tz = 'Asia/Seoul',
        int                  $balanceTtlSec = 3               // 잔고 단기 캐시 (초)
    )
    {
        $this->tz = $tz;
        $this->balanceTtlSec = $balanceTtlSec;
        $this->asset = $asset;
        $this->risk = $risk;
        $this->upbit = $upbit;
    }

    public function freeUsdt(): float
    {
        // 잔고 호출은 비싸므로 2~5초 정도 초단기 캐시 권장
        $key = 'portfolio:free:' . strtoupper($this->asset);
        $free = Cache::remember($key, $this->balanceTtlSec, function () {
            try {
                $balances = $this->upbit->fetchBalances();   // [{asset, free, locked, total}, ...]
                $row = collect($balances)->firstWhere(
                    fn($b) => strtoupper($b['asset'] ?? '') === strtoupper($this->asset)
                );
                if (!$row) return 0.0;
                // free 우선, 없으면 total 사용(거래소 포맷 보호)
                return (float)($row['free'] ?? $row['total'] ?? 0.0);
            } catch (Throwable $e) {
                Log::warning('[PortfolioService] freeUsdt failed', ['err' => $e->getMessage()]);
                return 0.0;
            }
        });

        return (float)$free;
    }

    public function remainingDailyBudgetUsdt(): float
    {
        // 예산 정책은 RiskManager가 책임 — 그대로 위임
        return (float)$this->risk->remainingDailyBudgetUsdt();
    }

    public function canAfford(float $usdt): bool
    {
        if ($usdt <= 0) return false;

        $free = $this->freeUsdt();
        $remain = $this->remainingDailyBudgetUsdt();

        // 실보유잔고 & 일일예산 잔여액 모두 충족해야 신규 진입 가능
        return $usdt <= $free + 1e-9 && $usdt <= $remain + 1e-9;
    }
}
