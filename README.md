<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="320" alt="Laravel"></a></p>

# Upbit Exchanger Bot (Laravel 12)

> 업비트 현물(USDT/KRW) 기준의 **시그널 기반 자동매매/리포팅** 보일러플레이트. DRY(모의)와 REAL(실거래) 두 모드 지원.

## 주요 기능 한눈에 보기
- **주기 스캔**: 아침 준비(`bot:morning-scan`), 분/야간 스캔(`bot:minute-scan`), 포지션 워치(`bot:watch`)
- **거래 집행**: `OrderExecutor`(REAL/DRY 일원화) — 시장가 매수/매도, 취소
- **리스크 관리**: 예산/쿨다운/DD 종합 판단(`RiskManager`) + `PortfolioService`(실잔고/일일예산)
- **포지션 관리/감시**: `PositionService`(open/close/trade/PnL), `PositionWatcher`(TP/SL/Timeout)
- **마켓데이터**: 업비트 **분봉 캔들** 수집, EMA20/60 + 거래량 SMA20 계산(`MarketDataService`)
- **리포팅**: Daily ledger 집계/시트 append/메일 발송(`report:daily`, `report:heartbeat`)

---

## 아키텍처 개요
```
Console Commands     Services (Domain/Infra)                  Exchange
┌─────────────────┐  ┌────────────────────────────────────┐   ┌─────────────┐
│ bot:morning-…   │→ │ MarketDataService  ──────── UpbitClient → Upbit REST │
│ bot:minute-scan │→ │ SignalService      ──────── Rules      └─────────────┘
│ bot:watch       │→ │ PositionService    ──────── PositionWatcher
│ bot:flatten     │→ │ OrderExecutor      ──────── DryFireGuard
│ bot:reset-day   │→ │ RiskManager + PortfolioService
│ report:daily    │→ │ Reporting (Aggregator → Sheets/Mail)
│ report:heartbeat│  └────────────────────────────────────┘
└─────────────────┘
```

### 핵심 도메인
- **MarketDataService**: 분봉 캔들 upsert, 지표 계산, `getLastPrice()` 캐시/폴백
- **SignalService (App\Services\Signals)**: 시그널 생성/소비/스킵(이유 Enum)
- **OrderExecutor**: `marketBuyByQuote`, `marketSell`, `cancel` → `ExecutionResult` 반환
- **RiskManager**: `canEnter()`로 예산/쿨다운/DD 판단, `registerFill()` 업데이트
- **PortfolioService**: `freeUsdt()`, `remainingDailyBudgetUsdt()`, `canAfford()`
- **PositionService**: 포지션/체결 기록 및 PnL 계산
- **PositionWatcher**: 오픈 포지션을 주기적으로 스캔하여 TP/SL/Timeout 청산
- **Reporting**: `LedgerAggregator` 집계 → `GoogleSheetAppender` + `MailNotifier`

---

## 실행 흐름(일일 루틴)
1. **07:30 `bot:reset-day`**  
   - 시작 잔액(`equity_start_usdt`) 기록(기본: `PortfolioService->freeUsdt()`), 리스크 카운터 초기화
2. **08:00 `bot:morning-scan`**  
   - 워치리스트 대상 분봉 캔들 스냅샷 및 인디케이터 계산
3. **08:10~22:50 `bot:minute-scan`**  
   - 시그널 평가 → `RiskManager.canEnter` + `Portfolio.canAfford` 이중 체크 후 **`marketBuyByQuote`** 진입
   - 체결 후 `PositionService.open` 및 `SignalService.markConsumed`
4. **상시 `bot:watch`(5~10초 루프)**  
   - 오픈 포지션 모니터링 → TP/SL/Timeout 청산
5. **22:50 `bot:flatten`**  
   - 모든 오픈 포지션 강제 청산(DRY=상태 종료, REAL=시장가 매도)
6. **23:00 `report:daily`**  
   - 당일 Trades/Positions 집계 → `DailyLedger` 업데이트 → Google Sheets append + 메일 발송
7. **시간별 `report:heartbeat`**  
   - free USDT, 남은 예산, 오픈 포지션 요약 출력

