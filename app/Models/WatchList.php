<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;


/**
 * @property int $id
 * @property string $symbol
 * @property string|null $base
 * @property string|null $quote
 * @property int $priority
 * @property numeric $max_entry_usdt
 * @property numeric|null $tick_size
 * @property numeric|null $step_size
 * @property bool $enabled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property array|null $meta
 * @property-read Collection<int, Position> $positions
 * @property-read int|null $positions_count
 * @property-read Collection<int, Signal> $signals
 * @property-read int|null $signals_count
 * @property-read Collection<int, MarketSnapshot> $snapshots
 * @property-read int|null $snapshots_count
 * @method static Builder<static>|WatchList newModelQuery()
 * @method static Builder<static>|WatchList newQuery()
 * @method static Builder<static>|WatchList query()
 * @method static Builder<static>|WatchList whereBase($value)
 * @method static Builder<static>|WatchList whereCreatedAt($value)
 * @method static Builder<static>|WatchList whereEnabled($value)
 * @method static Builder<static>|WatchList whereId($value)
 * @method static Builder<static>|WatchList whereMaxEntryUsdt($value)
 * @method static Builder<static>|WatchList whereMeta($value)
 * @method static Builder<static>|WatchList wherePriority($value)
 * @method static Builder<static>|WatchList whereQuote($value)
 * @method static Builder<static>|WatchList whereStepSize($value)
 * @method static Builder<static>|WatchList whereSymbol($value)
 * @method static Builder<static>|WatchList whereTickSize($value)
 * @method static Builder<static>|WatchList whereUpdatedAt($value)
 * @mixin Eloquent
 */
class WatchList extends Model
{
    //
    protected $fillable = [
        'symbol', 'base', 'quote', 'priority', 'max_entry_usdt', 'tick_size', 'step_size', 'enabled'
    ];

    protected $casts = [
        'priority' => 'integer',
        'max_entry_usdt' => 'decimal:6',
        'tick_size' => 'decimal:8',
        'step_size' => 'decimal:8',
        'enabled' => 'boolean',
        'meta' => 'array', // JSON 컬럼
    ];

    // null로 들어온 경우 array로 동작하도록 기본값
    protected $attributes = [
        'meta' => '[]',
    ];

    // symbol 기반 연관 (FK는 아니지만 편의상 로컬키=심볼로 연결)
    public function snapshots(): HasMany
    {
        return $this->hasMany(MarketSnapshot::class, 'symbol', 'symbol');
    }

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class, 'symbol', 'symbol');
    }

    public function signals(): HasMany
    {
        return $this->hasMany(Signal::class, 'symbol', 'symbol');
    }
}
