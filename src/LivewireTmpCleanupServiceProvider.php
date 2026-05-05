<?php

declare(strict_types=1);

namespace Bnzo\LivewireTmpCleanup;

use Bnzo\LivewireTmpCleanup\Console\CleanLivewireTmpCommand;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

class LivewireTmpCleanupServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/livewire-tmp-cleanup.php',
            'livewire-tmp-cleanup'
        );
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            CleanLivewireTmpCommand::class,
        ]);

        $this->publishes([
            __DIR__.'/../config/livewire-tmp-cleanup.php' => config_path('livewire-tmp-cleanup.php'),
        ], 'livewire-tmp-cleanup-config');

        $this->registerScheduledTask();
    }

    protected function registerScheduledTask(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $frequency = config('livewire-tmp-cleanup.schedule');

            if (empty($frequency) || $frequency === 'false') {
                return;
            }

            $event = $schedule->command('livewire-tmp:clean')
                ->onOneServer()
                ->withoutOverlapping(60)
                ->runInBackground();

            $allowed = [
                'everyMinute', 'everyTwoMinutes', 'everyFiveMinutes',
                'everyTenMinutes', 'everyFifteenMinutes', 'everyThirtyMinutes',
                'hourly', 'daily', 'weekly', 'monthly',
            ];

            if (is_string($frequency) && in_array($frequency, $allowed, true)) {
                $event->{$frequency}();

                return;
            }

            $event->daily();
        });
    }
}
