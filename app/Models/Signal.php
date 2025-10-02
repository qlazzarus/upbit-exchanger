<?php

namespace App\Models;

use App\Enums\SignalStatusEnum;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $symbol
 * @property Carbon $triggered_at
 * @property string $rule_key
 * @property numeric $confidence
 * @property SignalStatusEnum $status
 * @property string|null $reason
 * @property numeric|null $ref_price
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read WatchList|null $watch
 * @method static Builder<static>|Signal newModelQuery()
 * @method static Builder<static>|Signal newQuery()
 * @method static Builder<static>|Signal query()
 * @method static Builder<static>|Signal waiting()
 * @method static Builder<static>|Signal whereConfidence($value)
 * @method static Builder<static>|Signal whereCreatedAt($value)
 * @method static Builder<static>|Signal whereId($value)
 * @method static Builder<static>|Signal whereReason($value)
 * @method static Builder<static>|Signal whereRefPrice($value)
 * @method static Builder<static>|Signal whereRuleKey($value)
 * @method static Builder<static>|Signal whereStatus($value)
 * @method static Builder<static>|Signal whereSymbol($value)
 * @method static Builder<static>|Signal whereTriggeredAt($value)
 * @method static Builder<static>|Signal whereUpdatedAt($value)
 * @mixin Eloquent
 */
class Signal extends Model
{
    //
    protected $fillable = [
        'symbol', 'triggered_at', 'rule_key', 'confidence', 'status', 'reason', 'ref_price'
    ];

    protected $casts = [
        'triggered_at' => 'datetime',
        'confidence' => 'decimal:4',
        'ref_price' => 'decimal:8',
        'status' => SignalStatusEnum::class, // enum cast
    ];

    public function watch(): BelongsTo
    {
        return $this->belongsTo(WatchList::class, 'symbol', 'symbol');
    }

    // 유용한 스코프
    public function scopeWaiting($q)
    {
        return $q->where('status', SignalStatusEnum::WAITING);
    }

}
