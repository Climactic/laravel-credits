<?php

use Climactic\Credits\Events\CreditsAdded;
use Climactic\Credits\Events\CreditsDeducted;
use Climactic\Credits\Events\CreditsTransferred;
use Climactic\Credits\Exceptions\InsufficientCreditsException;
use Climactic\Credits\Tests\TestModels\User;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);
});

it('can add credits', function () {
    $transaction = $this->user->creditAdd(100.00, 'Test credit');

    expect((float) $transaction->amount)->toEqual(100.00)
        ->and($transaction->type)->toBe('credit')
        ->and((float) $transaction->running_balance)->toEqual(100.00)
        ->and((float) $this->user->creditBalance())->toEqual(100.00);
});

it('can deduct credits', function () {
    $this->user->creditAdd(100.00);
    $transaction = $this->user->creditDeduct(50.00, 'Test debit');

    expect((float) $transaction->amount)->toEqual(50.00)
        ->and($transaction->type)->toBe('debit')
        ->and((float) $transaction->running_balance)->toEqual(50.00)
        ->and((float) $this->user->creditBalance())->toEqual(50.00);
});

it('prevents negative balance when configured', function () {
    config(['credits.allow_negative_balance' => false]);

    $this->user->creditAdd(100.00);

    expect(fn () => $this->user->creditDeduct(150.00))
        ->toThrow(InsufficientCreditsException::class);
});

it('allows negative balance when configured', function () {
    config(['credits.allow_negative_balance' => true]);

    $this->user->creditAdd(100.00);
    $transaction = $this->user->creditDeduct(150.00);

    expect((float) $transaction->running_balance)->toEqual(-50.00)
        ->and((float) $this->user->creditBalance())->toEqual(-50.00);
});

it('can transfer credits between users', function () {
    $recipient = User::create([
        'name' => 'Recipient User',
        'email' => 'recipient@example.com',
    ]);

    $this->user->creditAdd(100.00);
    $result = $this->user->creditTransfer($recipient, 50.00, 'Test transfer');

    expect($result['sender_balance'])->toBe(50.00)
        ->and($result['recipient_balance'])->toBe(50.00)
        ->and($this->user->creditBalance())->toBe(50.00)
        ->and($recipient->creditBalance())->toBe(50.00);
});

it('can get transaction history', function () {
    $this->user->creditAdd(100.00, 'First credit');
    $this->user->creditDeduct(30.00, 'First debit');
    $this->user->creditAdd(50.00, 'Second credit');

    $history = $this->user->creditHistory(10);

    expect($history)->toHaveCount(3)
        ->and($history->first()->description)->toBe('Second credit');
});

it('can check if has enough credits', function () {
    $this->user->creditAdd(100.00);

    expect($this->user->hasCredits(50.00))->toBeTrue()
        ->and($this->user->hasCredits(150.00))->toBeFalse();
});

it('can get balance as of date', function () {
    $this->user->creditAdd(100.00);

    // Move time forward
    $this->travel(1)->days();

    $this->user->creditAdd(50.00);

    $pastDate = now()->subDay();
    $balance = $this->user->creditBalanceAt($pastDate);

    expect($balance)->toBe(100.00);
});

it('can get balance as of timestamp', function () {
    // Store current time before adding credits
    $beforeTimestamp = now()->subSeconds(30)->timestamp;

    $this->user->creditAdd(100.00);

    $afterTimestamp = now()->addSeconds(30)->timestamp;

    // Test balance at different points in time
    expect($this->user->creditBalanceAt($beforeTimestamp))->toBe(0.00)
        ->and($this->user->creditBalanceAt($afterTimestamp))->toBe(100.00);
});

