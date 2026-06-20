<?php

declare(strict_types=1);

use App\Jobs\FifoTickJob;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Schedule;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
});

it('registers fifo tick scheduler every second', function () {
    $events = collect(Schedule::events())
        ->filter(static fn ($event): bool => ($event->description ?? '') === 'triosense:fifo-tick-scheduler');

    expect($events)->toHaveCount(1);
});

it('dispatches fifo tick jobs for active locations via scheduler callback', function () {
    Bus::fake([FifoTickJob::class]);

    $event = collect(Schedule::events())
        ->first(static fn ($event): bool => ($event->description ?? '') === 'triosense:fifo-tick-scheduler');

    expect($event)->not->toBeNull();

    $event->run($this->app);

    Bus::assertDispatched(FifoTickJob::class, 3);
});
