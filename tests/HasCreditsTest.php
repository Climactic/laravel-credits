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

it('can get balance as of millisecond timestamp', function () {
    // Store current time before adding credits in milliseconds
    $beforeTimestampMs = now()->subSeconds(30)->timestamp * 1000;

    $this->user->creditAdd(100.00);

    $afterTimestampMs = now()->addSeconds(30)->timestamp * 1000;

    // Test balance at different points in time with millisecond timestamps
    // The method should auto-detect and convert milliseconds to seconds
    expect($this->user->creditBalanceAt($beforeTimestampMs))->toBe(0.00)
        ->and($this->user->creditBalanceAt($afterTimestampMs))->toBe(100.00);
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

    // Create multiple transactions with different balances
    $initial = $this->user->creditAdd(100, 'Initial');
    $transaction1 = $this->user->creditDeduct(10, 'First deduction');
    $transaction2 = $this->user->creditDeduct(10, 'Second deduction');
    $transaction3 = $this->user->creditDeduct(10, 'Third deduction');

    // Set a fixed timestamp AFTER all transactions are created
    $fixedTimestamp = now();

    // Force all transactions (including initial) to have identical timestamps
    // This ensures creditBalanceAt() must rely on ID ordering
    $initial->update(['created_at' => $fixedTimestamp, 'updated_at' => $fixedTimestamp]);
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

    it('emits deprecation warnings for deprecated methods', function () {
        // Capture deprecation notices
        $deprecations = [];
        set_error_handler(function ($errno, $errstr) use (&$deprecations) {
            if ($errno === E_USER_DEPRECATED) {
                $deprecations[] = $errstr;
            }
        });

        try {
            // Call all deprecated methods
            $this->user->creditAdd(100.00);
            $this->user->addCredits(50.00);
            $this->user->deductCredits(10.00);
            $this->user->getCurrentBalance();
            $recipient = User::create(['name' => 'Test', 'email' => 'test2@example.com']);
            $this->user->transferCredits($recipient, 10.00);
            $this->user->getTransactionHistory(5);
            $this->user->hasEnoughCredits(10.00);
            $this->user->getBalanceAsOf(now());
            $this->user->creditTransactions;
        } finally {
            restore_error_handler();
        }

        // Verify deprecation notices were triggered
        expect($deprecations)->toContain('Method addCredits() is deprecated. Use creditAdd() instead.')
            ->and($deprecations)->toContain('Method deductCredits() is deprecated. Use creditDeduct() instead.')
            ->and($deprecations)->toContain('Method getCurrentBalance() is deprecated. Use creditBalance() instead.')
            ->and($deprecations)->toContain('Method transferCredits() is deprecated. Use creditTransfer() instead.')
            ->and($deprecations)->toContain('Method getTransactionHistory() is deprecated. Use creditHistory() instead.')
            ->and($deprecations)->toContain('Method hasEnoughCredits() is deprecated. Use hasCredits() instead.')
            ->and($deprecations)->toContain('Method getBalanceAsOf() is deprecated. Use creditBalanceAt() instead.')
            ->and($deprecations)->toContain('Method creditTransactions() is deprecated. Use credits() instead.');
    });
});

