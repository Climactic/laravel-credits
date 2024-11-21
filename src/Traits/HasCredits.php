<?php

namespace Climactic\Credits\Traits;

use Climactic\Credits\Exceptions\InsufficientCreditsException;
use Climactic\Credits\Models\Credit;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

trait HasCredits
{
    public function creditTransactions(): MorphMany
    {
        return $this->morphMany(Credit::class, 'creditable');
    }

    /**
     * Add credits to the model.
     *
     * @param float $amount
     * @param string|null $description
     * @param array $metadata
     * @return Credit
     */
    public function addCredits(float $amount, ?string $description = null, array $metadata = []): Credit
    {
        $currentBalance = $this->getCurrentBalance();
        $newBalance = $currentBalance + $amount;

        return $this->creditTransactions()->create([
            'amount' => $amount,
            'description' => $description,
            'type' => 'credit',
            'metadata' => $metadata,
            'running_balance' => $newBalance,
        ]);
    }

    /**
     * Deduct credits from the model.
     *
     * @param float $amount
     * @param string|null $description
     * @param array $metadata
     * @return Credit
     */
    public function deductCredits(float $amount, ?string $description = null, array $metadata = []): Credit
    {
        $currentBalance = $this->getCurrentBalance();
        $newBalance = $currentBalance - $amount;

        if (! config('credits.allow_negative_balance') && $newBalance < 0) {
            throw new InsufficientCreditsException($amount, $currentBalance);
        }

        return $this->creditTransactions()->create([
            'amount' => $amount,
            'description' => $description,
            'type' => 'debit',
            'metadata' => $metadata,
            'running_balance' => $newBalance,
        ]);
    }

    /**
     * Get the current balance of the model.
     *
     * @return float
     */
    public function getCurrentBalance(): float
    {
        return $this->creditTransactions()
            ->latest()
            ->value('running_balance') ?? 0.0;
    }

    /**
     * Transfer credits from the model to another model.
     *
     * @param self $recipient
     * @param float $amount
     * @param string|null $description
     * @param array $metadata
     * @return array
     */
    public function transferCredits(self $recipient, float $amount, ?string $description = null, array $metadata = []): array
    {
        DB::transaction(function () use ($recipient, $amount, $description, $metadata) {
            $this->deductCredits($amount, $description, $metadata);
            $recipient->addCredits($amount, $description, $metadata);
        });

        return [
            'sender_balance' => $this->getCurrentBalance(),
            'recipient_balance' => $recipient->getCurrentBalance(),
        ];
    }

    /**
     * Get the transaction history of the model.
     *
     * @param int $limit
     * @param string $order
     * @return Collection
     */
    public function getTransactionHistory(int $limit = 10, string $order = 'desc'): Collection
    {
        return $this->creditTransactions()
            ->orderBy('created_at', $order)
            ->limit($limit)
            ->get();
    }

    /**
     * Check if the model has enough credits.
     *
     * @param float $amount
     * @return bool
     */
    public function hasEnoughCredits(float $amount): bool
    {
        return $this->getCurrentBalance() >= $amount;
    }

    /**
     * Get the balance of the model as of a specific date and time or timestamp.
     *
     * @param \DateTimeInterface|int $dateTime
     * @return float
     */
    public function getBalanceAsOf($dateTime): float
    {
        if (is_int($dateTime)) {
            $dateTime = new \DateTime("@$dateTime");
        }

        return $this->creditTransactions()
            ->where('created_at', '<=', $dateTime)
            ->latest()
            ->value('running_balance') ?? 0.0;
    }
}
