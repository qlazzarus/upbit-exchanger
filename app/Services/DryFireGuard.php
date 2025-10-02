<?php

namespace App\Services;

use App\Models\Setting;
use Carbon\Carbon;
use Config;

class DryFireGuard
{
    public function active(): bool
    {
        $override = optional(Setting::where('key', 'dry_fire_override')->first())->value;
        if ($override === 'on') return true;
        if ($override === 'off') {/* fall through */
        }

        if (Config::get('bot.dry_fire', false)) return true;

        $now = Carbon::now('Asia/Seoul');
        $inNight = $now->betweenIncluded($now->copy()->setTime(23, 0), $now->copy()->setTime(23, 59, 59))
            || $now->betweenIncluded($now->copy()->setTime(0, 0), $now->copy()->setTime(7, 30));

        return $inNight;
    }
}
