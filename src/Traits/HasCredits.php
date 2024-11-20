<?php

namespace Climactic\Credits\Traits;

use Climactic\Credits\Models\Credit;
use Climactic\Credits\Exceptions\InsufficientCreditsException;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

trait HasCredits
{
    /**
     * @return MorphMany
     */
    public function creditTransactions(): MorphMany
    {
        return $this->morphMany(Credit::class, 'creditable');
    }

    /**
     * @param float $amount
     * @param string|null $description
     * @param array $metadata
     * @return Credit
     */
    public function addCredits(float $amount, string $description = null, array $metadata = []): Credit
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
     * @param float $amount
     * @param string|null $description
     * @param array $metadata
     * @return Credit
     */
    public function deductCredits(float $amount, string $description = null, array $metadata = []): Credit
    {
        $currentBalance = $this->getCurrentBalance();
        $newBalance = $currentBalance - $amount;

        if (!config('credits.allow_negative_balance') && $newBalance < 0) {
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
     * @return float
     */
    public function getCurrentBalance(): float
    {
        return $this->creditTransactions()
            ->latest()
            ->value('running_balance') ?? 0.0;
    }

    /**
     * @param self $recipient
     * @param float $amount
     * @param string|null $description
     * @param array $metadata
     * @return array
     */
    public function transferCredits(self $recipient, float $amount, string $description = null, array $metadata = []): array
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
     * @param int $limit
     * @param string $order
     * @return Collection
     */
    public function getTransactionHistory(int $limit = 10, string $order = 'desc'): Collection
    {
        return $this->creditTransactions()
            ->orderBy('created_at', $order)
            ->cursorPaginate($limit);
    }

    /**
     * @param float $amount
     * @return bool
     */
    public function hasEnoughCredits(float $amount): bool
    {
        return $this->getCurrentBalance() >= $amount;
    }

    /**
     * @param \DateTime $date
     * @return float
     */
    public function getBalanceAsOf(\DateTime $date): float
    {
        return $this->creditTransactions()
            ->where('created_at', '<=', $date)
            ->latest()
            ->value('running_balance') ?? 0.0;
    }
}