it('maintains accurate running balance', function () {
    $transactions = collect([
        $this->user->creditAdd(100.00),
        $this->user->creditDeduct(30.00),
        $this->user->creditAdd(50.00),
        $this->user->creditDeduct(20.00),
    ]);

    $expectedBalances = [100.00, 70.00, 120.00, 100.00];

    $transactions->each(function ($transaction, $index) use ($expectedBalances) {
        expect((float) $transaction->running_balance)->toEqual($expectedBalances[$index]);
    });

    expect((float) $this->user->creditBalance())->toEqual(100.00);
});

it('dispatches event when credits are added', function () {
    Event::fake();

    $transaction = $this->user->creditAdd(100.00, 'Test credit', ['source' => 'test']);

    Event::assertDispatched(CreditsAdded::class, function ($event) use ($transaction) {
        return $event->creditable->is($this->user)
            && $event->transactionId === $transaction->id
            && $event->amount === 100.00
            && $event->newBalance === 100.00
            && $event->description === 'Test credit'
            && $event->metadata === ['source' => 'test'];
    });
});

it('dispatches event when credits are deducted', function () {
    Event::fake();

    $this->user->creditAdd(100.00);
    Event::fake(); // Reset fake after initial credit

    $transaction = $this->user->creditDeduct(50.00, 'Test debit', ['reason' => 'purchase']);

    Event::assertDispatched(CreditsDeducted::class, function ($event) use ($transaction) {
        return $event->creditable->is($this->user)
            && $event->transactionId === $transaction->id
            && $event->amount === 50.00
            && $event->newBalance === 50.00
            && $event->description === 'Test debit'
            && $event->metadata === ['reason' => 'purchase'];
    });
});

it('dispatches event when credits are transferred', function () {
    Event::fake();

    $recipient = User::create([
        'name' => 'Recipient User',
        'email' => 'recipient.transfer@example.com',
    ]);

    $this->user->creditAdd(100.00);
    Event::fake(); // Reset fake after initial credit

    $result = $this->user->creditTransfer($recipient, 50.00, 'Test transfer', ['type' => 'gift']);

    Event::assertDispatched(CreditsTransferred::class, function ($event) use ($recipient, $result) {
        return $event->sender->is($this->user)
            && $event->recipient->is($recipient)
            && $event->amount === 50.00
            && $event->senderNewBalance === $result['sender_balance']
            && $event->recipientNewBalance === $result['recipient_balance']
            && $event->description === 'Test transfer'
            && $event->metadata === ['type' => 'gift'];
    });
});

it('returns correct running balance even when multiple transactions share same timestamp', function () {
    // This test verifies that creditBalance() uses latest('id') instead of latest('created_at').
    // When multiple transactions share identical timestamps, ORDER BY created_at is non-deterministic
    // across different database engines (MySQL, PostgreSQL, etc.). Using latest('id') ensures
    // consistent, predictable results by sorting on the auto-incrementing primary key.

    $fixedTimestamp = now();

    // Add initial credits
    $this->user->creditAdd(100, 'Initial');

    // Create multiple deductions and force them to have the same timestamp
    // This simulates a scenario where transactions are created in rapid succession
    $transaction1 = $this->user->creditDeduct(10, 'First deduction');
    $transaction2 = $this->user->creditDeduct(10, 'Second deduction');
    $transaction3 = $this->user->creditDeduct(10, 'Third deduction');

    // Force all three transactions to have identical timestamps
    $transaction1->update(['created_at' => $fixedTimestamp, 'updated_at' => $fixedTimestamp]);
    $transaction2->update(['created_at' => $fixedTimestamp, 'updated_at' => $fixedTimestamp]);
    $transaction3->update(['created_at' => $fixedTimestamp, 'updated_at' => $fixedTimestamp]);

    // Verify the transactions have the correct running balances
    expect((float) $transaction1->running_balance)->toBe(90.0)
        ->and((float) $transaction2->running_balance)->toBe(80.0)
        ->and((float) $transaction3->running_balance)->toBe(70.0);

    // creditBalance() should return the most recent transaction by ID (transaction3 with balance 70)
    // not by timestamp. This ensures deterministic behavior across all database engines.
    expect($this->user->creditBalance())->toBe(70.0);
});

