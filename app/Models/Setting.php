<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $key
 * @property string|null $value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @method static Builder<static>|Setting newModelQuery()
 * @method static Builder<static>|Setting newQuery()
 * @method static Builder<static>|Setting query()
 * @method static Builder<static>|Setting whereCreatedAt($value)
 * @method static Builder<static>|Setting whereId($value)
 * @method static Builder<static>|Setting whereKey($value)
 * @method static Builder<static>|Setting whereUpdatedAt($value)
 * @method static Builder<static>|Setting whereValue($value)
 * @mixin Eloquent
 */
class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    // 편의 메소드: 값 가져오기 (문자/숫자/불리언 자동 파싱 예시)
    public function getTypedValue(): mixed
    {
        $v = $this->value;
        if (is_numeric($v)) return $v + 0;
        if (in_array(strtolower((string)$v), ['true', 'false', '1', '0'], true)) return filter_var($v, FILTER_VALIDATE_BOOLEAN);
        return $v;
    }
}
