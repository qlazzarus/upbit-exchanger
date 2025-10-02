<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('settings')->updateOrInsert(['key' => 'dry_fire_override'], ['value' => 'off']);
        DB::table('settings')->updateOrInsert(['key' => 'daily_budget_usdt'], ['value' => '10']);
        DB::table('settings')->updateOrInsert(['key' => 'take_profit_pct'], ['value' => '1']);  // %
        DB::table('settings')->updateOrInsert(['key' => 'stop_loss_pct'], ['value' => '1']);    // %
        DB::table('settings')->updateOrInsert(['key' => 'daily_drawdown_stop_pct'], ['value' => '2']); // %
    }
}
