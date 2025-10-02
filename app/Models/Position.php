<?php

namespace App\Models;

use App\Enums\PositionStatusEnum;
use App\Enums\TradeModeEnum;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $symbol
 * @property TradeModeEnum $mode
 * @property numeric $qty
 * @property numeric $entry_price
 * @property numeric|null $tp_price
 * @property numeric|null $sl_price
 * @property PositionStatusEnum $status
 * @property Carbon $opened_at
 * @property Carbon|null $closed_at
 * @property numeric|null $mfe
 * @property numeric|null $mae
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string|null $realized_pnl
 * @property-read Collection<int, Trade> $trades
 * @property-read int|null $trades_count
 * @property-read WatchList|null $watch
 * @method static Builder<static>|Position newModelQuery()
 * @method static Builder<static>|Position newQuery()
 * @method static Builder<static>|Position open()
 * @method static Builder<static>|Position query()
 * @method static Builder<static>|Position whereClosedAt($value)
 * @method static Builder<static>|Position whereCreatedAt($value)
 * @method static Builder<static>|Position whereEntryPrice($value)
 * @method static Builder<static>|Position whereId($value)
 * @method static Builder<static>|Position whereMae($value)
 * @method static Builder<static>|Position whereMfe($value)
 * @method static Builder<static>|Position whereMode($value)
 * @method static Builder<static>|Position whereNotes($value)
 * @method static Builder<static>|Position whereOpenedAt($value)
 * @method static Builder<static>|Position whereQty($value)
 * @method static Builder<static>|Position whereSlPrice($value)
 * @method static Builder<static>|Position whereStatus($value)
 * @method static Builder<static>|Position whereSymbol($value)
 * @method static Builder<static>|Position whereTpPrice($value)
 * @method static Builder<static>|Position whereUpdatedAt($value)
 * @mixin Eloquent
 */
class Position extends Model
{
    //
    protected $fillable = [
        'symbol', 'mode', 'qty', 'entry_price', 'tp_price', 'sl_price',
        'status', 'opened_at', 'closed_at', 'mfe', 'mae', 'notes'
    ];


    protected $casts = [
        'mode' => TradeModeEnum::class,       // REAL / DRY
        'qty' => 'decimal:8',
        'entry_price' => 'decimal:8',
        'tp_price' => 'decimal:8',
        'sl_price' => 'decimal:8',
        'status' => PositionStatusEnum::class,  // open / closed / canceled
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'mfe' => 'decimal:8',
        'mae' => 'decimal:8'
    ];

    public function trades(): HasMany
    {
        return $this->hasMany(Trade::class);
    }

    public function watch(): BelongsTo
    {
        return $this->belongsTo(WatchList::class, 'symbol', 'symbol');
    }

    // 편의 접근자: 실현 손익 추정 (단순 합)
    public function getRealizedPnlAttribute(): ?string
    {
        if (!$this->relationLoaded('trades')) return null;
        $buy = $this->trades->where('side', 'buy')->sum(fn($t) => (float)$t->price * (float)$t->qty + (float)$t->fee);
        $sell = $this->trades->where('side', 'sell')->sum(fn($t) => (float)$t->price * (float)$t->qty - (float)$t->fee);
        return number_format($sell - $buy, 8, '.', '');
    }

    // 스코프
    public function scopeOpen($q)
    {
        return $q->where('status', PositionStatusEnum::OPEN);
    }
}
