<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\File;

use function Pest\Laravel\artisan;

/**
 * @return array<int, Event>
 */
function scheduledCleanupEvents(): array
{
    /** @var Schedule $schedule */
    $schedule = resolve(Schedule::class);

    return array_values(array_filter(
        $schedule->events(),
        fn (Event $event): bool => str_contains((string) $event->command, 'livewire-tmp:clean')
    ));
}

it('does not register a scheduled task when schedule is false', function (): void {
    config()->set('livewire-tmp-cleanup.schedule', false);

    resolve(Schedule::class);

    expect(scheduledCleanupEvents())->toBeEmpty();
});

it('registers a daily schedule by default', function (): void {
    config()->set('livewire-tmp-cleanup.schedule', 'daily');

    resolve(Schedule::class);

    $events = scheduledCleanupEvents();

    expect($events)->toHaveCount(1);

    $event = $events[0];

    expect($event->expression)->toBe('0 0 * * *')
        ->and($event->onOneServer)->toBeTrue()
        ->and($event->withoutOverlapping)->toBeTrue();
});

it('falls back to daily when frequency is unknown', function (): void {
    config()->set('livewire-tmp-cleanup.schedule', 'every-blue-moon');

    resolve(Schedule::class);

    $events = scheduledCleanupEvents();

    expect($events)->toHaveCount(1)
        ->and($events[0]->expression)->toBe('0 0 * * *');
});

it('respects whitelisted frequencies', function (): void {
    config()->set('livewire-tmp-cleanup.schedule', 'everyFiveMinutes');

    resolve(Schedule::class);

    $events = scheduledCleanupEvents();

    expect($events)->toHaveCount(1)
        ->and($events[0]->expression)->toBe('*/5 * * * *');
});

it('treats string "false" as opt-out', function (): void {
    config()->set('livewire-tmp-cleanup.schedule', 'false');

    resolve(Schedule::class);

    expect(scheduledCleanupEvents())->toBeEmpty();
});

it('publishes config to the application config path', function (): void {
    $target = config_path('livewire-tmp-cleanup.php');

    if (File::exists($target)) {
        File::delete($target);
    }

    artisan('vendor:publish', ['--tag' => 'livewire-tmp-cleanup-config'])
        ->assertSuccessful();

    expect(File::exists($target))->toBeTrue();

    File::delete($target);
});
