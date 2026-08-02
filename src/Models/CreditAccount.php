<?php

namespace Climactic\Credits\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * The per-creditable account row.
 *
 * There is exactly one row per creditable and it is never deleted or replaced,
 * which makes it a stable target for `SELECT ... FOR UPDATE`. Serializing every
 * balance mutation on this row is what prevents two concurrent writers from
 * computing their new balance off the same stale value.
 *
 * @property int|string $id
 * @property string $creditable_type
 * @property int|string $creditable_id
 * @property float $balance
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Model $creditable
 */
class CreditAccount extends Model
{
    protected $fillable = [
        'creditable_type',
        'creditable_id',
        'balance',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setTable(config('credits.accounts_table_name', 'credit_accounts'));
    }

    public function creditable(): MorphTo
    {
        return $this->morphTo();
    }
}
