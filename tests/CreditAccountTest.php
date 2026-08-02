<?php

use Climactic\Credits\Exceptions\InsufficientCreditsException;
use Climactic\Credits\Models\CreditAccount;
use Climactic\Credits\Tests\TestCase;
use Climactic\Credits\Tests\TestModels\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    /** @var TestCase $this */
    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);
});

it('creates a single account row on the first credit operation', function () {
    expect($this->user->creditAccount()->exists())->toBeFalse();

    $this->user->creditAdd(100.00);

    expect(CreditAccount::count())->toBe(1)
        ->and((float) $this->user->creditAccount()->value('balance'))->toBe(100.00);
});

it('keeps the account balance in sync with the ledger', function () {
    $this->user->creditAdd(100.00);
    $this->user->creditDeduct(30.00);
    $this->user->creditAdd(5.50);

    $ledger = (float) $this->user->credits()->latest('id')->value('running_balance');

    expect(CreditAccount::count())->toBe(1)
        ->and((float) $this->user->creditAccount()->value('balance'))->toBe(75.50)
        ->and($ledger)->toBe(75.50)
        ->and($this->user->creditBalance())->toBe(75.50);
});

it('reads the balance from the account row', function () {
    $this->user->creditAdd(100.00);

    // Point the account at a different value: creditBalance() must follow it,
    // proving the balance comes from the account and not from the ledger.
    $this->user->creditAccount()->update(['balance' => 42.00]);

    expect($this->user->creditBalance())->toBe(42.00);
});

it('falls back to the ledger when no account row exists', function () {
    $this->user->creditAdd(100.00);
    $this->user->creditDeduct(25.00);

    // Simulate a ledger written before the accounts table existed.
    CreditAccount::query()->delete();

    expect($this->user->creditBalance())->toBe(75.00);
});

it('seeds a missing account row from the existing ledger balance', function () {
    $this->user->creditAdd(100.00);
    CreditAccount::query()->delete();

    $this->user->creditAdd(20.00);

    expect($this->user->creditBalance())->toBe(120.00)
        ->and((float) $this->user->creditAccount()->value('balance'))->toBe(120.00)
        ->and((float) $this->user->credits()->latest('id')->value('running_balance'))->toBe(120.00);
});

it('does not create an account row for read-only operations', function () {
    expect($this->user->creditBalance())->toBe(0.00)
        ->and($this->user->hasCredits(1.00))->toBeFalse()
        ->and(CreditAccount::count())->toBe(0);
});

it('keeps one account row per creditable across many operations', function () {
    $other = User::create(['name' => 'Other', 'email' => 'other@example.com']);

    foreach (range(1, 10) as $i) {
        $this->user->creditAdd(10.00);
        $other->creditAdd(5.00);
    }

    expect(CreditAccount::count())->toBe(2)
        ->and($this->user->creditBalance())->toBe(100.00)
        ->and($other->creditBalance())->toBe(50.00);
});

it('updates both account rows on a transfer', function () {
    $recipient = User::create(['name' => 'Recipient', 'email' => 'recipient@example.com']);

    $this->user->creditAdd(100.00);
    $this->user->creditTransfer($recipient, 40.00);

    expect((float) $this->user->creditAccount()->value('balance'))->toBe(60.00)
        ->and((float) $recipient->creditAccount()->value('balance'))->toBe(40.00);
});

it('does not leave an account row behind when a deduction is rejected', function () {
    config(['credits.allow_negative_balance' => false]);

    expect(fn () => $this->user->creditDeduct(10.00))
        ->toThrow(InsufficientCreditsException::class);

    expect(CreditAccount::count())->toBe(0)
        ->and($this->user->credits()->count())->toBe(0);
});

it('backfills account balances from an existing ledger when migrating', function () {
    $other = User::create(['name' => 'Other', 'email' => 'other@example.com']);

    $this->user->creditAdd(100.00);
    $this->user->creditDeduct(40.00);
    $other->creditAdd(250.00);

    // Roll the accounts table back to the state of a pre-upgrade installation:
    // a populated ledger and no accounts table at all.
    $migration = include __DIR__.'/../database/migrations/create_credit_accounts_table.php.stub';
    $migration->down();

    expect(DB::getSchemaBuilder()->hasTable('credit_accounts'))->toBeFalse();

    $migration->up();

    expect(CreditAccount::count())->toBe(2)
        ->and($this->user->creditBalance())->toBe(60.00)
        ->and($other->creditBalance())->toBe(250.00);
});
