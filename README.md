<div align="center">

<img src=".github/assets/laravelcredits.webp" alt="Laravel Credits" width="100%" />

# Laravel Credits

A ledger-based Laravel package for managing credit-based systems in your application. Perfect for virtual currencies, reward points, or any credit-based feature.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/climactic/laravel-credits.svg?style=flat-square)](https://packagist.org/packages/climactic/laravel-credits)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/climactic/laravel-credits/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/climactic/laravel-credits/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/climactic/laravel-credits/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/climactic/laravel-credits/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/climactic/laravel-credits.svg?style=flat-square)](https://packagist.org/packages/climactic/laravel-credits)
![Depfu](https://img.shields.io/depfu/dependencies/github/Climactic%2Flaravel-credits?style=flat-square)
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
$user->creditAdd(100.00, 'Subscription Activated');

// Deduct credits
$user->creditDeduct(50.00, 'Purchase Made');

// Get current balance
$balance = $user->creditBalance();

// Check if user has enough credits
if ($user->hasCredits(30.00)) {
    // Proceed with transaction
}
```

### Transfers

Transfer credits between two models:

```php
$sender->creditTransfer($recipient, 100.00, 'Paying to user for their service');
```

### Transaction History

```php
// Get last 10 transactions
$history = $user->creditHistory();

// Get last 20 transactions in ascending order
$history = $user->creditHistory(20, 'asc');
```

### Historical Balance

Get balance as of a specific date:

```php
$date = new DateTime('2023-01-01');
$balanceAsOf = $user->creditBalanceAt($date);
```

### Metadata

Add additional information to transactions:

```php
$metadata = [
    'order_id' => 123,
    'product' => 'Premium Subscription'
];

$user->creditAdd(100.00, 'Purchase', $metadata);
```

### Events

Events are fired for each credit transaction, transfer, and balance update.

The events are:

- `CreditsAdded`
- `CreditsDeducted`
- `CreditsTransferred`

## API Reference

### Available Methods

| Method | Description |
|--------|-------------|
| `creditAdd(float $amount, ?string $description = null, array $metadata = [])` | Add credits to the model |
| `creditDeduct(float $amount, ?string $description = null, array $metadata = [])` | Deduct credits from the model |
| `creditBalance()` | Get the current balance |
| `creditTransfer(Model $recipient, float $amount, ?string $description = null, array $metadata = [])` | Transfer credits to another model |
| `creditHistory(int $limit = 10, string $order = 'desc')` | Get transaction history |
| `hasCredits(float $amount)` | Check if model has enough credits |
| `creditBalanceAt(\DateTimeInterface\|int $dateTime)` | Get balance at a specific point in time |
| `credits()` | Eloquent relationship to credit transactions |

### Deprecated Methods

The following methods are deprecated and will be removed in v2.0. They still work but will trigger deprecation warnings:

| Deprecated Method | Use Instead |
|-------------------|-------------|
| `addCredits()` | `creditAdd()` |
| `deductCredits()` | `creditDeduct()` |
| `getCurrentBalance()` | `creditBalance()` |
| `transferCredits()` | `creditTransfer()` |
| `getTransactionHistory()` | `creditHistory()` |
| `hasEnoughCredits()` | `hasCredits()` |
| `getBalanceAsOf()` | `creditBalanceAt()` |
| `creditTransactions()` | `credits()` |

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

## Disclaimer
This package is not affiliated with Laravel. It's for Laravel but is not by Laravel. Laravel is a trademark of Taylor Otwell.
