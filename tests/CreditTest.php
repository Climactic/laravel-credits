<?php

use Climactic\Credits\Models\Credit;
use Climactic\Credits\Tests\TestModels\User;

beforeEach(function () {
    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);
});

it('can create a credit transaction', function () {
    $credit = Credit::create([
        'creditable_type' => User::class,
        'creditable_id' => $this->user->id,
        'amount' => 100.00,
        'running_balance' => 100.00,
        'description' => 'Test credit',
        'type' => 'credit',
        'metadata' => ['source' => 'test'],
    ]);

    expect($credit)->toBeInstanceOf(Credit::class)
        ->and((float) $credit->amount)->toEqual(100.00)
        ->and($credit->type)->toBe('credit')
        ->and($credit->metadata)->toBe(['source' => 'test'])
        ->and($credit->creditable)->toBeInstanceOf(User::class);
});

it('casts amount and running_balance as decimals', function () {
    $credit = Credit::create([
        'creditable_type' => User::class,
        'creditable_id' => $this->user->id,
        'amount' => '100.50',
        'running_balance' => '100.50',
        'type' => 'credit',
    ]);

    expect((float) $credit->amount)->toEqual(100.50)
        ->and((float) $credit->running_balance)->toEqual(100.50);
});

it('casts metadata as array', function () {
    $credit = Credit::create([
        'creditable_type' => User::class,
        'creditable_id' => $this->user->id,
        'amount' => 100,
        'running_balance' => 100,
        'type' => 'credit',
        'metadata' => ['key' => 'value'],
    ]);

    expect($credit->metadata)->toBeArray()
        ->and($credit->metadata)->toBe(['key' => 'value']);
});

it('has correct table name from config', function () {
    config(['credits.table_name' => 'custom_credits']);
    $credit = new Credit;

    expect($credit->getTable())->toBe('custom_credits');
});

it('belongs to creditable model', function () {
    $credit = Credit::create([
        'creditable_type' => User::class,
        'creditable_id' => $this->user->id,
        'amount' => 100,
        'running_balance' => 100,
        'type' => 'credit',
    ]);

    expect($credit->creditable)->toBeInstanceOf(User::class)
        ->and($credit->creditable->id)->toBe($this->user->id);
});