it('returns correct balance as of date when multiple transactions share same timestamp', function () {
    // Similar to the previous test, but for creditBalanceAt()
    $fixedTimestamp = now();

    // Create multiple transactions with different balances
    $this->user->creditAdd(100, 'Initial');
    $transaction1 = $this->user->creditDeduct(10, 'First deduction');
    $transaction2 = $this->user->creditDeduct(10, 'Second deduction');
    $transaction3 = $this->user->creditDeduct(10, 'Third deduction');

    // Force all deductions to have identical timestamps
    $transaction1->update(['created_at' => $fixedTimestamp, 'updated_at' => $fixedTimestamp]);
    $transaction2->update(['created_at' => $fixedTimestamp, 'updated_at' => $fixedTimestamp]);
    $transaction3->update(['created_at' => $fixedTimestamp, 'updated_at' => $fixedTimestamp]);

    // When querying balance at the fixed timestamp, we should get the latest by ID (transaction3)
    $balance = $this->user->creditBalanceAt($fixedTimestamp);

    expect($balance)->toBe(70.0);
});

// Deprecated method tests - ensure backward compatibility
describe('deprecated methods', function () {
    it('supports deprecated addCredits method', function () {
        $transaction = $this->user->addCredits(100.00, 'Test credit');

        expect((float) $transaction->amount)->toEqual(100.00)
            ->and($transaction->type)->toBe('credit')
            ->and((float) $this->user->creditBalance())->toEqual(100.00);
    });

    it('supports deprecated deductCredits method', function () {
        $this->user->creditAdd(100.00);
        $transaction = $this->user->deductCredits(50.00, 'Test debit');

        expect((float) $transaction->amount)->toEqual(50.00)
            ->and($transaction->type)->toBe('debit')
            ->and((float) $this->user->creditBalance())->toEqual(50.00);
    });

    it('supports deprecated getCurrentBalance method', function () {
        $this->user->creditAdd(100.00);

        expect($this->user->getCurrentBalance())->toBe(100.00);
    });

    it('supports deprecated transferCredits method', function () {
        $recipient = User::create([
            'name' => 'Recipient User',
            'email' => 'deprecated.recipient@example.com',
        ]);

        $this->user->creditAdd(100.00);
        $result = $this->user->transferCredits($recipient, 50.00, 'Test transfer');

        expect($result['sender_balance'])->toBe(50.00)
            ->and($result['recipient_balance'])->toBe(50.00);
    });

    it('supports deprecated getTransactionHistory method', function () {
        $this->user->creditAdd(100.00, 'First credit');
        $this->user->creditDeduct(30.00, 'First debit');

        $history = $this->user->getTransactionHistory(10);

        expect($history)->toHaveCount(2);
    });

    it('supports deprecated hasEnoughCredits method', function () {
        $this->user->creditAdd(100.00);

        expect($this->user->hasEnoughCredits(50.00))->toBeTrue()
            ->and($this->user->hasEnoughCredits(150.00))->toBeFalse();
    });

    it('supports deprecated getBalanceAsOf method', function () {
        $beforeTimestamp = now()->subSeconds(30)->timestamp;
        $this->user->creditAdd(100.00);
        $afterTimestamp = now()->addSeconds(30)->timestamp;

        expect($this->user->getBalanceAsOf($beforeTimestamp))->toBe(0.00)
            ->and($this->user->getBalanceAsOf($afterTimestamp))->toBe(100.00);
    });

    it('supports deprecated creditTransactions relationship', function () {
        $this->user->creditAdd(100.00);
        $this->user->creditDeduct(30.00);

        expect($this->user->creditTransactions)->toHaveCount(2)
            ->and($this->user->creditTransactions()->count())->toBe(2);
    });
});
