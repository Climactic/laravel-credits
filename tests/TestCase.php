<?php

namespace Climactic\Credits\Tests;

use Climactic\Credits\CreditsServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Climactic\\Credits\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            CreditsServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        // Use SQLite in memory for testing
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Set up credits config
        config()->set('credits.allow_negative_balance', false);
        config()->set('credits.decimal_precision', 2);
        config()->set('credits.table_name', 'credits');
        config()->set('credits.description.required', false);
        config()->set('credits.description.max_length', 255);
    }

    protected function defineDatabaseMigrations()
    {
        // Include the package migrations
        include_once __DIR__.'/../database/migrations/create_credits_table.php.stub';
        (include __DIR__.'/../database/migrations/create_credits_table.php.stub')->up();

        // Include the test migrations
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
