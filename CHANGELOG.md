# Changelog

All notable changes to this package are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the package uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- First release: OAuth with automatic token refresh, accounts (business included), balance, pots, transactions, feed items, attachments, receipts and webhooks.
- Artisan commands: `monzo:authorize`, `monzo:whoami`, `monzo:accounts`, `monzo:refresh`, `monzo:webhooks` and `monzo:logout`.
- Encrypted file and cache token stores, plus support for your own `TokenStore`.