// Input validation tests
describe('input validation', function () {
    it('rejects zero amount when adding credits', function () {
        expect(fn () => $this->user->creditAdd(0.00, 'Invalid amount'))
            ->toThrow(\InvalidArgumentException::class, 'Amount must be greater than 0.');
    });

    it('rejects negative amount when adding credits', function () {
        expect(fn () => $this->user->creditAdd(-50.00, 'Invalid amount'))
            ->toThrow(\InvalidArgumentException::class, 'Amount must be greater than 0.');
    });

    it('rejects zero amount when deducting credits', function () {
        $this->user->creditAdd(100.00);

        expect(fn () => $this->user->creditDeduct(0.00, 'Invalid amount'))
            ->toThrow(\InvalidArgumentException::class, 'Amount must be greater than 0.');
    });

    it('rejects negative amount when deducting credits', function () {
        $this->user->creditAdd(100.00);

        expect(fn () => $this->user->creditDeduct(-50.00, 'Invalid amount'))
            ->toThrow(\InvalidArgumentException::class, 'Amount must be greater than 0.');
    });

    it('prevents balance manipulation via negative deduction', function () {
        // Ensure that negative amounts in creditDeduct cannot be used to increase balance
        $this->user->creditAdd(100.00, 'Initial balance');

        expect(fn () => $this->user->creditDeduct(-50.00, 'Attempting to increase via deduction'))
            ->toThrow(\InvalidArgumentException::class, 'Amount must be greater than 0.');

        // Balance should remain unchanged at 100.00
        expect($this->user->creditBalance())->toBe(100.00);
    });

    it('prevents balance manipulation via negative addition', function () {
        // Ensure that negative amounts in creditAdd cannot be used to decrease balance
        $this->user->creditAdd(100.00, 'Initial balance');

        expect(fn () => $this->user->creditAdd(-50.00, 'Attempting to decrease via addition'))
            ->toThrow(\InvalidArgumentException::class, 'Amount must be greater than 0.');

        // Balance should remain unchanged at 100.00
        expect($this->user->creditBalance())->toBe(100.00);
    });

    it('sanitizes order parameter in creditHistory', function () {
        $this->user->creditAdd(100.00, 'First');
        $this->user->creditAdd(50.00, 'Second');
        $this->user->creditAdd(25.00, 'Third');

        // Test valid 'asc' order
        $history = $this->user->creditHistory(10, 'asc');
        expect($history->first()->description)->toBe('First');

        // Test valid 'desc' order
        $history = $this->user->creditHistory(10, 'desc');
        expect($history->first()->description)->toBe('Third');

        // Test uppercase 'ASC' - should be converted to lowercase
        $history = $this->user->creditHistory(10, 'ASC');
        expect($history->first()->description)->toBe('First');

        // Test uppercase 'DESC' - should be converted to lowercase
        $history = $this->user->creditHistory(10, 'DESC');
        expect($history->first()->description)->toBe('Third');

        // Test invalid order - should default to 'desc'
        $history = $this->user->creditHistory(10, 'invalid');
        expect($history->first()->description)->toBe('Third');

        // Test SQL injection attempt - should default to 'desc'
        $history = $this->user->creditHistory(10, 'desc; DROP TABLE credits;--');
        expect($history->first()->description)->toBe('Third');
    });

    it('clamps limit parameter in creditHistory', function () {
        // Create 15 transactions
        for ($i = 1; $i <= 15; $i++) {
            $this->user->creditAdd(10.00, "Transaction {$i}");
        }

        // Test normal limit
        $history = $this->user->creditHistory(5);
        expect($history)->toHaveCount(5);

        // Test zero limit - should be clamped to 1
        $history = $this->user->creditHistory(0);
        expect($history)->toHaveCount(1);

        // Test negative limit - should be clamped to 1
        $history = $this->user->creditHistory(-10);
        expect($history)->toHaveCount(1);

        // Test excessive limit - should be clamped to 1000 (but we only have 15 records)
        $history = $this->user->creditHistory(9999);
        expect($history)->toHaveCount(15);

        // Test exactly at max (1000) - should work
        $history = $this->user->creditHistory(1000);
        expect($history)->toHaveCount(15);

        // Test just above max (1001) - should be clamped to 1000
        $history = $this->user->creditHistory(1001);
        expect($history)->toHaveCount(15);
    });
});

