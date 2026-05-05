# bnzo/livewire-tmp-cleanup

Schedule-driven cleanup of Livewire temporary uploads on S3-compatible storage. The drop-in replacement for `livewire:configure-s3-upload-cleanup` when you're on Laravel Cloud + Cloudflare R2.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/bnzo/livewire-tmp-cleanup.svg?style=flat-square)](https://packagist.org/packages/bnzo/livewire-tmp-cleanup)
[![Tests](https://img.shields.io/github/actions/workflow/status/bnzo/livewire-tmp-cleanup/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/bnzo/livewire-tmp-cleanup/actions/workflows/tests.yml)
[![License](https://img.shields.io/github/license/bnzo/livewire-tmp-cleanup.svg?style=flat-square)](https://github.com/bnzo/livewire-tmp-cleanup/blob/main/LICENSE.md)

## Why this exists

Laravel Vapor automatically purges its `tmp/` S3 prefix every 24 hours via an S3 lifecycle rule. **Laravel Cloud + Cloudflare R2 has no equivalent**, and `php artisan livewire:configure-s3-upload-cleanup` fails against R2 with `MalformedXML` because R2's `PutBucketLifecycleConfiguration` schema differs from what Livewire emits.

Worse: Laravel Cloud users do not have access to the Cloudflare account API token needed to manage R2 lifecycle rules through any path — S3-compatible API, REST API, Wrangler, or dashboard. The lifecycle-rule approach is **physically unavailable** to them, not just inconvenient.

If you found this package, you probably found it after spending an evening fighting `MalformedXML` errors. You're in the right place. See livewire/livewire#8543 for the canonical thread (with the Laravel Cloud staff response confirming the gap), and livewire/livewire#9305 for the AWS S3 duplicate.

This package does one thing: it deletes files older than N hours from a configured Laravel disk under a configured directory, on a schedule. It is Livewire-aware (it reads Livewire's config by default) but not Livewire-coupled — it works with any S3-compatible disk and any temp directory.

## Installation

```bash
composer require bnzo/livewire-tmp-cleanup
```

That's it. Auto-discovery wires the service provider, registers the Artisan command, and schedules the cleanup daily.

## Usage — zero config

By default the package schedules `livewire-tmp:clean` to run **daily** at midnight on your Laravel scheduler with `onOneServer()` and `withoutOverlapping()`. As long as Laravel Cloud's Scheduler toggle is enabled on your App compute cluster (or your VPS has a `* * * * * php artisan schedule:run` cron), you're done.

The defaults assume:

- Disk: whatever `livewire.temporary_file_upload.disk` resolves to (falls back to `filesystems.default`, then `s3`).
- Directory: whatever `livewire.temporary_file_upload.directory` resolves to (defaults to `livewire-tmp`).
- Age cutoff: 24 hours.

If those match your app, **stop reading and ship it**.

## Usage — manual scheduling

If you want full control over when the task runs (e.g. hourly instead of daily, or a specific minute offset), opt out of the auto-schedule and register manually:

```env
LIVEWIRE_TMP_CLEANUP_SCHEDULE=false
```

Then in `routes/console.php`:

```php
use Bnzo\LivewireTmpCleanup\LivewireTmpCleanup;

LivewireTmpCleanup::register()->hourly();
```

`register()` returns a `Schedule\Event` already configured with `onOneServer()`, `withoutOverlapping(60)`, and `runInBackground()`. You only need to chain a frequency.

## Configuration

Publish the config if you need to commit values rather than using env vars:

```bash
php artisan vendor:publish --tag=livewire-tmp-cleanup-config
```

| Key | Env var | Default | Notes |
|---|---|---|---|
| `disk` | `LIVEWIRE_TMP_CLEANUP_DISK` | `null` (auto) | Resolves to `livewire.temporary_file_upload.disk` → `filesystems.default` → `s3`. |
| `directory` | `LIVEWIRE_TMP_CLEANUP_DIRECTORY` | `null` (auto) | Resolves to `livewire.temporary_file_upload.directory` → `livewire-tmp`. |
| `hours` | `LIVEWIRE_TMP_CLEANUP_HOURS` | `24` | Files older than this are deleted. Refuses values < 1. |
| `schedule` | `LIVEWIRE_TMP_CLEANUP_SCHEDULE` | `'daily'` | Set to `false` to opt out and self-register. Whitelisted values: `everyMinute`, `everyTwoMinutes`, `everyFiveMinutes`, `everyTenMinutes`, `everyFifteenMinutes`, `everyThirtyMinutes`, `hourly`, `daily`, `weekly`, `monthly`. Unknown values silently fall back to `daily`. |

## Configuration cookbook

### Default Livewire setup

You configured Livewire to use the `s3` disk for temp uploads (Laravel Cloud's default). Don't touch anything. The package picks it up.

### Migrated from Vapor

If you followed Dwight Watson's recommendation and set `livewire.temporary_file_upload.directory = 'tmp'` so existing Vapor temp files would still be honored after migration, the package will read that automatically. No action needed unless you want to override:

```env
LIVEWIRE_TMP_CLEANUP_DIRECTORY=tmp
```

### Multiple Livewire apps on Laravel Cloud

Install the package in each app rather than centralizing. Each app has its own scheduler, its own disk binding, and its own bucket. A central cleanup service would need cross-app credentials and would tightly couple deploys — not worth it.

## Manual usage

```bash
# Dry-run — list what would be deleted, don't actually delete.
php artisan livewire-tmp:clean --dry-run

# Aggressive cleanup with a 1-hour cutoff (Livewire's max upload time is 5 minutes,
# so anything older than 1h is definitely orphaned).
php artisan livewire-tmp:clean --hours=1

# One-off cleanup of a different disk / directory.
php artisan livewire-tmp:clean --disk=other-bucket --directory=uploads/tmp --hours=6
```

The command prints a summary line `deleted=N skipped=N errors=N` and exits non-zero if any individual delete failed (so Laravel Cloud's task UI shows red on partial failure). Per-file errors are logged via `Log::warning` with the path and exception message.

## Why not just use a lifecycle rule?

If you can install a lifecycle rule, you should. It's free, server-side, and runs whether your scheduler does or not. This package exists because:

- **AWS S3:** `livewire:configure-s3-upload-cleanup` works. Use it.
- **Cloudflare R2 outside Laravel Cloud:** R2's S3-compatible API rejects Livewire's lifecycle XML. You can install a rule via the Cloudflare REST API or dashboard yourself. This package still works as a fallback.
- **Cloudflare R2 on Laravel Cloud:** you have no Cloudflare account access. The lifecycle-rule path is closed. **This is what the package is for.**

## Requirements

- PHP 8.2+
- Laravel 11, 12, or 13
- An S3-compatible disk (`league/flysystem-aws-s3-v3` is required as a hard dep, since that's how the disk talks to R2/S3)
- A non-`file` cache driver (database, redis, memcached) if you want `withoutOverlapping()` to work — required on multi-replica Laravel Cloud setups

## Testing

```bash
composer install
composer test
composer analyse
composer format
```

## Security

If you discover a security issue, please open a private security advisory at https://github.com/bnzo/livewire-tmp-cleanup/security/advisories/new rather than a public issue.

## Credits

- [bnzo](https://github.com/bnzo)
- The participants of [livewire/livewire#8543](https://github.com/livewire/livewire/issues/8543) who surfaced the problem and confirmed the Laravel Cloud constraint
- All Livewire + Laravel Cloud users who lost an evening to `MalformedXML`

## License

The MIT License (MIT). See [LICENSE.md](https://github.com/bnzo/livewire-tmp-cleanup/blob/main/LICENSE.md).
