<?php

namespace Climactic\Credits\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Credit extends Model
{
    protected $fillable = [
        'amount',
        'description',
        'type',
        'metadata',
        'running_balance',
        'creditable_type',
        'creditable_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'running_balance' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setTable(config('credits.table_name', 'credits'));
    }

    public function creditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Query credits where metadata key matches a value.
     *
     * Supports dot notation for nested keys (e.g., 'user.id', 'items.0.name').
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $key  Metadata key (supports dot notation)
     * @param  mixed  $operator  Comparison operator or value if no operator
     * @param  mixed  $value  Value to compare (optional if operator is value)
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWhereMetadata($query, string $key, $operator = null, $value = null)
    {
        // Handle two-parameter syntax: whereMetadata('key', 'value')
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        return $query->where("metadata->{$key}", $operator, $value);
    }

    /**
     * Query credits where metadata contains a value.
     *
     * For arrays: checks if value exists in array.
     * For objects: checks if key/value pair exists.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $key  Metadata key (supports dot notation)
     * @param  mixed  $value  Value to search for
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWhereMetadataContains($query, string $key, $value)
    {
        return $query->whereJsonContains("metadata->{$key}", $value);
    }

    /**
     * Query credits where metadata key exists (not null).
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $key  Metadata key (supports dot notation)
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWhereMetadataHas($query, string $key)
    {
        return $query->whereNotNull("metadata->{$key}");
    }

    /**
     * Query credits where metadata key is null or doesn't exist.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $key  Metadata key (supports dot notation)
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWhereMetadataNull($query, string $key)
    {
        return $query->whereNull("metadata->{$key}");
    }

    /**
     * Query credits where metadata JSON length matches condition.
     *
     * Useful for arrays: whereMetadataLength('items', '>', 5)
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $key  Metadata key (supports dot notation)
     * @param  mixed  $operator  Comparison operator
     * @param  mixed  $value  Length to compare
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWhereMetadataLength($query, string $key, $operator = null, $value = null)
    {
        // Handle two-parameter syntax
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        return $query->whereJsonLength("metadata->{$key}", $operator, $value);
    }
}
