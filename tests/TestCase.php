<?php

declare(strict_types=1);

namespace Bnzo\LivewireTmpCleanup\Tests;

use Bnzo\LivewireTmpCleanup\LivewireTmpCleanupServiceProvider;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LivewireTmpCleanupServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app->make(Repository::class)->set('filesystems.disks.test-disk', [
            'driver' => 'local',
            'root' => storage_path('framework/testing/disks/test-disk'),
        ]);

        $app->make(Repository::class)->set('filesystems.disks.alt-disk', [
            'driver' => 'local',
            'root' => storage_path('framework/testing/disks/alt-disk'),
        ]);

        $app->make(Repository::class)->set('livewire-tmp-cleanup.disk', 'test-disk');
        $app->make(Repository::class)->set('livewire-tmp-cleanup.directory', 'livewire-tmp');
        $app->make(Repository::class)->set('livewire-tmp-cleanup.hours', 24);
        $app->make(Repository::class)->set('livewire-tmp-cleanup.schedule', false);
    }
}
