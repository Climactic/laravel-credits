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

    public function getCurrentBalance(): float
    {
        return $this->creditTransactions()
            ->latest()
            ->value('running_balance') ?? 0.0;
    }

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

    public function getTransactionHistory(int $limit = 10, string $order = 'desc'): Collection
    {
        return $this->creditTransactions()
            ->orderBy('created_at', $order)
            ->limit($limit)
            ->get();
    }

    public function hasEnoughCredits(float $amount): bool
    {
        return $this->getCurrentBalance() >= $amount;
    }

    public function getBalanceAsOf(\DateTime $date): float
    {
        return $this->creditTransactions()
            ->where('created_at', '<=', $date)
            ->latest()
            ->value('running_balance') ?? 0.0;
    }
}
