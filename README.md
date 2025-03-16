<div align="center">

<img src=".github/assets/laravelcredits.webp" alt="Laravel Credits" width="100%" />

# Laravel Credits

A ledger-based Laravel package for managing credit-based systems in your application. Perfect for virtual currencies, reward points, or any credit-based feature.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/climactic/laravel-credits.svg?style=flat-square)](https://packagist.org/packages/climactic/laravel-credits)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/climactic/laravel-credits/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/climactic/laravel-credits/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/climactic/laravel-credits/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/climactic/laravel-credits/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/climactic/laravel-credits.svg?style=flat-square)](https://packagist.org/packages/climactic/laravel-credits)
</div>

## Features

- 🔄 Credit transactions
- 💸 Credit transfers
- 📢 Events for adding, deducting, and transferring credits
- 💰 Balance tracking with running balance
- 📊 Transaction history
- 🔍 Point-in-time balance lookup
- 📝 Transaction metadata support
- ⚡ Efficient queries using running balance and indexes

## Installation

You can install the package via composer:

```bash
composer require climactic/laravel-credits
```

Publish and run the migrations:

```bash
php artisan vendor:publish --tag="credits-migrations"
php artisan migrate
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag="credits-config"
```

## Configuration

```php
return [
    // Allow negative balances
    'allow_negative_balance' => false,
    
    // Table name for credit transactions (change if you've updated the migration table name)
    'table_name' => 'credits',
];
```

## Usage

### Setup Your Model

Add the `HasCredits` trait to any model that should handle credits:

```php
use Climactic\Credits\Traits\HasCredits;

class User extends Model
{
    use HasCredits;
}
```

### Basic Usage

```php
// Add credits
$user->addCredits(100.00, 'Subscription Activated');

// Deduct credits
$user->deductCredits(50.00, 'Purchase Made');

// Get current balance
$balance = $user->getCurrentBalance();

// Check if user has enough credits
if ($user->hasEnoughCredits(30.00)) {
    // Proceed with transaction
}
```

### Transfers

Transfer credits between two models:

```php
$sender->transferCredits($recipient, 100.00, 'Paying to user for their service');
```

### Transaction History

```php
// Get last 10 transactions
$history = $user->getTransactionHistory();

// Get last 20 transactions in ascending order
$history = $user->getTransactionHistory(20, 'asc');
```

### Historical Balance

Get balance as of a specific date:

```php
$date = new DateTime('2023-01-01');
$balanceAsOf = $user->getBalanceAsOf($date);
```

### Metadata

Add additional information to transactions:

```php
$metadata = [
    'order_id' => 123,
    'product' => 'Premium Subscription'
];

$user->addCredits(100.00, 'Purchase', $metadata);
```

### Events

Events are fired for each credit transaction, transfer, and balance update.

The events are:

- `CreditsAdded`
- `CreditsDeducted`
- `CreditsTransferred`

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please report security vulnerabilities to [security@climactic.co](mailto:security@climactic.co).

## Sponsors

GitHub Sponsors: [@climactic](https://github.com/sponsors/climactic)

To become a title sponsor, please contact [sponsors@climactic.co](mailto:sponsors@climactic.co).

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
