<?php

namespace Climactic\Credits\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int|string $id
 * @property float $amount
 * @property string|null $description
 * @property string $type
 * @property array<string, mixed>|null $metadata
 * @property float $running_balance
 * @property string $creditable_type
 * @property int|string $creditable_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Model $creditable
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
    public function scopeWhereMetadata(Builder $query, string $key, $operator = null, $value = null): Builder
    {
        $key = $this->validateMetadataKey($key);

        // Handle two-parameter syntax: whereMetadata('key', 'value')
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        return $this->applyMetadataComparison($query, $key, $operator, $value, 'and');
    }

    /**
     * Apply a metadata comparison to the query.
     *
     * Extracting a JSON value yields text, which makes an ordering comparison
     * against a number mean different things per driver: PostgreSQL compares
     * lexicographically ('100' sorts below '50'), while SQLite ranks any text
     * above any number. Cast the value so the comparison means what it reads
     * like, and exclude rows whose value is not a number at all.
     *
     * @param  string  $key  Metadata key, already validated and in JSON path form
     * @param  mixed  $operator  Comparison operator
     * @param  mixed  $value  Value to compare
     * @param  string  $boolean  'and' or 'or'
     */
    protected function applyMetadataComparison(Builder $query, string $key, $operator, $value, string $boolean): Builder
    {
        $column = "metadata->{$key}";
        $driver = $query->getModel()->getConnection()->getDriverName();

        $comparable = is_numeric($value)
            && in_array($operator, ['<', '<=', '>', '>='], true)
            && in_array($driver, ['pgsql', 'sqlite'], true);

        if (! $comparable) {
            return $query->where($column, $operator, $value, $boolean);
        }

        $wrapped = $query->getQuery()->getGrammar()->wrap($column);

        // A CASE guard rather than a bare cast: non-numeric values yield NULL
        // and simply do not match, instead of aborting the query (PostgreSQL)
        // or being ranked above every number (SQLite).
        // SQLite has no regex, so the equivalent of PostgreSQL's anchored
        // pattern is two globs: starts like a number, and contains nothing that
        // is not part of one. Without the second, '12abc' would cast to 12.
        $cast = $driver === 'pgsql'
            ? sprintf("case when %s ~ '%s' then (%s)::numeric end", $wrapped, '^-?[0-9]+(\.[0-9]+)?$', $wrapped)
            : sprintf("case when %s glob '%s' and %s not glob '%s' then cast(%s as real) end", $wrapped, '[-0-9]*', $wrapped, '*[^0-9.-]*', $wrapped);

        return $query->whereRaw("({$cast}) {$operator} ?", [$value], $boolean);
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
    public function scopeWhereMetadataContains(Builder $query, string $key, $value): Builder
    {
        $key = $this->validateMetadataKey($key);

        return $query->whereJsonContains("metadata->{$key}", $value);
    }

    /**
     * Query credits where metadata key exists (not null).
     *
     * @param  string  $key  Metadata key (supports dot notation)
     */
    public function scopeWhereMetadataHas(Builder $query, string $key): Builder
    {
        $key = $this->validateMetadataKey($key);

        return $query->whereNotNull("metadata->{$key}");
    }

    /**
     * Query credits where metadata key is null or doesn't exist.
     *
     * @param  string  $key  Metadata key (supports dot notation)
     */
    public function scopeWhereMetadataNull(Builder $query, string $key): Builder
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
    public function scopeWhereMetadataLength(Builder $query, string $key, $operator = null, $value = null): Builder
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
    public function scopeOrWhereMetadata(Builder $query, string $key, $operator = null, $value = null): Builder
    {
        $key = $this->validateMetadataKey($key);

        // Handle two-parameter syntax: orWhereMetadata('key', 'value')
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        return $this->applyMetadataComparison($query, $key, $operator, $value, 'or');
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
    public function scopeOrWhereMetadataContains(Builder $query, string $key, $value): Builder
    {
        $key = $this->validateMetadataKey($key);

        return $query->orWhereJsonContains("metadata->{$key}", $value);
    }

    /**
     * Query credits where metadata key exists (not null) (OR condition).
     *
     * @param  string  $key  Metadata key (supports dot notation)
     */
    public function scopeOrWhereMetadataHas(Builder $query, string $key): Builder
    {
        $key = $this->validateMetadataKey($key);

        return $query->orWhereNotNull("metadata->{$key}");
    }

    /**
     * Query credits where metadata key is null or doesn't exist (OR condition).
     *
     * @param  string  $key  Metadata key (supports dot notation)
     */
    public function scopeOrWhereMetadataNull(Builder $query, string $key): Builder
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
    public function scopeOrWhereMetadataLength(Builder $query, string $key, $operator = null, $value = null): Builder
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
