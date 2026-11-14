# Changelog

All notable changes to this package are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the package uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [1.2.1] - 2026-11-14

### Fixed

- Installs alongside Guzzle 8, which Laravel 13 apps can have. The package only uses Guzzle through Laravel's HTTP client, so `guzzlehttp/guzzle` now allows `^7.8|^8.0`.

## [1.2.0] - 2026-11-14

### Changed

- The `database` token store is now the default, and the package runs its own migration for it, so `php artisan migrate` is all it needs. Set `MONZO_TOKEN_STORE=file` to keep the token in a file. Set `MONZO_RUN_MIGRATIONS=false` if you publish the migration to change it.
- A missing `monzo_tokens` table now says to run `php artisan migrate` rather than raising a bare SQL error.
- The package runs its `monzo_tokens` migration only when the `database` driver is chosen, so an app keeping the token in its own table doesn't get an empty one.
- A token store class that doesn't exist, or doesn't implement `TokenStore`, now raises a `ConfigurationException` that names it.

### Added

- Example files, and README steps, for keeping the Monzo token in an app's own OAuth integrations table beside other providers' tokens, in `examples/oauth-integrations`.

## [1.1.0] - 2026-11-14

### Added

- A `database` token store, for apps where more than one server or container calls Monzo without a shared disk. Publish its migration with `php artisan vendor:publish --tag=monzo-migrations`.

### Documentation

- When to use the `database` token store instead of the default file store, and that the refresh lock needs a shared cache store in the same setup.
- The webhook secret is part of the URL, so it appears in access logs and error trackers. How to keep it out of them, and how to rotate it.

## [1.0.0] - 2026-11-14

### Added

- First release: OAuth with automatic token refresh, accounts (business included), balance, pots, transactions, feed items, attachments, receipts and webhooks.
- Artisan commands: `monzo:authorize`, `monzo:whoami`, `monzo:accounts`, `monzo:refresh`, `monzo:webhooks` and `monzo:logout`.
- Encrypted file and cache token stores, plus support for your own `TokenStore`.

[Unreleased]: https://github.com/osaris-uk/monzo/compare/1.2.1...HEAD
[1.2.1]: https://github.com/osaris-uk/monzo/compare/1.2.0...1.2.1
[1.2.0]: https://github.com/osaris-uk/monzo/compare/1.1.0...1.2.0
[1.1.0]: https://github.com/osaris-uk/monzo/compare/1.0.0...1.1.0
[1.0.0]: https://github.com/osaris-uk/monzo/releases/tag/1.0.0