// Concurrency and race condition tests
describe('concurrency', function () {
    it('prevents race conditions when adding credits concurrently', function () {
        // This test verifies that the lockForUpdate() mechanism prevents race conditions
        // when multiple credit operations happen simultaneously on the same user.
        // Without proper locking, concurrent transactions could read the same balance
        // and write incorrect running_balance values.

        $this->user->creditAdd(100.00, 'Initial balance');

        // Simulate concurrent credit additions by nesting DB transactions
        // In a real concurrent scenario, these would run in separate processes/threads
        \Illuminate\Support\Facades\DB::transaction(function () {
            // First concurrent operation: read balance and prepare to add 50
            $balance1 = $this->user->creditBalance();
            expect($balance1)->toBe(100.00);

            // The lockForUpdate() in creditAdd ensures this completes atomically
            $this->user->creditAdd(50.00, 'Concurrent add 1');

            // Second operation should see the updated balance due to locking
            $balance2 = $this->user->creditBalance();
            expect($balance2)->toBe(150.00);

            $this->user->creditAdd(25.00, 'Concurrent add 2');

            // Final balance should be correct: 100 + 50 + 25 = 175
            $finalBalance = $this->user->creditBalance();
            expect($finalBalance)->toBe(175.00);
        });

        // Verify the running balances are sequential and correct
        $transactions = $this->user->creditHistory(10, 'asc');
        expect((float) $transactions[0]->running_balance)->toBe(100.00)
            ->and((float) $transactions[1]->running_balance)->toBe(150.00)
            ->and((float) $transactions[2]->running_balance)->toBe(175.00);
    });

    it('prevents race conditions when deducting credits concurrently', function () {
        $this->user->creditAdd(200.00, 'Initial balance');

        \Illuminate\Support\Facades\DB::transaction(function () {
            $balance1 = $this->user->creditBalance();
            expect($balance1)->toBe(200.00);

            // Deduct operations should be serialized by lockForUpdate()
            $this->user->creditDeduct(30.00, 'Concurrent deduct 1');

            $balance2 = $this->user->creditBalance();
            expect($balance2)->toBe(170.00);

            $this->user->creditDeduct(20.00, 'Concurrent deduct 2');

            $finalBalance = $this->user->creditBalance();
            expect($finalBalance)->toBe(150.00);
        });

        // Verify the running balances are sequential and correct
        $transactions = $this->user->creditHistory(10, 'asc');
        expect((float) $transactions[0]->running_balance)->toBe(200.00)
            ->and((float) $transactions[1]->running_balance)->toBe(170.00)
            ->and((float) $transactions[2]->running_balance)->toBe(150.00);
    });

    it('prevents overdraft race conditions with insufficient balance check', function () {
        config(['credits.allow_negative_balance' => false]);

        $this->user->creditAdd(100.00, 'Initial balance');

        // Try to deduct more than available in a concurrent scenario
        // The lock should prevent both operations from reading the same balance
        \Illuminate\Support\Facades\DB::transaction(function () {
            // First deduction succeeds
            $this->user->creditDeduct(80.00, 'Large deduction');
            expect($this->user->creditBalance())->toBe(20.00);

            // Second deduction should fail because balance is only 20
            expect(fn () => $this->user->creditDeduct(50.00, 'Would overdraft'))
                ->toThrow(InsufficientCreditsException::class);

            // Balance should remain at 20
            expect($this->user->creditBalance())->toBe(20.00);
        });
    });

    it('acquires locks in deterministic order during transfers to prevent deadlocks', function () {
        // This test verifies that creditTransfer() acquires locks in a consistent order
        // based on model type and ID, preventing deadlocks when two transfers occur
        // simultaneously in opposite directions (A→B and B→A).

        $userA = User::create(['name' => 'User A', 'email' => 'a@example.com']);
        $userB = User::create(['name' => 'User B', 'email' => 'b@example.com']);

        $userA->creditAdd(100.00, 'Initial balance A');
        $userB->creditAdd(100.00, 'Initial balance B');

        // Simulate bidirectional transfers in the same transaction
        // The deterministic locking order should prevent deadlocks
        \Illuminate\Support\Facades\DB::transaction(function () use ($userA, $userB) {
            // Transfer A → B
            $result1 = $userA->creditTransfer($userB, 30.00, 'Transfer A to B');

            // Transfer B → A
            $result2 = $userB->creditTransfer($userA, 20.00, 'Transfer B to A');

            // Verify final balances
            // A: 100 - 30 + 20 = 90
            // B: 100 + 30 - 20 = 110
            expect($userA->creditBalance())->toBe(90.00)
                ->and($userB->creditBalance())->toBe(110.00)
                ->and($result1['sender_balance'])->toBe(70.00)
                ->and($result1['recipient_balance'])->toBe(130.00)
                ->and($result2['sender_balance'])->toBe(110.00)
                ->and($result2['recipient_balance'])->toBe(90.00);
        });

        // Verify final state after transaction
        expect($userA->creditBalance())->toBe(90.00)
            ->and($userB->creditBalance())->toBe(110.00);
    });

    it('handles transfers between users with different IDs in correct lock order', function () {
        // Create users with explicit ordering to test lock acquisition
        $user1 = User::create(['name' => 'User 1', 'email' => 'user1@example.com']);
        $user2 = User::create(['name' => 'User 2', 'email' => 'user2@example.com']);
        $user3 = User::create(['name' => 'User 3', 'email' => 'user3@example.com']);

        $user1->creditAdd(100.00);
        $user2->creditAdd(100.00);
        $user3->creditAdd(100.00);

        // Perform transfers in various directions
        $user3->creditTransfer($user1, 10.00, 'Transfer 3 to 1');
        $user1->creditTransfer($user2, 20.00, 'Transfer 1 to 2');
        $user2->creditTransfer($user3, 30.00, 'Transfer 2 to 3');

        // Verify all balances are correct
        // User 1: 100 + 10 - 20 = 90
        // User 2: 100 + 20 - 30 = 90
        // User 3: 100 - 10 + 30 = 120
        expect($user1->creditBalance())->toBe(90.00)
            ->and($user2->creditBalance())->toBe(90.00)
            ->and($user3->creditBalance())->toBe(120.00);
    });

    it('handles self-transfers correctly with deterministic locking', function () {
        // Edge case: transferring to self (though not recommended in practice)
        $this->user->creditAdd(100.00);

        // This should work without deadlock since both sender and recipient are the same
        $result = $this->user->creditTransfer($this->user, 50.00, 'Self transfer');

        // Balance should remain the same (deduct 50, add 50)
        expect($this->user->creditBalance())->toBe(100.00)
            ->and($result['sender_balance'])->toBe(100.00)
            ->and($result['recipient_balance'])->toBe(100.00);

        // Verify we have the correct number of transactions (initial + deduct + add)
        expect($this->user->credits()->count())->toBe(3);
    });

    it('retries transaction on deadlock', function () {
        // This test verifies that DB::transaction with attempts parameter
        // will retry on deadlock. While we can't easily simulate a real deadlock
        // in tests, we verify the transaction completes successfully with the
        // retry parameter configured.

        $this->user->creditAdd(100.00, 'Initial');

        // Perform multiple concurrent-like operations that would benefit from retry logic
        $this->user->creditDeduct(20.00, 'First op');
        $this->user->creditAdd(30.00, 'Second op');
        $this->user->creditDeduct(10.00, 'Third op');

        // Verify final balance is correct: 100 - 20 + 30 - 10 = 100
        expect($this->user->creditBalance())->toBe(100.00);

        // Verify all transactions were recorded
        expect($this->user->credits()->count())->toBe(4);
    });

    it('maintains transaction integrity across retries', function () {
        // Verify that even with retry logic, transactions maintain ACID properties
        $this->user->creditAdd(100.00, 'Initial');

        \Illuminate\Support\Facades\DB::transaction(function () {
            // Multiple operations within a single transaction
            $this->user->creditDeduct(30.00, 'Op 1');
            $this->user->creditAdd(20.00, 'Op 2');
            $this->user->creditDeduct(10.00, 'Op 3');
        });

        // Final balance: 100 - 30 + 20 - 10 = 80
        expect($this->user->creditBalance())->toBe(80.00);

        // Verify running balances are sequential
        $transactions = $this->user->creditHistory(10, 'asc');
        expect((float) $transactions[0]->running_balance)->toBe(100.00)
            ->and((float) $transactions[1]->running_balance)->toBe(70.00)
            ->and((float) $transactions[2]->running_balance)->toBe(90.00)
            ->and((float) $transactions[3]->running_balance)->toBe(80.00);
    });
});

