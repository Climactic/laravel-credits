# Changelog

All notable changes to `laravel-credits` will be documented in this file.

## Unreleased

### Fixed

- **Concurrent `creditAdd()`/`creditDeduct()` on the same creditable could silently lose an update.** Both methods locked the latest ledger row, which moves with every insert: a writer blocked on that lock woke up holding the row that used to be latest, never saw the entry committed ahead of it, and computed its new balance from a stale value. No exception was raised, so the built-in transaction retry could not help. Under `allow_negative_balance = false` this also let concurrent deductions overdraw the balance.

  Balances now live in a new `credit_accounts` table, one row per creditable, and every mutation locks that row instead. Because the row exists exactly once and never moves, concurrent writers are properly serialized.

- **Amounts overflowed the schema well below the values the package advertised.** `amount` and `running_balance` were `decimal(10,2)`, capping a balance at 99,999,999.99. They are now `decimal(14,2)`, raising the ceiling to 999,999,999,999.99 — the largest value PHP's default `precision` setting still converts to a string without dropping the cents. SQLite ignores column precision, which is why this never surfaced in the test suite.

- **Numeric metadata comparisons returned the wrong rows.** `whereMetadata('order_value', '>', 50)` compared the extracted JSON value as text, so on PostgreSQL '100' and '200' sorted below '50' and were silently excluded; on SQLite any text value ranked above every number and was silently included. Ordering comparisons against a numeric value are now cast to a number, with non-numeric values excluded rather than aborting the query.

### Added

- `credit_accounts` table and the `creditAccount()` relationship. The migration backfills balances from the existing ledger, so upgrades need nothing beyond `php artisan vendor:publish --tag="credits-migrations" && php artisan migrate`.
- `increase_credits_precision` migration, which widens the amount columns of installations created before this release.
- `credits.accounts_table_name` config option.
- Concurrency tests that drive the package from separate processes against a real PostgreSQL server.
- The test suite can now run against PostgreSQL by setting `DB_CONNECTION=pgsql` and the usual `DB_*` variables.

### Changed

- `creditBalance()` reads the account row (an indexed single-row lookup) instead of sorting the ledger, falling back to the ledger when no account row exists yet.
- `creditAdd()`, `creditDeduct()` and `creditTransfer()` open their transaction on the model's own connection rather than the default one.
- `creditTransfer()` now throws an `InvalidArgumentException` when sender and recipient are on different connections. A transaction only covers one connection, so the recipient's half used to commit outside it — a rollback would debit the sender and credit nobody.

## v1.4.2 - 2026-03-26

### What's Changed

* feat: add laravel 13 support by @ludioao in https://github.com/Climactic/laravel-credits/pull/19

### New Contributors

* @ludioao made their first contribution in https://github.com/Climactic/laravel-credits/pull/19

**Full Changelog**: https://github.com/Climactic/laravel-credits/compare/v1.4.1...v1.4.2

## v1.4.1 - 2026-01-02

- Added Laravel Boost Guidelines

**Full Changelog**: https://github.com/Climactic/laravel-credits/compare/v1.4.0...v1.4.1

## v1.4.0 - 2025-12-14

### What's Changed

* feat: add orWhere metadata query methods and split test files by @adiologydev in https://github.com/Climactic/laravel-credits/pull/18

**Full Changelog**: https://github.com/Climactic/laravel-credits/compare/v1.3.3...v1.4.0

## v1.3.3 - 2025-10-30

### What's Changed

* feat: query credits based on metadata by @adiologydev in https://github.com/Climactic/laravel-credits/pull/16

**Full Changelog**: https://github.com/Climactic/laravel-credits/compare/v1.3.2...v1.3.3

## v1.3.2 - 2025-10-27

### What's Changed

* chore: upgrade dependencies and readme by @adiologydev in https://github.com/Climactic/laravel-credits/pull/13

**Full Changelog**: https://github.com/Climactic/laravel-credits/compare/v1.3.1...v1.3.2

## chore: update dependencies - 2025-10-16

### What's Changed

* chore: upgrade dependencies by @adiologydev in https://github.com/Climactic/laravel-credits/pull/12

### Breaking Change

This release updates illuminate/contracts dependency to ^12.34.0 which now drops support for Laravel 10 and 11. This is a breaking change for users on earlier Laravel versions.

**Full Changelog**: https://github.com/Climactic/laravel-credits/compare/v1.3.0...v1.3.1

## v1.3.0 - 2025-10-16

### What's Changed

* Fix: ensure getCurrentBalance uses latest('id') for correct ordering by @Abeni001 in https://github.com/Climactic/laravel-credits/pull/7
* fix: deterministic queries on getBalanceAsOf by @adiologydev in https://github.com/Climactic/laravel-credits/pull/8
* refactor: rename methods for consistency and deprecate old ones by @adiologydev in https://github.com/Climactic/laravel-credits/pull/9
* Add DB locking, input validation, and post-commit event dispatch by @adiologydev in https://github.com/Climactic/laravel-credits/pull/10

### New Contributors

* @Abeni001 made their first contribution in https://github.com/Climactic/laravel-credits/pull/7

**Full Changelog**: https://github.com/Climactic/laravel-credits/compare/v1.2.2...v1.3.0

## v1.2.2 - 2025-08-19

### What's Changed

* chore(deps): bump dependabot/fetch-metadata from 2.3.0 to 2.4.0 by @dependabot[bot] in https://github.com/Climactic/laravel-credits/pull/3
* chore(deps): bump aglipanci/laravel-pint-action from 2.5 to 2.6 by @dependabot[bot] in https://github.com/Climactic/laravel-credits/pull/5

**Full Changelog**: https://github.com/Climactic/laravel-credits/compare/v1.2.1...v1.2.2

## v1.2.1 - 2025-04-01

### What's Changed

* chore(deps): bump dependabot/fetch-metadata from 2.2.0 to 2.3.0 by @dependabot in https://github.com/Climactic/laravel-credits/pull/1
* chore(deps): bump aglipanci/laravel-pint-action from 2.4 to 2.5 by @dependabot in https://github.com/Climactic/laravel-credits/pull/2
* chore: update dependencies and tests further to support laravel 12

**Full Changelog**: https://github.com/Climactic/laravel-credits/compare/v1.1.1...v1.2.1

## v1.2.0 - 2025-01-13

feat: add transaction IDs to events
chore: added tests for events

## v1.1.0 - 2024-11-29

feat: events for credit added, deducted, and transferred.

**Full Changelog**: https://github.com/Climactic/laravel-credits/compare/v1.0.1...v1.1.0

## v1.0.1 - 2024-11-21

- feat: `getBalanceAsOf` function now supports integer timestamps
- chore: remove unused config options (description length & required, decimal precision)
- chore: remove config values from migrations as they shouldn't be dynamic.
- chore: add typedocs for `HasCredits` trait.

## v1.0.0 - 2024-11-20

Initial Release with Core Functionality
