<?php

use Climactic\Credits\Tests\TestCase;
use Climactic\Credits\Tests\TestModels\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The largest balance the schema accepts: 14 significant digits, which is also
 * the most PHP's default `precision` setting preserves when binding a float.
 */
const MAX_BALANCE = 999999999999.99;

/**
 * Read the declared type of a column, e.g. "numeric(14,2)".
 */
function columnType(string $table, string $column): string
{
    $definition = collect(Schema::getColumns($table))->firstWhere('name', $column);

    return str_replace(' ', '', strtolower($definition['type']));
}

it('declares the ledger columns with the documented precision', function () {
    /** @var TestCase $this */
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('SQLite does not report column precision.');
    }

    expect(columnType('credits', 'amount'))->toBe('numeric(14,2)')
        ->and(columnType('credits', 'running_balance'))->toBe('numeric(14,2)')
        ->and(columnType('credit_accounts', 'balance'))->toBe('numeric(14,2)');
});

it('widens the columns of an installation created before the precision bump', function () {
    // Rebuild the schema exactly as a pre-upgrade installation had it.
    Schema::dropIfExists('credit_accounts');
    Schema::dropIfExists('credits');

    Schema::create('credits', function (Blueprint $table) {
        $table->id();
        $table->morphs('creditable');
        $table->decimal('amount', 10, 2);
        $table->decimal('running_balance', 10, 2);
        $table->string('description', 255)->nullable();
        $table->string('type');
        $table->json('metadata')->nullable();
        $table->timestamps();
    });

    (include __DIR__.'/../database/migrations/create_credit_accounts_table.php.stub')->up();

    $user = User::create(['name' => 'Legacy', 'email' => 'legacy@example.com']);
    $user->creditAdd(1234.56, 'Recorded before the upgrade');

    (include __DIR__.'/../database/migrations/increase_credits_precision.php.stub')->up();

    // Existing rows survive the widening...
    expect($user->creditBalance())->toBe(1234.56);

    // ...and an amount that overflowed decimal(10,2) now fits.
    $user->creditAdd(999999999.99, 'Would not have fit before');

    expect($user->creditBalance())->toBe(1000001234.55);
});

it('stores the largest balance the schema allows', function () {
    $user = User::create(['name' => 'Whale', 'email' => 'whale@example.com']);

    $user->creditAdd(MAX_BALANCE, 'Maximum balance');

    expect($user->creditBalance())->toBe(MAX_BALANCE)
        ->and((float) $user->credits()->latest('id')->value('running_balance'))->toBe(MAX_BALANCE);
});

it('rejects a balance beyond the schema instead of silently rounding it', function () {
    /** @var TestCase $this */
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('SQLite does not enforce column precision.');
    }

    $user = User::create(['name' => 'Overflow', 'email' => 'overflow@example.com']);

    // Beyond 14 significant digits PHP has already rounded the cents away
    // before the value reaches the driver, so the write must fail rather than
    // record a number nobody asked for.
    expect(fn () => $user->creditAdd(9999999999999.99, 'Too large'))
        ->toThrow(QueryException::class);

    expect($user->creditBalance())->toBe(0.00)
        ->and($user->credits()->count())->toBe(0);
});
