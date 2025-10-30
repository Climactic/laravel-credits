<?php

namespace Climactic\Credits\Traits;

use Climactic\Credits\Events\CreditsAdded;
use Climactic\Credits\Events\CreditsDeducted;
use Climactic\Credits\Events\CreditsTransferred;
use Climactic\Credits\Exceptions\InsufficientCreditsException;
use Climactic\Credits\Models\Credit;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

trait HasCredits
{
    /**
     * Get all credit transactions for this model.
     */
    public function credits(): MorphMany
    {
        return $this->morphMany(Credit::class, 'creditable');
    }

    /**
     * Get all credit transactions for this model.
     *
     * @deprecated Use credits() instead. Will be removed in v2.0
     */
    public function creditTransactions(): MorphMany
    {
        trigger_error('Method creditTransactions() is deprecated. Use credits() instead.', E_USER_DEPRECATED);

        return $this->credits();
    }

    /**
     * Create a credit transaction for the model, update its running balance, and dispatch a CreditsAdded event.
     *
     * @param  float  $amount  The amount to add; must be greater than 0.
     * @param  string|null  $description  Optional human-readable description for the transaction.
     * @param  array  $metadata  Optional arbitrary metadata stored with the transaction.
     * @return \Climactic\Credits\Models\Credit The created Credit record with the updated running balance.
     *
     * @throws \InvalidArgumentException If $amount is not greater than 0.
     */
    public function creditAdd(float $amount, ?string $description = null, array $metadata = []): Credit
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be greater than 0.');
        }

        return DB::transaction(function () use ($amount, $description, $metadata) {
            $lastBalance = (float) ($this->credits()
                ->lockForUpdate()
                ->latest('id')
                ->value('running_balance') ?? 0.0);
            $newBalance = $lastBalance + $amount;

            $credit = $this->credits()->create([
                'amount' => $amount,
                'description' => $description,
                'type' => 'credit',
                'metadata' => $metadata,
                'running_balance' => $newBalance,
            ]);

            event(new CreditsAdded(
                creditable: $this,
                transactionId: $credit->id,
                amount: $amount,
                newBalance: $newBalance,
                description: $description,
                metadata: $metadata
            ));

            return $credit;
        }, 5); // Retry up to 5 times on deadlock/transient errors
    }

    /**
     * Add credits to the model.
     *
     * @deprecated Use creditAdd() instead. Will be removed in v2.0
     */
    public function addCredits(float $amount, ?string $description = null, array $metadata = []): Credit
    {
        trigger_error('Method addCredits() is deprecated. Use creditAdd() instead.', E_USER_DEPRECATED);

        return $this->creditAdd($amount, $description, $metadata);
    }

    /**
     * Deducts credits from the model and records a debit transaction.
     *
     * @param  float  $amount  The amount to deduct; must be greater than 0.
     * @param  string|null  $description  Optional description for the transaction.
     * @param  array  $metadata  Optional metadata to attach to the transaction.
     * @return Credit The created Credit record representing the debit and its resulting running balance.
     *
     * @throws \InvalidArgumentException If $amount is not greater than 0.
     * @throws InsufficientCreditsException If negative balances are disallowed and the model has insufficient credits.
     */
    public function creditDeduct(float $amount, ?string $description = null, array $metadata = []): Credit
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be greater than 0.');
        }

        return DB::transaction(function () use ($amount, $description, $metadata) {
            $lastBalance = (float) ($this->credits()
                ->lockForUpdate()
                ->latest('id')
                ->value('running_balance') ?? 0.0);
            $newBalance = $lastBalance - $amount;

            if (! config('credits.allow_negative_balance') && $newBalance < 0) {
                throw new InsufficientCreditsException($amount, $lastBalance);
            }

            $credit = $this->credits()->create([
                'amount' => $amount,
                'description' => $description,
                'type' => 'debit',
                'metadata' => $metadata,
                'running_balance' => $newBalance,
            ]);

            event(new CreditsDeducted(
                creditable: $this,
                transactionId: $credit->id,
                amount: $amount,
                newBalance: $newBalance,
                description: $description,
                metadata: $metadata
            ));

            return $credit;
        }, 5); // Retry up to 5 times on deadlock/transient errors
    }

    /**
     * Deduct credits from the model.
     *
     * @deprecated Use creditDeduct() instead. Will be removed in v2.0
     */
    public function deductCredits(float $amount, ?string $description = null, array $metadata = []): Credit
    {
        trigger_error('Method deductCredits() is deprecated. Use creditDeduct() instead.', E_USER_DEPRECATED);

        return $this->creditDeduct($amount, $description, $metadata);
    }

    /**
     * Retrieve the model's current credit balance.
     *
     * @return float The most recent `running_balance` as a float, or 0.0 if no transactions exist.
     */
    public function creditBalance(): float
    {
        // Use latest by ID to ensure correct order even with same timestamps
        return (float) ($this->credits()
            ->latest('id')
            ->value('running_balance') ?? 0.0);
    }

    /**
     * Get the current balance of the model.
     *
     * @deprecated Use creditBalance() instead. Will be removed in v2.0
     */
    public function getCurrentBalance(): float
    {
        trigger_error('Method getCurrentBalance() is deprecated. Use creditBalance() instead.', E_USER_DEPRECATED);

        return $this->creditBalance();
    }

    /**
     * Transfer credits from this model to another model.
     *
     * The transfer is executed inside a database transaction with deterministic
     * row locking and retry (up to 5 attempts) to prevent deadlocks when concurrent
     * transfers occur in opposite directions.
     *
     * @param  self  $recipient  The model receiving the credits.
     * @param  float  $amount  The amount of credits to transfer (must be greater than zero).
     * @param  string|null  $description  Optional human-readable description for the transaction.
     * @param  array  $metadata  Optional arbitrary metadata to attach to the transaction.
     * @return array{
     *     sender_balance: float,
     *     recipient_balance: float
     * } Associative array containing the sender's and recipient's balances after the transfer.
     */
    public function creditTransfer(self $recipient, float $amount, ?string $description = null, array $metadata = []): array
    {
        $result = [];

        $lastTransaction = DB::transaction(function () use ($recipient, $amount, $description, $metadata, &$result) {
            // Pre-lock both models in deterministic order to prevent deadlocks
            // Sort by model type first, then by ID to ensure consistent lock acquisition order
            $models = collect([$this, $recipient])
                ->sortBy(function ($model) {
                    return [get_class($model), $model->getKey()];
                })
                ->values();

            // Acquire locks in deterministic order
            foreach ($models as $model) {
                $model->credits()->lockForUpdate()->latest('id')->value('running_balance');
            }

            // Now perform the actual transfer operations
            $this->creditDeduct($amount, $description, $metadata);
            $transaction = $recipient->creditAdd($amount, $description, $metadata);

            $senderBalance = $this->creditBalance();
            $recipientBalance = $recipient->creditBalance();

            $result = [
                'sender_balance' => $senderBalance,
                'recipient_balance' => $recipientBalance,
            ];

            return $transaction;
        }, 5); // Retry up to 5 times on deadlock

        event(new CreditsTransferred(
            transactionId: $lastTransaction->id,
            sender: $this,
            recipient: $recipient,
            amount: $amount,
            senderNewBalance: $result['sender_balance'],
            recipientNewBalance: $result['recipient_balance'],
            description: $description,
            metadata: $metadata
        ));

        return $result;
    }

    /**
     * Transfer credits from the model to another model.
     *
     * @deprecated Use creditTransfer() instead. Will be removed in v2.0
     */
    public function transferCredits(self $recipient, float $amount, ?string $description = null, array $metadata = []): array
    {
        trigger_error('Method transferCredits() is deprecated. Use creditTransfer() instead.', E_USER_DEPRECATED);

        return $this->creditTransfer($recipient, $amount, $description, $metadata);
    }

    /**
     * Retrieve credit transactions for the model.
     *
     * The results are ordered by `created_at` and then `id` using the specified direction.
     * The `$order` parameter is normalized to `'asc'` or `'desc'` (defaults to `'desc'` if invalid).
     * The `$limit` parameter is clamped to the range 1..1000.
     *
     * @param  int  $limit  Maximum number of records to return (clamped to 1..1000).
     * @param  string  $order  Sort direction, either `'asc'` or `'desc'` (invalid values default to `'desc'`).
     * @return \Illuminate\Database\Eloquent\Collection|EloquentCollection A collection of Credit records matching the query.
     */
    public function creditHistory(int $limit = 10, string $order = 'desc'): EloquentCollection
    {
        // Sanitize order direction - only allow 'asc' or 'desc'
        $order = strtolower($order);
        if (! in_array($order, ['asc', 'desc'], true)) {
            $order = 'desc';
        }

        // Clamp limit to a positive integer between 1 and 1000
        $limit = min(max((int) $limit, 1), 1000);

        return $this->credits()
            ->orderBy('created_at', $order)
            ->orderBy('id', $order) // Tie-break on ID for deterministic ordering
            ->limit($limit)
            ->get();
    }

    /**
     * Retrieve the model's credit transaction history.
     *
     * @param  int  $limit  Maximum number of records to return (clamped to 1..1000).
     * @param  string  $order  Sort direction, either 'asc' or 'desc' (defaults to 'desc').
     * @return \Illuminate\Database\Eloquent\Collection Collection of Credit records.
     *
     * @deprecated Use creditHistory() instead. Will be removed in v2.0.
     */
    public function getTransactionHistory(int $limit = 10, string $order = 'desc'): EloquentCollection
    {
        trigger_error('Method getTransactionHistory() is deprecated. Use creditHistory() instead.', E_USER_DEPRECATED);

        return $this->creditHistory($limit, $order);
    }

    /**
     * Check if the model has enough credits.
     */
    public function hasCredits(float $amount): bool
    {
        return $this->creditBalance() >= $amount;
    }

    /**
     * Check if the model has enough credits.
     *
     * @deprecated Use hasCredits() instead. Will be removed in v2.0
     */
    public function hasEnoughCredits(float $amount): bool
    {
        trigger_error('Method hasEnoughCredits() is deprecated. Use hasCredits() instead.', E_USER_DEPRECATED);

        return $this->hasCredits($amount);
    }

    /**
     * Retrieve the model's credit balance as of a given date/time or Unix timestamp.
     *
     * Accepts a DateTimeInterface or an integer Unix timestamp (seconds or milliseconds; milliseconds are auto-detected).
     *
     * @param  \DateTimeInterface|int  $dateTime  The target date/time or Unix timestamp to query the balance at.
     * @return float The running balance at or before the specified date/time, or 0.0 if no transactions exist.
     */
    public function creditBalanceAt($dateTime): float
    {
        if (is_int($dateTime)) {
            // Auto-detect millisecond timestamps (values > 9999999999 are likely milliseconds)
            if ($dateTime > 9999999999) {
                $dateTime = (int) floor($dateTime / 1000);
            }
            $dateTime = Carbon::createFromTimestamp($dateTime);
        } elseif ($dateTime instanceof \DateTimeInterface) {
            $dateTime = Carbon::instance($dateTime);
        }

        return (float) ($this->credits()
            ->where('created_at', '<=', $dateTime)
            ->latest('id')
            ->value('running_balance') ?? 0.0);
    }

    /**
     * Get the balance of the model as of a specific date and time or timestamp.
     *
     * @param  \DateTimeInterface|int  $dateTime
     *
     * @deprecated Use creditBalanceAt() instead. Will be removed in v2.0
     */
    public function getBalanceAsOf($dateTime): float
    {
        trigger_error('Method getBalanceAsOf() is deprecated. Use creditBalanceAt() instead.', E_USER_DEPRECATED);

        return $this->creditBalanceAt($dateTime);
    }

    /**
     * Get credits filtered by metadata key/value.
     *
     * Supports dot notation for nested keys (e.g., 'user.id', 'items.0.name').
     *
     * @param  string  $key  Metadata key to filter by
     * @param  mixed  $operator  Comparison operator or value if no operator
     * @param  mixed  $value  Value to compare (optional if operator is value)
     * @param  int  $limit  Maximum number of records to return (clamped to 1..1000)
     * @param  string  $order  Sort direction, either 'asc' or 'desc' (defaults to 'desc')
     * @return \Illuminate\Database\Eloquent\Collection Collection of Credit records matching the criteria
     */
    public function creditsByMetadata(string $key, $operator = null, $value = null, int $limit = 10, string $order = 'desc'): EloquentCollection
    {
        // Sanitize order direction
        $order = strtolower($order);
        if (! in_array($order, ['asc', 'desc'], true)) {
            $order = 'desc';
        }

        // Clamp limit to a positive integer between 1 and 1000
        $limit = min(max((int) $limit, 1), 1000);

        // Build query
        $query = $this->credits();

        // Handle two-parameter syntax: creditsByMetadata('key', 'value')
        if ($value === null) {
            $query->whereMetadata($key, $operator);
        } else {
            $query->whereMetadata($key, $operator, $value);
        }

        return $query
            ->orderBy('created_at', $order)
            ->orderBy('id', $order)
            ->limit($limit)
            ->get();
    }

    /**
     * Get credits history filtered by multiple metadata conditions.
     *
     * @param  array  $filters  Array of metadata filters, each containing:
     *                          - 'key': metadata key (required)
     *                          - 'operator': comparison operator (optional, defaults to '=')
     *                          - 'value': value to compare (required)
     *                          - 'method': 'where', 'contains', 'has', or 'null' (optional, defaults to 'where')
     * @param  int  $limit  Maximum number of records to return (clamped to 1..1000)
     * @param  string  $order  Sort direction, either 'asc' or 'desc' (defaults to 'desc')
     * @return \Illuminate\Database\Eloquent\Collection Collection of Credit records matching all criteria
     *
     * @example
     * $user->creditHistoryWithMetadata([
     *     ['key' => 'source', 'value' => 'purchase'],
     *     ['key' => 'amount', 'operator' => '>', 'value' => 100],
     *     ['key' => 'tags', 'value' => 'premium', 'method' => 'contains'],
     * ], limit: 50);
     */
    public function creditHistoryWithMetadata(array $filters, int $limit = 10, string $order = 'desc'): EloquentCollection
    {
        // Sanitize order direction
        $order = strtolower($order);
        if (! in_array($order, ['asc', 'desc'], true)) {
            $order = 'desc';
        }

        // Clamp limit to a positive integer between 1 and 1000
        $limit = min(max((int) $limit, 1), 1000);

        // Build query with filters
        $query = $this->credits();

        foreach ($filters as $filter) {
            $key = $filter['key'] ?? null;
            $method = $filter['method'] ?? 'where';
            $operator = $filter['operator'] ?? '=';
            $value = $filter['value'] ?? null;

            if (! $key) {
                continue; // Skip invalid filters
            }

            switch ($method) {
                case 'contains':
                    $query->whereMetadataContains($key, $value);
                    break;
                case 'has':
                    $query->whereMetadataHas($key);
                    break;
                case 'null':
                    $query->whereMetadataNull($key);
                    break;
                case 'length':
                    $query->whereMetadataLength($key, $operator, $value);
                    break;
                case 'where':
                default:
                    if ($value === null && $operator !== '=') {
                        // Two-parameter syntax: operator is actually the value
                        $query->whereMetadata($key, $operator);
                    } else {
                        $query->whereMetadata($key, $operator, $value);
                    }
                    break;
            }
        }

        return $query
            ->orderBy('created_at', $order)
            ->orderBy('id', $order)
            ->limit($limit)
            ->get();
    }
}
