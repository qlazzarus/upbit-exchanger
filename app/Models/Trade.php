<?php

namespace App\Models;

use App\Enums\TradeModeEnum;
use App\Enums\TradeSideEnum;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $position_id
 * @property string $symbol
 * @property TradeModeEnum $mode
 * @property TradeSideEnum $side
 * @property numeric $price
 * @property numeric $qty
 * @property numeric $fee
 * @property Carbon $executed_at
 * @property string|null $provider
 * @property string|null $external_order_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Position $position
 * @property-read WatchList|null $watch
 * @method static Builder<static>|Trade newModelQuery()
 * @method static Builder<static>|Trade newQuery()
 * @method static Builder<static>|Trade query()
 * @method static Builder<static>|Trade whereCreatedAt($value)
 * @method static Builder<static>|Trade whereExecutedAt($value)
 * @method static Builder<static>|Trade whereExternalOrderId($value)
 * @method static Builder<static>|Trade whereFee($value)
 * @method static Builder<static>|Trade whereId($value)
 * @method static Builder<static>|Trade whereMode($value)
 * @method static Builder<static>|Trade wherePositionId($value)
 * @method static Builder<static>|Trade wherePrice($value)
 * @method static Builder<static>|Trade whereProvider($value)
 * @method static Builder<static>|Trade whereQty($value)
 * @method static Builder<static>|Trade whereSide($value)
 * @method static Builder<static>|Trade whereSymbol($value)
 * @method static Builder<static>|Trade whereUpdatedAt($value)
 * @mixin Eloquent
 */
class Trade extends Model
{
    //
    protected $fillable = [
        'position_id', 'symbol', 'mode', 'side', 'price', 'qty', 'fee', 'executed_at', 'provider', 'external_order_id'
    ];

    protected $casts = [
        'mode' => TradeModeEnum::class,   // REAL / DRY
        'side' => TradeSideEnum::class,   // buy / sell
        'price' => 'decimal:8',
        'qty' => 'decimal:8',
        'fee' => 'decimal:8',
        'executed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function watch(): BelongsTo
    {
        return $this->belongsTo(WatchList::class, 'symbol', 'symbol');
    }
}
