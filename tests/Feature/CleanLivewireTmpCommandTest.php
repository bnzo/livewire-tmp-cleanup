<?php

declare(strict_types=1);

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

use function Pest\Laravel\artisan;

beforeEach(function (): void {
    config()->set('livewire-tmp-cleanup.disk', 'test-disk');
    config()->set('livewire-tmp-cleanup.directory', 'livewire-tmp');
    config()->set('livewire-tmp-cleanup.hours', 24);
});

it('deletes files older than the cutoff', function (): void {
    $disk = Storage::fake('test-disk');

    $disk->put('livewire-tmp/old.png', 'old');
    $disk->put('livewire-tmp/new.png', 'new');

    touch($disk->path('livewire-tmp/old.png'), now()->subHours(25)->timestamp);
    touch($disk->path('livewire-tmp/new.png'), now()->subMinutes(10)->timestamp);

    artisan('livewire-tmp:clean')->assertSuccessful();

    expect($disk->exists('livewire-tmp/old.png'))->toBeFalse()
        ->and($disk->exists('livewire-tmp/new.png'))->toBeTrue();
});

it('does nothing in dry-run mode', function (): void {
    $disk = Storage::fake('test-disk');
    $disk->put('livewire-tmp/old.png', 'old');
    touch($disk->path('livewire-tmp/old.png'), now()->subHours(25)->timestamp);

    artisan('livewire-tmp:clean', ['--dry-run' => true])
        ->expectsOutputToContain('would delete: livewire-tmp/old.png')
        ->assertSuccessful();

    expect($disk->exists('livewire-tmp/old.png'))->toBeTrue();
});

it('honors --hours override', function (): void {
    $disk = Storage::fake('test-disk');
    $disk->put('livewire-tmp/recent.png', 'x');
    touch($disk->path('livewire-tmp/recent.png'), now()->subMinutes(90)->timestamp);

    artisan('livewire-tmp:clean', ['--hours' => 1])->assertSuccessful();

    expect($disk->exists('livewire-tmp/recent.png'))->toBeFalse();
});

it('refuses --hours=0', function (): void {
    $disk = Storage::fake('test-disk');
    $disk->put('livewire-tmp/old.png', 'old');
    touch($disk->path('livewire-tmp/old.png'), now()->subHours(25)->timestamp);

    artisan('livewire-tmp:clean', ['--hours' => 0])
        ->assertExitCode(SymfonyCommand::INVALID);

    expect($disk->exists('livewire-tmp/old.png'))->toBeTrue();
});

it('honors --disk and --directory overrides', function (): void {
    $primary = Storage::fake('test-disk');
    $alt = Storage::fake('alt-disk');

    $primary->put('livewire-tmp/should-stay.png', 'x');
    touch($primary->path('livewire-tmp/should-stay.png'), now()->subHours(25)->timestamp);

    $alt->put('custom/should-go.png', 'x');
    touch($alt->path('custom/should-go.png'), now()->subHours(25)->timestamp);

    artisan('livewire-tmp:clean', [
        '--disk' => 'alt-disk',
        '--directory' => 'custom',
    ])->assertSuccessful();

    expect($primary->exists('livewire-tmp/should-stay.png'))->toBeTrue()
        ->and($alt->exists('custom/should-go.png'))->toBeFalse();
});

it('returns SUCCESS when nothing to delete', function (): void {
    Storage::fake('test-disk');

    artisan('livewire-tmp:clean')
        ->expectsOutputToContain('deleted=0 skipped=0 errors=0')
        ->assertSuccessful();
});

it('skips files newer than cutoff', function (): void {
    $disk = Storage::fake('test-disk');
    $disk->put('livewire-tmp/fresh.png', 'x');
    touch($disk->path('livewire-tmp/fresh.png'), now()->subHours(2)->timestamp);

    artisan('livewire-tmp:clean')
        ->expectsOutputToContain('deleted=0 skipped=1 errors=0')
        ->assertSuccessful();

    expect($disk->exists('livewire-tmp/fresh.png'))->toBeTrue();
});

it('continues on per-file errors and returns FAILURE', function (): void {
    Log::spy();

    $cutoff = now()->subHours(25)->timestamp;

    $items = (function () use ($cutoff) {
        yield new FileAttributes('livewire-tmp/fail.png', 100, null, $cutoff);
        yield new FileAttributes('livewire-tmp/ok.png', 100, null, $cutoff);
        yield new DirectoryAttributes('livewire-tmp/sub');
    })();

    $mock = Mockery::mock(Filesystem::class);
    $mock->shouldReceive('listContents')
        ->with('livewire-tmp', true)
        ->andReturn($items);
    $mock->shouldReceive('delete')
        ->with('livewire-tmp/fail.png')
        ->andThrow(new RuntimeException('boom'));
    $mock->shouldReceive('delete')
        ->with('livewire-tmp/ok.png')
        ->andReturnTrue();

    Storage::set('test-disk', $mock);

    artisan('livewire-tmp:clean')
        ->expectsOutputToContain('deleted=1 skipped=0 errors=1')
        ->assertExitCode(SymfonyCommand::FAILURE);

    Log::shouldHaveReceived('warning')
        ->with('livewire-tmp:clean failed to delete object', Mockery::on(fn (array $ctx): bool => $ctx['path'] === 'livewire-tmp/fail.png' && str_contains((string) $ctx['error'], 'boom')))
        ->once();
});

it('skips items with null lastModified', function (): void {
    $cutoff = now()->subHours(25)->timestamp;

    $items = (function () use ($cutoff) {
        yield new FileAttributes('livewire-tmp/no-mtime.png', 100);
        yield new FileAttributes('livewire-tmp/old.png', 100, null, $cutoff);
    })();

    $mock = Mockery::mock(Filesystem::class);
    $mock->shouldReceive('listContents')
        ->with('livewire-tmp', true)
        ->andReturn($items);
    $mock->shouldReceive('delete')
        ->with('livewire-tmp/old.png')
        ->andReturnTrue();

    Storage::set('test-disk', $mock);

    artisan('livewire-tmp:clean')
        ->expectsOutputToContain('deleted=1 skipped=1 errors=0')
        ->assertSuccessful();
});

it('falls back to livewire.temporary_file_upload.disk when package disk is null', function (): void {
    config()->set('livewire-tmp-cleanup.disk');
    config()->set('livewire-tmp-cleanup.directory');
    config()->set('livewire.temporary_file_upload.disk', 'alt-disk');
    config()->set('livewire.temporary_file_upload.directory', 'tmp');

    $primary = Storage::fake('test-disk');
    $alt = Storage::fake('alt-disk');

    $primary->put('livewire-tmp/stay.png', 'x');
    touch($primary->path('livewire-tmp/stay.png'), now()->subHours(25)->timestamp);

    $alt->put('tmp/go.png', 'x');
    touch($alt->path('tmp/go.png'), now()->subHours(25)->timestamp);

    artisan('livewire-tmp:clean')->assertSuccessful();

    expect($primary->exists('livewire-tmp/stay.png'))->toBeTrue()
        ->and($alt->exists('tmp/go.png'))->toBeFalse();
});