> 스케줄은 `routes/console.php`에 정의. `bot:watch`는 **Supervisor/PM2/systemd**로 상시 실행 권장.

---

## 설치 & 환경 설정
### 요구 사항
- PHP 8.4+, Laravel 12
- Composer

### 의존성 설치
```bash
composer install
composer require google/apiclient:^2.15
```

### .env 샘플
```dotenv
APP_ENV=local
APP_TIMEZONE=Asia/Seoul

# Upbit API (사설 요청: 주문/취소 등)
UPBIT_ACCESS_KEY=...
UPBIT_SECRET_KEY=...

# Bot 설정
BOT_ORDER_USDT=5        # 1회 기본 주문 금액(USDT)
BOT_LAST_PRICE_SNAP_GRACE_MIN=2

# DRY 가드(모의 거래)
DRY_FIRE=on             # on/off, 시간대 정책은 DryFireGuard에서 추가 제어

# Reporting
GSHEET_SPREADSHEET_ID=...
REPORT_MAIL_TO=you@example.com
REPORT_SUBJECT_PREFIX="[Daily PnL]"
REPORT_TIMEZONE=Asia/Seoul
\```

> Google Sheets: 서비스 계정에 스프레드시트 공유 권한 필요. `config/reporting.php`에서 범위/시트 범위(A:Z) 설정.

---

## 주요 콘솔 커맨드
```bash
php artisan bot:morning-scan [--symbols=BTC/USDT,ETH/USDT] [--recompute]
php artisan bot:minute-scan [--order=10]
php artisan bot:watch [--interval=5] [--jitter=2] [--once] [--max-errors=50]
php artisan bot:flatten [--symbol=BTC/USDT,ETH/USDT] [--dry]
php artisan bot:reset-day [--asset=USDT] [--force] [--note=...] [--clear-cooldowns]
php artisan report:daily [--date=YYYY-MM-DD]
php artisan report:heartbeat

# 유틸
php artisan bot:dry on|off   # DRY override 토글(설정 테이블)
```

---

## 데이터 모델(요약)
- **positions**: 심볼, 수량, 진입가, TP/SL, 상태(open/closed), opened_at/closed_at
- **trades**: position_id, side(buy/sell), price, qty, fee, executed_at
- **market_snapshots**: symbol, captured_at(분봉), price_last, volume, ema20, ema60, vol_sma20
- **daily_ledgers**: date, equity_start_usdt, equity_end_usdt, pnl_usdt, pnl_pct, trades_count, wins, losses, notes
- **signals**: symbol, triggered_at, rule_key, status(wait/consumed/skipped), reason
- **watch_list**: symbol, enabled, (선택) tick_size/step_size/min_notional 등 메타

---

## REAL/DRY 모드
- `DryFireGuard`가 **시간대 + override + env**로 3중 가드
- 주문 경로:
  - DRY: `ExecutionResult`는 `executed=true`로 간주, 원시 주문 전송 없음
  - REAL: Upbit 사설 API 호출 → 실패 시 백오프/로그

---

## 레이트 리밋 & 안정성
- 업비트는 **초당 레이트 리밋**. 응답 헤더 `Remaining-Req`로 잔량 확인
- 호출 전략
  - 시세: `getLastPrice()` 2초 캐시 + 스냅샷 2분 그레이스
  - 분봉: `snapshot()`은 심볼 묶어서 1분 1회
  - 주문/취소: 예외 처리 + 재시도(지수 백오프) 권장
- 장기 워커(`bot:watch`)는 **Supervisor/PM2/systemd**로 관리

---

## 개발 팁
- 인터페이스 기반 DI 사용: `AppServiceProvider`에서 Interface→Implementation 바인딩
- 시그널 이유 Enum: `SignalSkipReasonEnum`, `SignalConsumeReasonEnum` (사유 로깅 표준화)
- 최소 주문/호가 단위 반영: `WatchList` 메타 동기화 후 `OrderExecutor` 반올림 처리에 활용 권장
- 테스트: Risk/Portfolio/OrderExecutor(DRY)/LedgerAggregator부터 유닛테스트 추가 추천

---

## 라이선스
MIT (이 저장소의 애플리케이션 코드). Laravel 프레임워크는 [MIT license](https://opensource.org/licenses/MIT).
