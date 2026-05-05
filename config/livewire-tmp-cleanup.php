<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Disk
    |--------------------------------------------------------------------------
    |
    | The Laravel filesystem disk to clean. Defaults to whatever Livewire is
    | using for temporary uploads. On Laravel Cloud this is typically the
    | "s3" disk, which is backed by Cloudflare R2.
    |
    | Set to null to use livewire.temporary_file_upload.disk at runtime.
    |
    */
    'disk' => env('LIVEWIRE_TMP_CLEANUP_DISK'),

    /*
    |--------------------------------------------------------------------------
    | Directory
    |--------------------------------------------------------------------------
    |
    | The prefix / directory under which temp uploads live. Livewire's default
    | is "livewire-tmp". If you migrated from Vapor and kept Dwight Watson's
    | recommendation (livewire.temporary_file_upload.directory = "tmp"), set
    | this to "tmp" so the package knows where to look.
    |
    | Set to null to use livewire.temporary_file_upload.directory at runtime.
    |
    */
    'directory' => env('LIVEWIRE_TMP_CLEANUP_DIRECTORY'),

    /*
    |--------------------------------------------------------------------------
    | Maximum age (hours)
    |--------------------------------------------------------------------------
    |
    | Files older than this are deleted. Vapor's mechanism uses 24 hours.
    | Livewire's max upload time is 5 minutes by default, so anything past
    | ~1 hour is effectively orphaned.
    |
    */
    'hours' => (int) env('LIVEWIRE_TMP_CLEANUP_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Schedule
    |--------------------------------------------------------------------------
    |
    | Set to false to disable automatic scheduling and register the task
    | manually. Otherwise, set a frequency:
    |
    |   "everyMinute", "everyFiveMinutes", "hourly", "daily", "weekly"
    |
    | The package always calls onOneServer()->withoutOverlapping() so it is
    | safe on multi-replica Laravel Cloud environments. Note: that requires
    | a non-file cache driver (database, redis, memcached).
    |
    */
    'schedule' => env('LIVEWIRE_TMP_CLEANUP_SCHEDULE', 'daily'),

];
