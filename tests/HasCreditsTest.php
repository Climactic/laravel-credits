<?php

use Climactic\Credits\Tests\TestModels\User;
use Climactic\Credits\Exceptions\InsufficientCreditsException;

beforeEach(function () {
    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);
});

it('can add credits', function () {
    $transaction = $this->user->addCredits(100.00, 'Test credit');

    expect((float)$transaction->amount)->toEqual(100.00)
        ->and($transaction->type)->toBe('credit')
        ->and((float)$transaction->running_balance)->toEqual(100.00)
        ->and((float)$this->user->getCurrentBalance())->toEqual(100.00);
});

it('can deduct credits', function () {
    $this->user->addCredits(100.00);
    $transaction = $this->user->deductCredits(50.00, 'Test debit');

    expect((float)$transaction->amount)->toEqual(50.00)
        ->and($transaction->type)->toBe('debit')
        ->and((float)$transaction->running_balance)->toEqual(50.00)
        ->and((float)$this->user->getCurrentBalance())->toEqual(50.00);
});

it('prevents negative balance when configured', function () {
    config(['credits.allow_negative_balance' => false]);

    $this->user->addCredits(100.00);

    expect(fn() => $this->user->deductCredits(150.00))
        ->toThrow(InsufficientCreditsException::class);
});

it('allows negative balance when configured', function () {
    config(['credits.allow_negative_balance' => true]);

    $this->user->addCredits(100.00);
    $transaction = $this->user->deductCredits(150.00);

    expect((float)$transaction->running_balance)->toEqual(-50.00)
        ->and((float)$this->user->getCurrentBalance())->toEqual(-50.00);
});

it('can transfer credits between users', function () {
    $recipient = User::create([
        'name' => 'Recipient User',
        'email' => 'recipient@example.com',
    ]);

    $this->user->addCredits(100.00);
    $result = $this->user->transferCredits($recipient, 50.00, 'Test transfer');

    expect($result['sender_balance'])->toBe(50.00)
        ->and($result['recipient_balance'])->toBe(50.00)
        ->and($this->user->getCurrentBalance())->toBe(50.00)
        ->and($recipient->getCurrentBalance())->toBe(50.00);
});

it('can get transaction history', function () {
    $this->user->addCredits(100.00, 'First credit');
    $this->user->deductCredits(30.00, 'First debit');
    $this->user->addCredits(50.00, 'Second credit');

    $history = $this->user->getTransactionHistory(10);

    expect($history)->toHaveCount(3)
        ->and($history->first()->description)->toBe('Second credit');
});

it('can check if has enough credits', function () {
    $this->user->addCredits(100.00);

    expect($this->user->hasEnoughCredits(50.00))->toBeTrue()
        ->and($this->user->hasEnoughCredits(150.00))->toBeFalse();
});

it('can get balance as of date', function () {
    $this->user->addCredits(100.00);

    // Move time forward
    $this->travel(1)->days();

    $this->user->addCredits(50.00);

    $pastDate = now()->subDay();
    $balance = $this->user->getBalanceAsOf($pastDate);

    expect($balance)->toBe(100.00);
});

it('maintains accurate running balance', function () {
    $transactions = collect([
        $this->user->addCredits(100.00),
        $this->user->deductCredits(30.00),
        $this->user->addCredits(50.00),
        $this->user->deductCredits(20.00),
    ]);

    $expectedBalances = [100.00, 70.00, 120.00, 100.00];

    $transactions->each(function ($transaction, $index) use ($expectedBalances) {
        expect((float)$transaction->running_balance)->toEqual($expectedBalances[$index]);
    });

    expect((float)$this->user->getCurrentBalance())->toEqual(100.00);
});
