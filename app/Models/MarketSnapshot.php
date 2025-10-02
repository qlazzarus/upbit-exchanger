<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $symbol
 * @property Carbon $captured_at
 * @property numeric $price_last
 * @property numeric|null $volume
 * @property numeric|null $ema20
 * @property numeric|null $ema60
 * @property numeric|null $vol_sma20
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read WatchList|null $watch
 * @method static Builder<static>|MarketSnapshot newModelQuery()
 * @method static Builder<static>|MarketSnapshot newQuery()
 * @method static Builder<static>|MarketSnapshot query()
 * @method static Builder<static>|MarketSnapshot whereCapturedAt($value)
 * @method static Builder<static>|MarketSnapshot whereCreatedAt($value)
 * @method static Builder<static>|MarketSnapshot whereEma20($value)
 * @method static Builder<static>|MarketSnapshot whereEma60($value)
 * @method static Builder<static>|MarketSnapshot whereId($value)
 * @method static Builder<static>|MarketSnapshot wherePriceLast($value)
 * @method static Builder<static>|MarketSnapshot whereSymbol($value)
 * @method static Builder<static>|MarketSnapshot whereUpdatedAt($value)
 * @method static Builder<static>|MarketSnapshot whereVolSma20($value)
 * @method static Builder<static>|MarketSnapshot whereVolume($value)
 * @mixin Eloquent
 */
class MarketSnapshot extends Model
{
    //
    protected $fillable = [
        'symbol', 'captured_at', 'price_last', 'volume', 'ema20', 'ema60', 'vol_sma20'
    ];

    protected $casts = [
        'captured_at' => 'datetime',
        'price_last' => 'decimal:8',
        'volume' => 'decimal:8',
        'ema20' => 'decimal:8',
        'ema60' => 'decimal:8',
        'vol_sma20' => 'decimal:8',
    ];

    public function watch(): BelongsTo
    {
        return $this->belongsTo(WatchList::class, 'symbol', 'symbol');
    }
}
