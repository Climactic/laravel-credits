<?php

use Climactic\Credits\Tests\TestModels\User;

beforeEach(function () {
    /** @var \Climactic\Credits\Tests\TestCase $this */
    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);
});

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
