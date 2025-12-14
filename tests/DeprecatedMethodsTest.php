<?php

use Climactic\Credits\Tests\TestModels\User;

beforeEach(function () {
    /** @var \Climactic\Credits\Tests\TestCase $this */
    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);
});

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
