# Changelog

All notable changes to `laravel-credits` will be documented in this file.

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
