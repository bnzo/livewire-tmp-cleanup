<h1 align="center">Livewire Tmp Cleanup</h1>

<p align="center">Schedule-driven cleanup of Livewire temporary uploads on any S3-compatible disk.</p>

<p align="center">
<a href="https://github.com/bnzo/livewire-tmp-cleanup/actions"><img src="https://github.com/bnzo/livewire-tmp-cleanup/workflows/tests/badge.svg" alt="Tests"></a>
<a href="https://packagist.org/packages/bnzo/livewire-tmp-cleanup"><img src="https://img.shields.io/packagist/dt/bnzo/livewire-tmp-cleanup" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/bnzo/livewire-tmp-cleanup"><img src="https://img.shields.io/packagist/v/bnzo/livewire-tmp-cleanup" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/bnzo/livewire-tmp-cleanup"><img src="https://img.shields.io/packagist/l/bnzo/livewire-tmp-cleanup" alt="License"></a>
</p>

## About

Livewire's S3 temporary uploads accumulate forever unless something deletes them. This package adds a single Artisan command — `livewire-tmp:clean` — that removes files older than a configurable age from a configured disk and directory, and registers it on Laravel's scheduler for you.

- Deletes files older than N hours from a configured Laravel disk + directory.
- Auto-schedules a daily cleanup task — no boilerplate.
- Reads Livewire's `temporary_file_upload` disk and directory by default.
- Works with AWS S3, Cloudflare R2, MinIO, or any Flysystem S3 disk.
- Dry-run mode for safe inspection.
- `onOneServer()` + `withoutOverlapping()` safe for multi-replica deploys.

## Installation

```bash
composer require bnzo/livewire-tmp-cleanup
```

Auto-discovery wires the service provider, registers the Artisan command, and schedules the cleanup daily.

## Usage

### Zero config

By default the package schedules `livewire-tmp:clean` to run **daily** at midnight via Laravel's scheduler with `onOneServer()` and `withoutOverlapping()`. As long as `php artisan schedule:run` is firing every minute, you're done.

### Manual scheduling

To control the cadence yourself, opt out of the auto-schedule:

```env
LIVEWIRE_TMP_CLEANUP_SCHEDULE=false
```

Then in `routes/console.php`:

```php
use Bnzo\LivewireTmpCleanup\LivewireTmpCleanup;

LivewireTmpCleanup::register()->hourly();
```

`register()` returns a `Schedule\Event` already configured with `onOneServer()`, `withoutOverlapping(60)`, and `runInBackground()`. Chain a frequency.

### Manual run

```bash
# Dry-run — list what would be deleted, don't actually delete.
php artisan livewire-tmp:clean --dry-run

# Aggressive cleanup with a 1-hour cutoff.
php artisan livewire-tmp:clean --hours=1

# One-off cleanup of a different disk / directory.
php artisan livewire-tmp:clean --disk=other-bucket --directory=uploads/tmp --hours=6
```

The command prints `deleted=N skipped=N errors=N` and exits non-zero if any individual delete failed. Per-file errors are logged via `Log::warning`.

## Configuration

Publish the config to commit values rather than using env vars:

```bash
php artisan vendor:publish --tag=livewire-tmp-cleanup-config
```

| Key | Env var | Default | Notes |
|---|---|---|---|
| `disk` | `LIVEWIRE_TMP_CLEANUP_DISK` | `null` (auto) | Resolves to `livewire.temporary_file_upload.disk` → `filesystems.default` → `s3`. |
| `directory` | `LIVEWIRE_TMP_CLEANUP_DIRECTORY` | `null` (auto) | Resolves to `livewire.temporary_file_upload.directory` → `livewire-tmp`. |
| `hours` | `LIVEWIRE_TMP_CLEANUP_HOURS` | `24` | Files older than this are deleted. Refuses values < 1. |
| `schedule` | `LIVEWIRE_TMP_CLEANUP_SCHEDULE` | `'daily'` | Set to `false` to opt out and self-register. Whitelisted: `everyMinute`, `everyTwoMinutes`, `everyFiveMinutes`, `everyTenMinutes`, `everyFifteenMinutes`, `everyThirtyMinutes`, `hourly`, `daily`, `weekly`, `monthly`. Unknown values fall back to `daily`. |

## Compatibility

| Package | PHP    | Laravel              | Livewire    |
| ------- | ------ | -------------------- | ----------- |
| 1.x     | ^8.2   | 11.x · 12.x · 13.x   | 3.x · 4.x   |

Also requires `league/flysystem-aws-s3-v3` (hard dep) and a non-`file` cache driver (database, redis, memcached) for `withoutOverlapping()` to work across replicas.

## Testing

```bash
composer test
composer analyse
composer format
```

## Security

If you discover a security issue, please open a private security advisory at https://github.com/bnzo/livewire-tmp-cleanup/security/advisories/new rather than a public issue.

## Credits

- [bnzo](https://github.com/bnzo)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
