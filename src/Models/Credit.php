<?php

namespace Climactic\Credits\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int|string $id
 * @property float $amount
 * @property string|null $description
 * @property string $type
 * @property array<string, mixed>|null $metadata
 * @property float $running_balance
 * @property string $creditable_type
 * @property int|string $creditable_id
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Model $creditable
 */
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
     * Validate and sanitize a metadata key for use in JSON path queries.
     *
     * Converts dot notation (user.id) to Laravel's JSON path syntax (user->id).
     *
     * @param  string  $key  Metadata key to validate
     * @return string Sanitized key safe for use in JSON paths
     *
     * @throws \InvalidArgumentException If key is invalid
     */
    protected function validateMetadataKey(string $key): string
    {
        // Trim whitespace
        $key = trim($key);

        // Reject empty keys
        if ($key === '') {
            throw new \InvalidArgumentException('Metadata key cannot be empty.');
        }

        // Reject keys with Laravel's JSON path arrow operator (should use dot notation)
        if (str_contains($key, '->')) {
            throw new \InvalidArgumentException('Metadata key cannot contain "->". Use dot notation instead (e.g., "user.id").');
        }

        // Reject keys with quotes that could break JSON path syntax
        if (str_contains($key, '"') || str_contains($key, "'")) {
            throw new \InvalidArgumentException('Metadata key cannot contain quotes.');
        }

        // Convert dot notation to Laravel's JSON path arrow notation
        // e.g., 'user.id' becomes 'user->id'
        $key = str_replace('.', '->', $key);

        return $key;
    }

    /**
     * Query credits where metadata key matches a value.
     *
     * Supports dot notation for nested keys (e.g., 'user.id', 'items.0.name').
     *
     * @param  string  $key  Metadata key (supports dot notation)
     * @param  mixed  $operator  Comparison operator or value if no operator
     * @param  mixed  $value  Value to compare (optional if operator is value)
     */
    public function scopeWhereMetadata(\Illuminate\Database\Eloquent\Builder $query, string $key, $operator = null, $value = null): \Illuminate\Database\Eloquent\Builder
    {
        $key = $this->validateMetadataKey($key);

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
     * @param  string  $key  Metadata key (supports dot notation)
     * @param  mixed  $value  Value to search for
     */
    public function scopeWhereMetadataContains(\Illuminate\Database\Eloquent\Builder $query, string $key, $value): \Illuminate\Database\Eloquent\Builder
    {
        $key = $this->validateMetadataKey($key);

        return $query->whereJsonContains("metadata->{$key}", $value);
    }

    /**
     * Query credits where metadata key exists (not null).
     *
     * @param  string  $key  Metadata key (supports dot notation)
     */
    public function scopeWhereMetadataHas(\Illuminate\Database\Eloquent\Builder $query, string $key): \Illuminate\Database\Eloquent\Builder
    {
        $key = $this->validateMetadataKey($key);

        return $query->whereNotNull("metadata->{$key}");
    }

    /**
     * Query credits where metadata key is null or doesn't exist.
     *
     * @param  string  $key  Metadata key (supports dot notation)
     */
    public function scopeWhereMetadataNull(\Illuminate\Database\Eloquent\Builder $query, string $key): \Illuminate\Database\Eloquent\Builder
    {
        $key = $this->validateMetadataKey($key);

        return $query->whereNull("metadata->{$key}");
    }

    /**
     * Query credits where metadata JSON length matches condition.
     *
     * Useful for arrays: whereMetadataLength('items', '>', 5)
     *
     * @param  string  $key  Metadata key (supports dot notation)
     * @param  mixed  $operator  Comparison operator
     * @param  mixed  $value  Length to compare
     */
    public function scopeWhereMetadataLength(\Illuminate\Database\Eloquent\Builder $query, string $key, $operator = null, $value = null): \Illuminate\Database\Eloquent\Builder
    {
        $key = $this->validateMetadataKey($key);

        // Handle two-parameter syntax
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        return $query->whereJsonLength("metadata->{$key}", $operator, $value);
    }

    /**
     * Query credits where metadata key matches a value (OR condition).
     *
     * Supports dot notation for nested keys (e.g., 'user.id', 'items.0.name').
     *
     * @param  string  $key  Metadata key (supports dot notation)
     * @param  mixed  $operator  Comparison operator or value if no operator
     * @param  mixed  $value  Value to compare (optional if operator is value)
     */
    public function scopeOrWhereMetadata(\Illuminate\Database\Eloquent\Builder $query, string $key, $operator = null, $value = null): \Illuminate\Database\Eloquent\Builder
    {
        $key = $this->validateMetadataKey($key);

        // Handle two-parameter syntax: orWhereMetadata('key', 'value')
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        return $query->orWhere("metadata->{$key}", $operator, $value);
    }

    /**
     * Query credits where metadata contains a value (OR condition).
     *
     * For arrays: checks if value exists in array.
     * For objects: checks if key/value pair exists.
     *
     * @param  string  $key  Metadata key (supports dot notation)
     * @param  mixed  $value  Value to search for
     */
    public function scopeOrWhereMetadataContains(\Illuminate\Database\Eloquent\Builder $query, string $key, $value): \Illuminate\Database\Eloquent\Builder
    {
        $key = $this->validateMetadataKey($key);

        return $query->orWhereJsonContains("metadata->{$key}", $value);
    }

    /**
     * Query credits where metadata key exists (not null) (OR condition).
     *
     * @param  string  $key  Metadata key (supports dot notation)
     */
    public function scopeOrWhereMetadataHas(\Illuminate\Database\Eloquent\Builder $query, string $key): \Illuminate\Database\Eloquent\Builder
    {
        $key = $this->validateMetadataKey($key);

        return $query->orWhereNotNull("metadata->{$key}");
    }

    /**
     * Query credits where metadata key is null or doesn't exist (OR condition).
     *
     * @param  string  $key  Metadata key (supports dot notation)
     */
    public function scopeOrWhereMetadataNull(\Illuminate\Database\Eloquent\Builder $query, string $key): \Illuminate\Database\Eloquent\Builder
    {
        $key = $this->validateMetadataKey($key);

        return $query->orWhereNull("metadata->{$key}");
    }

    /**
     * Query credits where metadata JSON length matches condition (OR condition).
     *
     * Useful for arrays: orWhereMetadataLength('items', '>', 5)
     *
     * @param  string  $key  Metadata key (supports dot notation)
     * @param  mixed  $operator  Comparison operator
     * @param  mixed  $value  Length to compare
     */
    public function scopeOrWhereMetadataLength(\Illuminate\Database\Eloquent\Builder $query, string $key, $operator = null, $value = null): \Illuminate\Database\Eloquent\Builder
    {
        $key = $this->validateMetadataKey($key);

        // Handle two-parameter syntax
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        return $query->orWhereJsonLength("metadata->{$key}", $operator, $value);
    }
}
