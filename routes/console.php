<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// === 스케줄 정의 ===
$tz = 'Asia/Seoul';

// [아침 준비]
Schedule::command('bot:morning-scan')->dailyAt('08:00')->timezone($tz);
Schedule::command('bot:build-watch-list')->dailyAt('08:05')->timezone($tz);

// [주간 판단 루틴] 08:10~22:50 매분
Schedule::command('bot:minute-scan')->everyMinute()
    ->between('08:10', '22:50')->timezone($tz);

// [야간: DRY 느슨 주기] 22:50~07:30 5분 간격
Schedule::command('bot:minute-scan')->everyFiveMinutes()
    ->between('22:50', '23:59')->timezone($tz);
Schedule::command('bot:minute-scan')->everyFiveMinutes()
    ->between('00:00', '07:30')->timezone($tz);

// [강제 마감] 야간 포지션 금지
Schedule::command('bot:flatten')->dailyAt('22:50')->timezone($tz);

// [일일 리셋] 실거래 복귀/한도 초기화
Schedule::command('bot:reset-day')->dailyAt('07:30')->timezone($tz);

// [리포트] 일일 마감 메일/시트
Schedule::command('report:daily')->dailyAt('23:00')->timezone($tz);

// (선택) 시간별 하트비트
Schedule::command('report:heartbeat')->hourly()
    ->between('09:00', '22:00')->timezone($tz);


// === (옵션) 클로저 기반 유틸 커맨드 ===
// DRY FIRE 수동 토글: php artisan bot:dry on|off
Artisan::command('bot:dry {state : on|off}', function (string $state) {
    $state = strtolower($state);
    abort_unless(in_array($state, ['on', 'off'], true), 1, 'state must be on|off');
    DB::table('settings')->updateOrInsert(['key' => 'dry_fire_override'], ['value' => $state]);
    $this->info("Dry fire override => {$state}");
})->purpose('Toggle dry-fire override (on/off)');
