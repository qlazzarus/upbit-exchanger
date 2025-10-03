<?php

namespace App\Providers;

use App\Services\DryFireGuard;
use App\Services\Execution\OrderExecutor;
use App\Services\Execution\OrderExecutorInterface;
use App\Services\Market\MarketDataService;
use App\Services\Market\MarketDataServiceInterface;
use App\Services\Portfolio\PortfolioService;
use App\Services\Portfolio\PortfolioServiceInterface;
use App\Services\Position\PositionService;
use App\Services\Position\PositionServiceInterface;
use App\Services\Reporting\DailyReportService;
use App\Services\Reporting\DailyReportServiceInterface;
use App\Services\Reporting\GoogleSheetAppender;
use App\Services\Reporting\LedgerAggregator;
use App\Services\Reporting\LedgerAggregatorInterface;
use App\Services\Reporting\MailNotifier;
use App\Services\Reporting\MailNotifierInterface;
use App\Services\Reporting\SheetAppenderInterface;
use App\Services\Risk\RiskManager;
use App\Services\Risk\RiskManagerInterface;
use App\Services\Signals\SignalService;
use App\Services\Signals\SignalServiceInterface;
use App\Services\Watch\PositionWatcher;
use App\Services\Watch\PositionWatcherInterface;
use App\Services\Watch\WatchListRepository;
use App\Services\Watch\WatchListRepositoryInterface;
use App\Services\Watch\WatchListService;
use App\Services\Watch\WatchListServiceInterface;
use Google\Client;
use Google\Service\Sheets;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
        $this->app->singleton(DryFireGuard::class, function ($app) {
            return new DryFireGuard();
        });
        $this->app->singleton(RiskManagerInterface::class, RiskManager::class);
        $this->app->singleton(Sheets::class, function () {
            $cfg = config('reporting.sheets', []);
            $credPath = $cfg['credentials'] ?? null;
            if (!$credPath || !file_exists($credPath)) return null;

            $client = new Client();
            $client->setAuthConfig($credPath);
            $client->setScopes(['https://www.googleapis.com/auth/spreadsheets']);
            return new Sheets($client);
        });

        $this->app->bind(MarketDataServiceInterface::class, MarketDataService::class);
        $this->app->bind(WatchListServiceInterface::class, WatchListService::class);
        $this->app->bind(WatchListRepositoryInterface::class, WatchListRepository::class);
        $this->app->bind(SignalServiceInterface::class, SignalService::class);
        $this->app->bind(OrderExecutorInterface::class, OrderExecutor::class);
        $this->app->bind(PositionServiceInterface::class, PositionService::class);
        $this->app->bind(DailyReportServiceInterface::class, DailyReportService::class);
        $this->app->bind(LedgerAggregatorInterface::class, LedgerAggregator::class);
        $this->app->bind(SheetAppenderInterface::class, GoogleSheetAppender::class);
        $this->app->bind(MailNotifierInterface::class, MailNotifier::class);
        $this->app->bind(PortfolioServiceInterface::class, PortfolioService::class);
        $this->app->bind(PositionWatcherInterface::class, PositionWatcher::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
