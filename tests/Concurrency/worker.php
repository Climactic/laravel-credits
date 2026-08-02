<?php

/**
 * Standalone credit worker used by PostgresConcurrencyTest.
 *
 * Runs in its own process against its own database connection, which is the
 * only way to produce the interleaving that a single PHP process cannot: two
 * transactions genuinely racing for the same creditable.
 *
 * Usage: php worker.php <userId> <add|deduct> <amount> <operations> <lockSleepMs> <startDelayMs>
 * Emits a single JSON line on stdout.
 */

use Climactic\Credits\Exceptions\InsufficientCreditsException;
use Climactic\Credits\Tests\TestCase;
use Climactic\Credits\Tests\TestModels\User;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;

require dirname(__DIR__, 2).'/vendor/autoload.php';

[, $userId, $operation, $amount, $operations, $lockSleepMs, $startDelayMs] = $argv + [
    null, null, 'add', '10', '1', '0', '0',
];

$container = new Container;
Container::setInstance($container);

$container->instance('config', new Repository([
    'credits' => [
        'allow_negative_balance' => (bool) getenv('CREDITS_ALLOW_NEGATIVE_BALANCE'),
        'table_name' => 'credits',
        'accounts_table_name' => 'credit_accounts',
    ],
]));

$events = new Dispatcher($container);
$events->setTransactionManagerResolver(fn () => null);
$container->instance('events', $events);

$capsule = new Capsule($container);
$capsule->addConnection(TestCase::databaseConfiguration());
$capsule->setEventDispatcher($events);
$capsule->setAsGlobal();
$capsule->bootEloquent();

$container->instance('db', $capsule->getDatabaseManager());
Facade::setFacadeApplication($container);
Model::setEventDispatcher($events);

if ((int) $lockSleepMs > 0) {
    // Widen the window between acquiring the lock and writing the new balance.
    // This is the window the lost update slips through.
    DB::listen(function (QueryExecuted $query) use ($lockSleepMs) {
        if (str_contains(strtolower($query->sql), 'for update')) {
            usleep((int) $lockSleepMs * 1000);
        }
    });
}

if ((int) $startDelayMs > 0) {
    usleep((int) $startDelayMs * 1000);
}

$user = User::findOrFail($userId);

$completed = 0;
$insufficient = 0;
$errors = [];

for ($i = 0; $i < (int) $operations; $i++) {
    try {
        $operation === 'deduct'
            ? $user->creditDeduct((float) $amount, "worker {$operation} {$i}")
            : $user->creditAdd((float) $amount, "worker {$operation} {$i}");
        $completed++;
    } catch (InsufficientCreditsException) {
        $insufficient++;
    } catch (Throwable $e) {
        $errors[] = get_class($e).': '.substr($e->getMessage(), 0, 200);
    }
}

echo json_encode([
    'operation' => $operation,
    'completed' => $completed,
    'insufficient' => $insufficient,
    'errors' => $errors,
])."\n";