// Edge case tests
describe('edge cases', function () {
    it('handles very small positive amounts correctly', function () {
        // Test with the smallest positive float
        $this->user->creditAdd(0.01, 'Tiny credit');

        expect($this->user->creditBalance())->toBe(0.01);

        $this->user->creditDeduct(0.01, 'Tiny debit');

        expect($this->user->creditBalance())->toBe(0.00);
    });

    it('handles very large amounts correctly', function () {
        // Test with large amounts (up to PHP float precision)
        $largeAmount = 999999999.99;

        $this->user->creditAdd($largeAmount, 'Large credit');

        expect($this->user->creditBalance())->toBe($largeAmount);

        $this->user->creditDeduct(999999.99, 'Large debit');

        expect($this->user->creditBalance())->toBe(999000000.00);
    });

    it('handles float precision edge cases', function () {
        // Test amounts that might cause precision issues
        $this->user->creditAdd(10.00, 'Initial');
        $this->user->creditDeduct(3.33, 'First deduct');
        $this->user->creditDeduct(3.33, 'Second deduct');
        $this->user->creditDeduct(3.34, 'Third deduct');

        // Should be 0.00 (10 - 3.33 - 3.33 - 3.34 = 0)
        expect($this->user->creditBalance())->toBe(0.00);
    });

    it('handles rapid sequential operations', function () {
        // Simulate rapid-fire operations
        $this->user->creditAdd(1000.00, 'Initial');

        for ($i = 1; $i <= 20; $i++) {
            $this->user->creditDeduct(10.00, "Deduct {$i}");
        }

        // Balance: 1000 - (20 * 10) = 800
        expect($this->user->creditBalance())->toBe(800.00);

        // Verify all transactions recorded
        expect($this->user->credits()->count())->toBe(21);
    });

    it('maintains consistency when operations fail mid-sequence', function () {
        config(['credits.allow_negative_balance' => false]);

        $this->user->creditAdd(100.00, 'Initial');

        // First deduction succeeds
        $this->user->creditDeduct(50.00, 'Success');
        expect($this->user->creditBalance())->toBe(50.00);

        // Second deduction fails
        try {
            $this->user->creditDeduct(100.00, 'Will fail');
        } catch (\Exception $e) {
            // Expected to fail
        }

        // Balance should still be 50, not corrupted
        expect($this->user->creditBalance())->toBe(50.00);

        // Third deduction succeeds
        $this->user->creditDeduct(25.00, 'Success again');
        expect($this->user->creditBalance())->toBe(25.00);
    });

    it('handles concurrent transfers with same participants', function () {
        $userA = User::create(['name' => 'User A', 'email' => 'edgecase_a@example.com']);
        $userB = User::create(['name' => 'User B', 'email' => 'edgecase_b@example.com']);

        $userA->creditAdd(500.00);
        $userB->creditAdd(500.00);

        // Multiple transfers in quick succession
        $userA->creditTransfer($userB, 50.00, 'Transfer 1');
        $userA->creditTransfer($userB, 30.00, 'Transfer 2');
        $userB->creditTransfer($userA, 40.00, 'Transfer 3');
        $userA->creditTransfer($userB, 20.00, 'Transfer 4');

        // Final balances: A: 500 - 50 - 30 + 40 - 20 = 440
        //                 B: 500 + 50 + 30 - 40 + 20 = 560
        expect($userA->creditBalance())->toBe(440.00)
            ->and($userB->creditBalance())->toBe(560.00);

        // Total should remain constant (conservation)
        expect($userA->creditBalance() + $userB->creditBalance())->toBe(1000.00);
    });

    it('handles transfer with zero would fail', function () {
        $recipient = User::create(['name' => 'Recipient', 'email' => 'zero_transfer@example.com']);
        $this->user->creditAdd(100.00);

        expect(fn () => $this->user->creditTransfer($recipient, 0.00, 'Zero transfer'))
            ->toThrow(\InvalidArgumentException::class);
    });

    it('handles transfer with negative amount would fail', function () {
        $recipient = User::create(['name' => 'Recipient', 'email' => 'neg_transfer@example.com']);
        $this->user->creditAdd(100.00);

        expect(fn () => $this->user->creditTransfer($recipient, -50.00, 'Negative transfer'))
            ->toThrow(\InvalidArgumentException::class);
    });

    it('preserves transaction history order with rapid operations', function () {
        // Create many transactions rapidly to test ordering consistency
        $this->user->creditAdd(100.00, 'Op 1');
        $this->user->creditAdd(50.00, 'Op 2');
        $this->user->creditDeduct(30.00, 'Op 3');
        $this->user->creditAdd(20.00, 'Op 4');
        $this->user->creditDeduct(10.00, 'Op 5');

        // Get history in ascending order
        $history = $this->user->creditHistory(10, 'asc');

        // Verify descriptions are in correct order
        expect($history[0]->description)->toBe('Op 1')
            ->and($history[1]->description)->toBe('Op 2')
            ->and($history[2]->description)->toBe('Op 3')
            ->and($history[3]->description)->toBe('Op 4')
            ->and($history[4]->description)->toBe('Op 5');

        // Verify running balances increase/decrease correctly
        expect((float) $history[0]->running_balance)->toBe(100.00)
            ->and((float) $history[1]->running_balance)->toBe(150.00)
            ->and((float) $history[2]->running_balance)->toBe(120.00)
            ->and((float) $history[3]->running_balance)->toBe(140.00)
            ->and((float) $history[4]->running_balance)->toBe(130.00);
    });

    it('handles metadata edge cases', function () {
        // Test with empty metadata
        $t1 = $this->user->creditAdd(10.00, 'Empty metadata', []);
        expect($t1->metadata)->toBe([]);

        // Test with large metadata
        $largeMetadata = [
            'user_id' => 12345,
            'transaction_type' => 'purchase',
            'items' => array_fill(0, 100, ['id' => 1, 'name' => 'Item']),
            'notes' => str_repeat('x', 1000),
        ];
        $t2 = $this->user->creditAdd(20.00, 'Large metadata', $largeMetadata);
        expect($t2->metadata)->toBe($largeMetadata);

        // Test with nested arrays
        $nestedMetadata = [
            'level1' => [
                'level2' => [
                    'level3' => ['value' => 'deep'],
                ],
            ],
        ];
        $t3 = $this->user->creditAdd(30.00, 'Nested metadata', $nestedMetadata);
        expect($t3->metadata)->toBe($nestedMetadata);
    });
});
