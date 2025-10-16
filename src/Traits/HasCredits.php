<?php

namespace Climactic\Credits\Traits;

use Climactic\Credits\Events\CreditsAdded;
use Climactic\Credits\Events\CreditsDeducted;
use Climactic\Credits\Events\CreditsTransferred;
use Climactic\Credits\Exceptions\InsufficientCreditsException;
use Climactic\Credits\Models\Credit;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
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
     * Add credits to the model.
     */
    public function creditAdd(float $amount, ?string $description = null, array $metadata = []): Credit
    {
        $currentBalance = $this->creditBalance();
        $newBalance = $currentBalance + $amount;

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
     * Deduct credits from the model.
     */
    public function creditDeduct(float $amount, ?string $description = null, array $metadata = []): Credit
    {
        $currentBalance = $this->creditBalance();
        $newBalance = $currentBalance - $amount;

        if (! config('credits.allow_negative_balance') && $newBalance < 0) {
            throw new InsufficientCreditsException($amount, $currentBalance);
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
     * Get the current balance of the model.
     */
    public function creditBalance(): float
    {
        // Use latest by ID to ensure correct order even with same timestamps
        return $this->credits()
            ->latest('id')
            ->value('running_balance') ?? 0.0;
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
     * Transfer credits from the model to another model.
     */
    public function creditTransfer(self $recipient, float $amount, ?string $description = null, array $metadata = []): array
    {
        $result = [];

        $lastTransaction = DB::transaction(function () use ($recipient, $amount, $description, $metadata, &$result) {
            $this->creditDeduct($amount, $description, $metadata);
            $transaction = $recipient->creditAdd($amount, $description, $metadata);

            $senderBalance = $this->creditBalance();
            $recipientBalance = $recipient->creditBalance();

            $result = [
                'sender_balance' => $senderBalance,
                'recipient_balance' => $recipientBalance,
            ];

            return $transaction;
        });

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
     * Get the transaction history of the model.
     */
    public function creditHistory(int $limit = 10, string $order = 'desc'): Collection
    {
        return $this->credits()
            ->orderBy('created_at', $order)
            ->limit($limit)
            ->get();
    }

    /**
     * Get the transaction history of the model.
     *
     * @deprecated Use creditHistory() instead. Will be removed in v2.0
     */
    public function getTransactionHistory(int $limit = 10, string $order = 'desc'): Collection
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
     * Get the balance of the model as of a specific date and time or timestamp.
     *
     * @param  \DateTimeInterface|int  $dateTime
     */
    public function creditBalanceAt($dateTime): float
    {
        if (is_int($dateTime)) {
            $dateTime = new \DateTime("@$dateTime");
        }

        return $this->credits()
            ->where('created_at', '<=', $dateTime)
            ->latest('id')
            ->value('running_balance') ?? 0.0;
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
}
