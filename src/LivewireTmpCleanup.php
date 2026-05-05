<?php

declare(strict_types=1);

namespace Bnzo\LivewireTmpCleanup;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\App;

class LivewireTmpCleanup
{
    /**
     * Register the cleanup as a scheduled task.
     *
     * Usage from routes/console.php:
     *
     *   use Bnzo\LivewireTmpCleanup\LivewireTmpCleanup;
     *   LivewireTmpCleanup::register()->hourly();
     *
     * The returned Event is pre-configured with onOneServer() and
     * withoutOverlapping(); chain a frequency on the returned Event.
     */
    public static function register(): Event
    {
        $schedule = App::make(Schedule::class);

        return $schedule->command('livewire-tmp:clean')
            ->onOneServer()
            ->withoutOverlapping(60)
            ->runInBackground();
    }
}
