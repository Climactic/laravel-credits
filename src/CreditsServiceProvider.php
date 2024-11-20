<?php

namespace Climactic\Credits;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Climactic\Credits\Commands\CreditsCommand;

class CreditsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-credits')
            ->hasConfigFile()
            ->hasMigration('create_credits_table');
    }
}