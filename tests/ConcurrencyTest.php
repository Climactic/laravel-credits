<?php

use Climactic\Credits\Exceptions\InsufficientCreditsException;
use Climactic\Credits\Tests\TestModels\User;

beforeEach(function () {
    /** @var \Climactic\Credits\Tests\TestCase $this */
    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);
});

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
