<?php

use Climactic\Credits\Tests\TestModels\User;

beforeEach(function () {
    /** @var \Climactic\Credits\Tests\TestCase $this */
    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);
});

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
