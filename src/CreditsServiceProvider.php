<?php

namespace Climactic\Credits;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class CreditsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-credits')
            ->hasConfigFile()
            ->hasMigrations([
                'create_credits_table',
                'create_credit_accounts_table',
                'increase_credits_precision',
            ]);
    }
}
