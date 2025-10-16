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
    public function creditTransactions(): MorphMany
    {
        return $this->morphMany(Credit::class, 'creditable');
    }

    /**
     * Add credits to the model.
     */
    public function addCredits(float $amount, ?string $description = null, array $metadata = []): Credit
    {
        $currentBalance = $this->getCurrentBalance();
        $newBalance = $currentBalance + $amount;

        $credit = $this->creditTransactions()->create([
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
     * Deduct credits from the model.
     */
    public function deductCredits(float $amount, ?string $description = null, array $metadata = []): Credit
    {
        $currentBalance = $this->getCurrentBalance();
        $newBalance = $currentBalance - $amount;

        if (! config('credits.allow_negative_balance') && $newBalance < 0) {
            throw new InsufficientCreditsException($amount, $currentBalance);
        }

        $credit = $this->creditTransactions()->create([
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
     * Get the current balance of the model.
     */
    public function getCurrentBalance(): float
    {
       // Use latest by ID to ensure correct order even with same timestamps
        return $this->creditTransactions()
            ->latest('id')
            ->value('running_balance') ?? 0.0;
    }

    /**
     * Transfer credits from the model to another model.
     */
    public function transferCredits(self $recipient, float $amount, ?string $description = null, array $metadata = []): array
    {
        $result = [];

        $lastTransaction = DB::transaction(function () use ($recipient, $amount, $description, $metadata, &$result) {
            $this->deductCredits($amount, $description, $metadata);
            $transaction = $recipient->addCredits($amount, $description, $metadata);

            $senderBalance = $this->getCurrentBalance();
            $recipientBalance = $recipient->getCurrentBalance();

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
     * Get the transaction history of the model.
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
     */
    public function hasEnoughCredits(float $amount): bool
    {
        return $this->getCurrentBalance() >= $amount;
    }

    /**
     * Get the balance of the model as of a specific date and time or timestamp.
     *
     * @param  \DateTimeInterface|int  $dateTime
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
