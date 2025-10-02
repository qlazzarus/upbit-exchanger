<?php

namespace App\Services\Reporting;

use App\Models\DailyLedger;
use Google\Service\Exception;
use Google\Service\Sheets;
use Google\Service\Sheets\ValueRange;
use Illuminate\Support\Facades\Log;

/**
 * Google Sheets API 클라이언트가 주입되었을 때만 동작.
 * 없으면 경고 로그만 남기고 조용히 패스 (운영 환경에서 의존성 주입 권장).
 */
class GoogleSheetAppender implements SheetAppenderInterface
{
    private ?Sheets $sheets = null;
    private string $tz = 'Asia/Seoul';

    public function __construct(
        ?Sheets         $sheets = null,
        private ?string $spreadsheetId = null,
        private string  $summaryRange = 'Daily Summary!A:Z',
        private string  $tradesRange = 'Trade Log!A:Z',
        string          $tz = 'Asia/Seoul',
    )
    {
        $this->tz = $tz;
        $this->sheets = $sheets;
        $cfg = config('reporting.sheets', []);
        $this->spreadsheetId = $this->spreadsheetId ?: ($cfg['spreadsheet_id'] ?? null);
        $this->summaryRange = $cfg['summary_range'] ?? $this->summaryRange;
        $this->tradesRange = $cfg['trades_range'] ?? $this->tradesRange;
    }

    /**
     * @throws Exception
     */
    public function appendDailySummary(DailyLedger $ledger): void
    {
        if (!$this->sheets || !$this->spreadsheetId) {
            Log::warning('[GoogleSheetAppender] Sheets client or spreadsheetId missing');
            return;
        }
        // Google Sheets API는 배열(JSON array)만 허용하므로
        // 모든 값을 스칼라 문자열/숫자로 변환하고 순차 인덱스를 강제한다.
        $dateStr = \Illuminate\Support\Carbon::parse($ledger->date, $this->tz)->toDateString();
        $nowStr  = now($this->tz)->toDateTimeString();

        $targetPct = (float) (config('reporting.summary.target_pct', 0));
        $usedUsdt  = ''; // TODO: 당일 사용 금액(체결 집계) 산출 시 교체
        $notes     = (string) ($ledger->notes ?? '');

        $row = [
            (string) $dateStr,                                        // A 날짜
            is_null($ledger->equity_start_usdt) ? '' : (float) $ledger->equity_start_usdt, // B 시작 자본
            $usedUsdt,                                                // C 사용 금액 (보류)
            $targetPct,                                               // D 목표 수익률
            is_null($ledger->pnl_pct) ? '' : (float) $ledger->pnl_pct, // E 실제 수익률
            is_null($ledger->equity_end_usdt) ? '' : (float) $ledger->equity_end_usdt, // F 종료 자본
            $notes,                                                   // G 비고
            (int) ($ledger->trades_count ?? 0),                       // H 거래(수)
            (string) $nowStr,                                         // I 타임스탬프
        ];

        // 배열 인덱스 재정렬(연관 배열→순차 배열 방지)
        $row = array_values($row);
        $values = [ $row ];

        $body = new ValueRange([
            'range' => $this->summaryRange,
            'majorDimension' => 'ROWS',
            'values' => $values,
        ]);

        $params = [
            'valueInputOption' => 'USER_ENTERED',
            'insertDataOption' => 'INSERT_ROWS',
        ];

        $this->sheets->spreadsheets_values->append(
            $this->spreadsheetId,
            $this->summaryRange,
            $body,
            $params
        );
    }


    public function appendTradeLog(DailyLedger $ledger): void
    {
        // 필요 시 구현: ledger->date 기준으로 trades를 조회해 상세 로그를 시트에 Append
        // 인터페이스는 유지하고, 우선은 Daily Summary만 사용해도 OK.
    }
}
