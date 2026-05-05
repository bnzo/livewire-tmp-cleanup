# Changelog

All notable changes to `bnzo/livewire-tmp-cleanup` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.1.0] - 2026-05-04

### Added

- Laravel 11 support. CI matrix now covers Laravel 11/12/13 across PHP 8.2/8.3/8.4 (PHP 8.2 + Laravel 13 still excluded — Testbench 11 requires PHP 8.3+).

## [1.0.0] - 2026-05-04

### Added

- `livewire-tmp:clean` Artisan command with `--disk`, `--directory`, `--hours`, `--dry-run` options.
- `LivewireTmpCleanup::register()` helper for manual scheduling from `routes/console.php`.
- Service provider with auto-discovery and opt-out auto-schedule (default `daily`, env override via `LIVEWIRE_TMP_CLEANUP_SCHEDULE`).
- Resolver chain for disk and directory: CLI option → package config → Livewire config → framework default.
- 16 Pest tests covering deletion logic, dry-run, hour cutoff, error handling, and config fallback.
- CI matrix: PHP 8.2/8.3/8.4 × Laravel 12/13 (PHP 8.2 + Laravel 13 excluded — Testbench 11 requires PHP 8.3+).
- PHPStan level `max`, Laravel Pint, Rector with Laravel set list.

[Unreleased]: https://github.com/bnzo/livewire-tmp-cleanup/compare/v1.1.0...HEAD
[1.1.0]: https://github.com/bnzo/livewire-tmp-cleanup/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/bnzo/livewire-tmp-cleanup/releases/tag/v1.0.0
