<?php
return [
    'timezone' => 'Asia/Seoul',
    'sheets' => [
        'credentials' => storage_path('app/google/service-account.json'),
        'spreadsheet_id' => env('GSHEET_SPREADSHEET_ID'),
        'summary_range' => 'Daily Summary!A:Z',
        'trades_range' => 'Trade Log!A:Z',
    ],
    // return 배열 안 어딘가에 추가
    'summary' => [
        'target_pct' => (float) env('REPORT_TARGET_PCT', 0),
    ],
    'mail' => [
        'to' => env('REPORT_MAIL_TO'),
        'subject_prefix' => '[Daily PnL]',
    ],
];
