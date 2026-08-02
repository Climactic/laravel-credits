<?php

namespace Climactic\Credits\Tests;

use Climactic\Credits\CreditsServiceProvider;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

/**
 * @property \Climactic\Credits\Tests\TestModels\User $user
 */
class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app)
    {
        return [
            CreditsServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', static::databaseConfiguration());

        // Set up credits config
        config()->set('credits.allow_negative_balance', false);
        config()->set('credits.table_name', 'credits');
        config()->set('credits.accounts_table_name', 'credit_accounts');
    }

    /**
     * The connection under test.
     *
     * Defaults to in-memory SQLite. Set DB_CONNECTION=pgsql (plus the usual
     * DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD) to run the suite
     * against PostgreSQL, which is the only way to exercise the row locking
     * that guards concurrent credit operations.
     *
     * @return array<string, mixed>
     */
    public static function databaseConfiguration(): array
    {
        if (getenv('DB_CONNECTION') !== 'pgsql') {
            return [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ];
        }

        return [
            'driver' => 'pgsql',
            'host' => getenv('DB_HOST') ?: '127.0.0.1',
            'port' => getenv('DB_PORT') ?: '5432',
            'database' => getenv('DB_DATABASE') ?: 'credits',
            'username' => getenv('DB_USERNAME') ?: 'credits',
            'password' => getenv('DB_PASSWORD') ?: '',
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ];
    }

    protected function defineDatabaseMigrations()
    {
        // Persistent connections keep the tables around between tests, so drop
        // them first. Harmless on the in-memory SQLite default.
        Schema::dropIfExists('credit_accounts');
        Schema::dropIfExists('credits');

        // Include the package migrations
        (include __DIR__.'/../database/migrations/create_credits_table.php.stub')->up();
        (include __DIR__.'/../database/migrations/create_credit_accounts_table.php.stub')->up();

        // Include the test migrations
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
