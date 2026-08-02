<?php

/**
 * Real concurrency coverage: every test here drives the package from separate
 * OS processes, each on its own connection, against a real PostgreSQL server.
 *
 * Run with:
 *   DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=5432 \
 *   DB_DATABASE=credits DB_USERNAME=credits DB_PASSWORD=secret vendor/bin/pest
 *
 * Skipped on any other driver: SQLite ignores `SELECT ... FOR UPDATE`, so it
 * cannot reproduce — or disprove — a lost update.
 */

use Climactic\Credits\Models\CreditAccount;
use Climactic\Credits\Tests\TestCase;
use Climactic\Credits\Tests\TestModels\User;
use Illuminate\Support\Facades\DB;

/**
 * Run credit workers in parallel and collect their JSON results.
 *
 * @param  array<int, array{0: int|string, 1: string, 2: float, 3: int, 4: int, 5: int}>  $specs
 * @return array<int, array<string, mixed>>
 */
function runCreditWorkers(array $specs): array
{
    $worker = __DIR__.'/Concurrency/worker.php';
    $environment = ['CREDITS_ALLOW_NEGATIVE_BALANCE' => config('credits.allow_negative_balance') ? '1' : ''];

    $processes = [];

    foreach ($specs as $spec) {
        $command = array_map('escapeshellarg', array_merge([PHP_BINARY, $worker], $spec));

        $process = proc_open(
            implode(' ', $command),
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            $environment + getenv()
        );

        $processes[] = ['process' => $process, 'pipes' => $pipes];
    }

    $results = [];

    foreach ($processes as $index => $handle) {
        $stdout = stream_get_contents($handle['pipes'][1]);
        $stderr = stream_get_contents($handle['pipes'][2]);
        proc_close($handle['process']);

        $decoded = json_decode(trim($stdout), true);

        expect($decoded)->toBeArray("worker {$index} failed: {$stdout}{$stderr}")
            ->and($decoded['errors'])->toBe([], "worker {$index} errored");

        $results[] = $decoded;
    }

    return $results;
}

/**
 * Assert every ledger row's running_balance equals the previous one plus or
 * minus its own amount — i.e. that no write was computed from a stale balance.
 */
function expectIntactLedgerChain(User $user): void
{
    $previous = 0.0;

    foreach ($user->credits()->orderBy('id')->get() as $credit) {
        $expected = $previous + ($credit->type === 'credit' ? (float) $credit->amount : -(float) $credit->amount);

        expect((float) $credit->running_balance)
            ->toBe(round($expected, 2), "ledger row {$credit->id} was computed from a stale balance");

        $previous = (float) $credit->running_balance;
    }
}

beforeEach(function () {
    /** @var TestCase $this */
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Concurrency guarantees can only be verified against PostgreSQL.');
    }

    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);
});

it('does not lose an update when a writer is blocked by another writer', function () {
    // The exact interleaving from the lost-update report: A takes the lock and
    // holds it while B queues up behind it. B must observe A's committed
    // balance, not the one it read before A ran.
    $this->user->creditAdd(100.00, 'initial seed');

    runCreditWorkers([
        //  id,               op,       amount, ops, lock sleep, start delay
        [$this->user->id, 'add', 50.00, 1, 1500, 0],
        [$this->user->id, 'deduct', 30.00, 1, 0, 400],
    ]);

    expect($this->user->creditBalance())->toBe(120.00);

    expectIntactLedgerChain($this->user);
});

it('keeps the balance correct under concurrent adds and deducts', function () {
    $this->user->creditAdd(1000.00, 'initial seed');

    $specs = [];
    foreach (range(0, 7) as $index) {
        $specs[] = [$this->user->id, $index % 2 === 0 ? 'add' : 'deduct', 10.00, 15, 0, 0];
    }

    $results = runCreditWorkers($specs);

    $added = collect($results)->where('operation', 'add')->sum('completed');
    $deducted = collect($results)->where('operation', 'deduct')->sum('completed');

    expect($this->user->creditBalance())->toBe(1000.00 + $added * 10.00 - $deducted * 10.00);

    expectIntactLedgerChain($this->user);
});

it('creates exactly one account row when writers race to create it', function () {
    // No seeding: every worker starts against a creditable that has no account
    // row yet, so they all race to create the very row they need to lock.
    $specs = [];
    foreach (range(0, 7) as $index) {
        $specs[] = [$this->user->id, 'add', 10.00, 10, 0, 0];
    }

    runCreditWorkers($specs);

    expect(CreditAccount::count())->toBe(1)
        ->and($this->user->creditBalance())->toBe(800.00);

    expectIntactLedgerChain($this->user);
});

it('never overdraws the balance under concurrent deductions', function () {
    config(['credits.allow_negative_balance' => false]);

    $this->user->creditAdd(100.00, 'initial seed');

    $specs = [];
    foreach (range(0, 5) as $index) {
        $specs[] = [$this->user->id, 'deduct', 10.00, 10, 0, 0];
    }

    $results = runCreditWorkers($specs);

    // 60 attempts at 10 each against a balance of 100: exactly 10 may succeed.
    expect(collect($results)->sum('completed'))->toBe(10)
        ->and(collect($results)->sum('insufficient'))->toBe(50)
        ->and($this->user->creditBalance())->toBe(0.00);

    expectIntactLedgerChain($this->user);
});
