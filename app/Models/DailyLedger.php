<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;


/**
 * @property int $id
 * @property Carbon $date
 * @property numeric|null $equity_start_usdt
 * @property numeric|null $equity_end_usdt
 * @property numeric|null $pnl_usdt
 * @property numeric|null $pnl_pct
 * @property int $wins
 * @property int $losses
 * @property bool $trading_halt
 * @property string|null $halt_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int $trades_count
 * @property string|null $notes
 * @property-read float|null $win_rate
 * @method static Builder<static>|DailyLedger newModelQuery()
 * @method static Builder<static>|DailyLedger newQuery()
 * @method static Builder<static>|DailyLedger query()
 * @method static Builder<static>|DailyLedger whereCreatedAt($value)
 * @method static Builder<static>|DailyLedger whereDate($value)
 * @method static Builder<static>|DailyLedger whereEquityEndUsdt($value)
 * @method static Builder<static>|DailyLedger whereEquityStartUsdt($value)
 * @method static Builder<static>|DailyLedger whereHaltReason($value)
 * @method static Builder<static>|DailyLedger whereId($value)
 * @method static Builder<static>|DailyLedger whereLosses($value)
 * @method static Builder<static>|DailyLedger whereNotes($value)
 * @method static Builder<static>|DailyLedger wherePnlPct($value)
 * @method static Builder<static>|DailyLedger wherePnlUsdt($value)
 * @method static Builder<static>|DailyLedger whereTradesCount($value)
 * @method static Builder<static>|DailyLedger whereTradingHalt($value)
 * @method static Builder<static>|DailyLedger whereUpdatedAt($value)
 * @method static Builder<static>|DailyLedger whereWins($value)
 * @mixin Eloquent
 */
class DailyLedger extends Model
{
    //
    protected $fillable = [
        'date', 'equity_start_usdt', 'equity_end_usdt', 'pnl_usdt', 'pnl_pct',
        'trades_count', 'wins', 'losses', 'trading_halt', 'halt_reason', 'notes'
    ];

    protected $casts = [
        'date' => 'date',
        'equity_start_usdt' => 'decimal:8',
        'equity_end_usdt' => 'decimal:8',
        'pnl_usdt' => 'decimal:8',
        'pnl_pct' => 'decimal:6',
        'trades_count' => 'integer',
        'wins' => 'integer',
        'losses' => 'integer',
        'trading_halt' => 'boolean',
        'notes' => 'string',
    ];


    // 집계 편의
    public function getWinRateAttribute(): ?float
    {
        $t = $this->wins + $this->losses;
        if ($t > 0) {
            $rate = (float)$this->wins / (float)$t;
            return round($rate, 4);
        }
        return null;
    }
}
