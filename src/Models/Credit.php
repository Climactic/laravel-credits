<?php

namespace Climactic\Credits\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Credit extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setTable(config('credits.table_name', 'credits'));
    }

    protected $fillable = [
        'amount',
        'description',
        'type',
        'metadata',
        'running_balance',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:' . config('credits.decimal_precision', 2),
            'running_balance' => 'decimal:' . config('credits.decimal_precision', 2),
            'metadata' => 'array',
        ];
    }

    public function creditable(): MorphTo
    {
        return $this->morphTo();
    }
}
