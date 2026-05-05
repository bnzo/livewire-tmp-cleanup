<?php

declare(strict_types=1);

namespace Bnzo\LivewireTmpCleanup\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CleanLivewireTmpCommand extends Command
{
    /** @var string */
    protected $signature = 'livewire-tmp:clean
                            {--disk= : Disk to clean (overrides config and Livewire defaults)}
                            {--directory= : Prefix to scan (overrides config and Livewire defaults)}
                            {--hours= : Max age in hours (overrides config)}
                            {--dry-run : List what would be deleted without deleting}';

    /** @var string */
    protected $description = 'Delete stale Livewire temporary uploads from an S3-compatible disk. Drop-in replacement for the unusable livewire:configure-s3-upload-cleanup on Laravel Cloud / Cloudflare R2.';

    public function handle(): int
    {
        $disk = $this->resolveDisk();
        $directory = $this->resolveDirectory();
        $hours = $this->resolveHours();

        if ($hours < 1) {
            $this->error('Refusing to run with --hours < 1; that would delete in-progress uploads.');

            return self::INVALID;
        }

        $diskInstance = Storage::disk($disk);
        $cutoff = (int) now()->subHours($hours)->timestamp;
        $dryRun = (bool) $this->option('dry-run');

        $this->components->info(sprintf(
            '%s %s://%s/ for files older than %dh',
            $dryRun ? 'Scanning (dry-run)' : 'Cleaning',
            $disk,
            trim($directory, '/'),
            $hours
        ));

        $stats = $this->purge($diskInstance, $directory, $cutoff, $dryRun);

        $this->components->info(sprintf(
            '%s deleted=%d skipped=%d errors=%d',
            $dryRun ? 'Dry-run summary:' : 'Done.',
            $stats['deleted'],
            $stats['skipped'],
            $stats['errors']
        ));

        return $stats['errors'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array{deleted:int, skipped:int, errors:int}
     */
    protected function purge(Filesystem $disk, string $directory, int $cutoff, bool $dryRun): array
    {
        $deleted = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($disk->listContents($directory, true) as $item) {
            if ($item->isDir()) {
                continue;
            }

            $path = $item->path();

            try {
                $lastModified = $item->lastModified();

                if ($lastModified === null || $lastModified >= $cutoff) {
                    $skipped++;

                    continue;
                }

                if ($dryRun) {
                    $this->line(sprintf(
                        '  would delete: %s (mtime %s)',
                        $path,
                        date('c', $lastModified)
                    ));
                    $deleted++;

                    continue;
                }

                $disk->delete($path);
                $deleted++;
            } catch (Throwable $e) {
                $errors++;

                Log::warning('livewire-tmp:clean failed to delete object', [
                    'path' => $path,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return ['deleted' => $deleted, 'skipped' => $skipped, 'errors' => $errors];
    }

    protected function resolveDisk(): string
    {
        $option = $this->option('disk');

        if (is_string($option) && $option !== '') {
            return $option;
        }

        return $this->firstString([
            config('livewire-tmp-cleanup.disk'),
            config('livewire.temporary_file_upload.disk'),
            config('filesystems.default'),
        ], 's3');
    }

    protected function resolveDirectory(): string
    {
        $option = $this->option('directory');

        if (is_string($option) && $option !== '') {
            return $option;
        }

        return $this->firstString([
            config('livewire-tmp-cleanup.directory'),
            config('livewire.temporary_file_upload.directory'),
        ], 'livewire-tmp');
    }

    protected function resolveHours(): int
    {
        $option = $this->option('hours');

        if (is_numeric($option)) {
            return (int) $option;
        }

        $configured = config('livewire-tmp-cleanup.hours', 24);

        return is_numeric($configured) ? (int) $configured : 24;
    }

    /**
     * @param  array<int, mixed>  $candidates
     */
    private function firstString(array $candidates, string $fallback): string
    {
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return $fallback;
    }
}
