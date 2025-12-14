<div align="center">

<img src=".github/assets/laravelcredits.webp" alt="Laravel Credits" width="100%" />

# Laravel Credits

A ledger-based Laravel package for managing credit-based systems in your application. Perfect for virtual currencies, reward points, or any credit-based feature.

[![Discord](https://img.shields.io/discord/303195322514014210?style=for-the-badge)](https://discord.gg/kedWdzwwR5)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/climactic/laravel-credits.svg?style=for-the-badge)](https://packagist.org/packages/climactic/laravel-credits)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/climactic/laravel-credits/run-tests.yml?branch=main&label=tests&style=for-the-badge)](https://github.com/climactic/laravel-credits/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/climactic/laravel-credits/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=for-the-badge)](https://github.com/climactic/laravel-credits/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/climactic/laravel-credits.svg?style=for-the-badge)](https://packagist.org/packages/climactic/laravel-credits)
![Depfu](https://img.shields.io/depfu/dependencies/github/Climactic%2Flaravel-credits?style=for-the-badge)
[![Sponsor on GitHub](https://img.shields.io/badge/Sponsor-GitHub-ea4aaa?style=for-the-badge&logo=github)](https://github.com/sponsors/climactic)
[![Support on Ko-fi](https://img.shields.io/badge/Support-Ko--fi-FF5E5B?style=for-the-badge&logo=ko-fi&logoColor=white)](https://ko-fi.com/ClimacticCo)
</div>

## Table of Contents

- [Laravel Credits](#laravel-credits)
  - [Table of Contents](#table-of-contents)
  - [Features](#features)
  - [Installation](#installation)
  - [Configuration](#configuration)
    - [Database Recommendations](#database-recommendations)
  - [Usage](#usage)
    - [Setup Your Model](#setup-your-model)
    - [Basic Usage](#basic-usage)
    - [Transfers](#transfers)
    - [Transaction History](#transaction-history)
    - [Historical Balance](#historical-balance)
    - [Metadata](#metadata)
    - [Querying by Metadata](#querying-by-metadata)
      - [Metadata Key Format](#metadata-key-format)
      - [Basic Metadata Queries](#basic-metadata-queries)
      - [Advanced Metadata Queries](#advanced-metadata-queries)
      - [Chaining Multiple Metadata Conditions](#chaining-multiple-metadata-conditions)
      - [Convenience Methods](#convenience-methods)
      - [Performance Optimization](#performance-optimization)
        - [When to Optimize](#when-to-optimize)
        - [MySQL/MariaDB: Virtual Columns with Indexes](#mysqlmariadb-virtual-columns-with-indexes)
        - [PostgreSQL: GIN Indexes on JSONB](#postgresql-gin-indexes-on-jsonb)
        - [SQLite: Limited Support](#sqlite-limited-support)
        - [Choosing What to Index](#choosing-what-to-index)
        - [Best Practices](#best-practices)
    - [Events](#events)
  - [API Reference](#api-reference)
    - [Available Methods](#available-methods)
    - [Query Scopes](#query-scopes)
    - [Deprecated Methods](#deprecated-methods)
  - [Testing](#testing)
  - [Changelog](#changelog)
  - [Contributing](#contributing)
  - [Security Vulnerabilities](#security-vulnerabilities)
  - [Support This Project](#support-this-project)
    - [Sponsors](#sponsors)
  - [Star History](#star-history)
  - [License](#license)
  - [Disclaimer](#disclaimer)

## Features

- 🔄 Credit transactions
- 💸 Credit transfers
- 📢 Events for adding, deducting, and transferring credits
- 💰 Balance tracking with running balance
- 📊 Transaction history
- 🔍 Point-in-time balance lookup
- 📝 Transaction metadata support
- 🔎 Powerful metadata querying with filters and scopes
- ⚡ Efficient queries using running balance and indexes
- 🚀 Performance optimization guide for high-volume applications

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

### Database Recommendations

**Concurrency & Locking**: This package uses row-level locking (`SELECT FOR UPDATE`) to prevent race conditions during concurrent credit operations. This requires a database engine that supports proper transaction isolation and row-level locking:

- ✅ **MySQL/MariaDB**: Requires InnoDB engine (default in modern versions)
- ✅ **PostgreSQL**: Full support for row-level locking
- ⚠️ **SQLite**: Row-level locking is ignored; concurrent operations may produce incorrect results in high-concurrency scenarios

For production environments with concurrent users, we recommend using MySQL/MariaDB (InnoDB) or PostgreSQL.

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
    'product' => 'Premium Subscription',
    'user_id' => 456,
    'tags' => ['premium', 'featured']
];

$user->creditAdd(100.00, 'Purchase', $metadata);
```

### Querying by Metadata

The package provides powerful query scopes to filter transactions by metadata with built-in input validation for security.

#### Metadata Key Format

Metadata keys must follow these rules:
- Use **dot notation** for nested keys (e.g., `'user.id'`, not `'user->id'`)
- Cannot be empty or contain only whitespace
- Cannot contain quotes (`"` or `'`)
- Whitespace is automatically trimmed

```php
// ✅ Valid
$user->credits()->whereMetadata('source', 'purchase')->get();
$user->credits()->whereMetadata('user.id', 123)->get();
$user->credits()->whereMetadata('  source  ', 'test')->get(); // Trimmed automatically (not recommended)

// ❌ Invalid - will throw InvalidArgumentException
$user->credits()->whereMetadata('', 'value')->get();              // Empty key
$user->credits()->whereMetadata('data->key', 'value')->get();     // Arrow operator
$user->credits()->whereMetadata('data"key', 'value')->get();      // Contains quotes
```

#### Basic Metadata Queries

```php
// Query by simple metadata value
$purchases = $user->credits()
    ->whereMetadata('source', 'purchase')
    ->get();

// Query with comparison operators
$highValue = $user->credits()
    ->whereMetadata('order_value', '>', 100)
    ->get();

// Query by nested metadata (using dot notation)
$specificUser = $user->credits()
    ->whereMetadata('user.id', 123)
    ->get();

// Deeply nested keys (use raw where for very deep nesting)
$results = $user->credits()
    ->where('metadata->data->level1->level2->value', 'found')
    ->get();
```

#### Advanced Metadata Queries

```php
// Check if metadata array contains a value
$premium = $user->credits()
    ->whereMetadataContains('tags', 'premium')
    ->get();

// Check if metadata key exists
$withOrderId = $user->credits()
    ->whereMetadataHas('order_id')
    ->get();

// Check if metadata key is null or doesn't exist
$withoutTags = $user->credits()
    ->whereMetadataNull('tags')
    ->get();

// Query by metadata array length
$multipleTags = $user->credits()
    ->whereMetadataLength('tags', '>', 1)
    ->get();
```

#### Chaining Multiple Metadata Conditions

```php
$filtered = $user->credits()
    ->whereMetadata('source', 'purchase')
    ->whereMetadata('category', 'electronics')
    ->whereMetadataContains('tags', 'featured')
    ->where('amount', '>', 50)
    ->get();
```

#### Convenience Methods

```php
// Get credits filtered by metadata (with limit and ordering)
$purchases = $user->creditsByMetadata('source', 'purchase', limit: 20, order: 'desc');

// With comparison operators
$highValue = $user->creditsByMetadata('order_value', '>=', 100, limit: 10);

// Multiple metadata filters
$filtered = $user->creditHistoryWithMetadata([
    ['key' => 'source', 'value' => 'purchase'],
    ['key' => 'category', 'value' => 'electronics'],
    ['key' => 'tags', 'value' => 'premium', 'method' => 'contains'],
    ['key' => 'order_value', 'operator' => '>', 'value' => 50],
], limit: 25);

// Query with null values (explicitly include 'value' key)
$nullChecks = $user->creditHistoryWithMetadata([
    ['key' => 'refund_id', 'operator' => '=', 'value' => null],  // Check for null
    ['key' => 'status', 'value' => 'pending'],                   // Simple equality
], limit: 10);
```

**Note:** When using `creditHistoryWithMetadata()`, the filter array distinguishes between "two-parameter syntax" and "three-parameter syntax" by checking if the `'value'` key exists:
- If `'value'` key exists: uses three-parameter form `whereMetadata($key, $operator, $value)`
- If `'value'` key missing: uses two-parameter form `whereMetadata($key, $operator)` where operator becomes the value

This allows proper handling of null values while maintaining shorthand syntax convenience.

#### Performance Optimization

For high-volume applications querying metadata frequently, consider adding database indexes. Without indexes, metadata queries perform full table scans. With proper indexes, queries become nearly instant even with millions of records.

##### When to Optimize

- **Small datasets (< 10k transactions)**: No optimization needed
- **Medium datasets (10k - 100k)**: Consider optimization for frequently queried keys
- **Large datasets (> 100k)**: Highly recommended for any metadata queries

##### MySQL/MariaDB: Virtual Columns with Indexes

Virtual generated columns extract JSON values into indexed columns for fast queries:

```php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up()
    {
        $tableName = config('credits.table_name', 'credits');

        // Add virtual columns for frequently queried metadata keys
        DB::statement("
            ALTER TABLE {$tableName}
            ADD COLUMN metadata_source VARCHAR(255)
            GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.source'))) VIRTUAL
        ");

        DB::statement("
            ALTER TABLE {$tableName}
            ADD COLUMN metadata_order_id BIGINT
            GENERATED ALWAYS AS (JSON_EXTRACT(metadata, '$.order_id')) VIRTUAL
        ");

        // Add indexes on virtual columns
        Schema::table($tableName, function (Blueprint $table) {
            $table->index('metadata_source');
            $table->index('metadata_order_id');
        });

        // Optional: Composite indexes for common query combinations
        Schema::table($tableName, function (Blueprint $table) {
            $table->index(['metadata_source', 'created_at']);
            $table->index(['creditable_id', 'creditable_type', 'metadata_order_id']);
        });
    }

    public function down()
    {
        $tableName = config('credits.table_name', 'credits');

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropIndex(['metadata_source', 'created_at']);
            $table->dropIndex(['creditable_id', 'creditable_type', 'metadata_order_id']);
            $table->dropIndex(['metadata_source']);
            $table->dropIndex(['metadata_order_id']);
        });

        DB::statement("ALTER TABLE {$tableName} DROP COLUMN metadata_source");
        DB::statement("ALTER TABLE {$tableName} DROP COLUMN metadata_order_id");
    }
};
```

**Performance impact**: Queries go from scanning millions of rows to using index lookups (1000x+ faster).

##### PostgreSQL: GIN Indexes on JSONB

PostgreSQL's GIN (Generalized Inverted Index) provides efficient querying for all JSON operations:

```php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        $tableName = config('credits.table_name', 'credits');

        // GIN index supports all JSON operators (@>, ?, ?&, ?|, etc.)
        DB::statement("
            CREATE INDEX {$tableName}_metadata_gin_idx
            ON {$tableName} USING GIN (metadata)
        ");

        // Optional: Add specific path indexes for even faster queries
        DB::statement("
            CREATE INDEX {$tableName}_metadata_source_idx
            ON {$tableName} ((metadata->>'source'))
        ");

        DB::statement("
            CREATE INDEX {$tableName}_metadata_order_id_idx
            ON {$tableName} ((metadata->'order_id'))
        ");
    }

    public function down()
    {
        $tableName = config('credits.table_name', 'credits');

        DB::statement("DROP INDEX IF EXISTS {$tableName}_metadata_gin_idx");
        DB::statement("DROP INDEX IF EXISTS {$tableName}_metadata_source_idx");
        DB::statement("DROP INDEX IF EXISTS {$tableName}_metadata_order_id_idx");
    }
};
```

**Note**: The GIN index alone enables fast queries for all metadata keys. Path-specific indexes provide marginal additional performance.

##### SQLite: Limited Support

SQLite has limited JSON indexing capabilities. For SQLite:
- Metadata queries work but will be slower on large datasets
- Consider using a different database for production if metadata querying is critical
- JSON1 extension must be enabled (available in SQLite 3.38+)

##### Choosing What to Index

Index metadata keys that you query frequently:

```php
// If you often query by source
$user->credits()->whereMetadata('source', 'purchase')->get();
// → Index: metadata_source

// If you filter by order_id
$user->credits()->whereMetadata('order_id', 12345)->get();
// → Index: metadata_order_id

// If you query by tags array
$user->credits()->whereMetadataContains('tags', 'premium')->get();
// → MySQL: Index on virtual column for tags
// → PostgreSQL: GIN index handles this automatically
```

##### Best Practices

1. **Analyze your queries first**: Use `EXPLAIN` to identify slow queries
2. **Index selectively**: Only index frequently queried keys (each index adds storage overhead)
3. **Use composite indexes**: For queries combining metadata with other columns
4. **Test with production data**: Benchmark before and after indexing
5. **Monitor index usage**: Remove unused indexes to save storage

### Events

Events are fired for each credit transaction, transfer, and balance update.

The events are:

- `CreditsAdded`
- `CreditsDeducted`
- `CreditsTransferred`

## API Reference

### Available Methods

| Method                                                                                               | Description                                  |
| ---------------------------------------------------------------------------------------------------- | -------------------------------------------- |
| `creditAdd(float $amount, ?string $description = null, array $metadata = [])`                        | Add credits to the model                     |
| `creditDeduct(float $amount, ?string $description = null, array $metadata = [])`                     | Deduct credits from the model                |
| `creditBalance()`                                                                                    | Get the current balance                      |
| `creditTransfer(Model $recipient, float $amount, ?string $description = null, array $metadata = [])` | Transfer credits to another model            |
| `creditHistory(int $limit = 10, string $order = 'desc')`                                             | Get transaction history                      |
| `hasCredits(float $amount)`                                                                          | Check if model has enough credits            |
| `creditBalanceAt(Carbon\|DateTimeInterface\|int $dateTime)`                                          | Get balance at a specific time               |
| `credits()`                                                                                          | Eloquent relationship to credit transactions |
| `creditsByMetadata(string $key, $operator, $value = null, int $limit = 10, string $order = 'desc')`  | Get credits filtered by metadata key/value   |
| `creditHistoryWithMetadata(array $filters, int $limit = 10, string $order = 'desc')`                 | Get credits filtered by multiple metadata    |

### Query Scopes

These scopes can be used on the `credits()` relationship:

| Scope                                                  | Description                                     |
| ------------------------------------------------------ | ----------------------------------------------- |
| `whereMetadata(string $key, $operator, $value = null)` | Filter by metadata key/value                    |
| `whereMetadataContains(string $key, $value)`           | Filter where metadata array contains value      |
| `whereMetadataHas(string $key)`                        | Filter where metadata key exists                |
| `whereMetadataNull(string $key)`                       | Filter where metadata key is null/doesn't exist |
| `whereMetadataLength(string $key, $operator, $value)`  | Filter by metadata array length                 |

### Deprecated Methods

The following methods are deprecated and will be removed in v2.0. They still work but will trigger deprecation warnings:

| Deprecated Method         | Use Instead         |
| ------------------------- | ------------------- |
| `addCredits()`            | `creditAdd()`       |
| `deductCredits()`         | `creditDeduct()`    |
| `getCurrentBalance()`     | `creditBalance()`   |
| `transferCredits()`       | `creditTransfer()`  |
| `getTransactionHistory()` | `creditHistory()`   |
| `hasEnoughCredits()`      | `hasCredits()`      |
| `getBalanceAsOf()`        | `creditBalanceAt()` |
| `creditTransactions()`    | `credits()`         |

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.
You can also join our Discord server to discuss ideas and get help: [Discord Invite](https://discord.gg/kedWdzwwR5).

## Security Vulnerabilities

Please report security vulnerabilities to [security@climactic.co](mailto:security@climactic.co).

## Support This Project

Laravel Credits is free and open source, built and maintained with care. If this package has saved you development time or helped power your application, please consider supporting its continued development.

<a href="https://github.com/sponsors/climactic">
    <img src="https://img.shields.io/badge/Sponsor%20on-GitHub-ea4aaa?style=for-the-badge&logo=github" alt="Sponsor on GitHub" />
</a>
&nbsp;
<a href="https://ko-fi.com/ClimacticCo">
    <img src="https://img.shields.io/badge/Support%20on-Ko--fi-FF5E5B?style=for-the-badge&logo=ko-fi&logoColor=white" alt="Support on Ko-fi" />
</a>

### Sponsors

<!-- sponsors -->
*Your logo here* — Become a sponsor and get your logo featured in this README and on our website.
<!-- sponsors -->

**Interested in title sponsorship?** Contact us at [sponsors@climactic.co](mailto:sponsors@climactic.co) for premium placement and recognition.

## Star History

[![Star History Chart](https://api.star-history.com/svg?repos=climactic/laravel-credits&type=date&legend=top-left)](https://www.star-history.com/#climactic/laravel-credits&type=date&legend=top-left)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Disclaimer
This package is not affiliated with Laravel. It's for Laravel but is not by Laravel. Laravel is a trademark of Taylor Otwell.
