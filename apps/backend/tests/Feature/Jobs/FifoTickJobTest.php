<?php

declare(strict_types=1);

use App\Broadcasting\CutoffStatusChanged;
use App\Broadcasting\LocationStateUpdated;
use App\Domain\Fifo\LocationRedisKeys;
use App\Domain\Fifo\Status;
use App\Jobs\FifoTickJob;
use App\Models\CutoffEvent;
use App\Models\Location;
use App\Services\Fifo\FifoTickService;
use App\Services\Fifo\LocationRedisStateWriter;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;

/**
 * Integration tests for the FIFO tick pipeline (Redis → calculator → DB → broadcast).
 *
 * Requires a reachable Redis instance (see phpunit.xml REDIS_* env vars).
 */

beforeEach(function (): void {
    try {
        Redis::connection()->ping();
    } catch (Throwable) {
        $this->markTestSkipped('Redis is not available for FifoTick integration tests.');
    }

    Redis::connection()->flushdb();
    $this->seed(DatabaseSeeder::class);
    CarbonImmutable::setTestNow(
        CarbonImmutable::parse('2026-06-20 06:00:00', 'Asia/Kolkata')
    );
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
    Redis::connection()->flushdb();
});

function seedLocationRedis(int $locationId, array $values): void
{
    app(LocationRedisStateWriter::class)->seed($locationId, $values);
}

function location(int $locationId = 3): Location
{
    return Location::query()->with('tenant')->findOrFail($locationId);
}

it('persists cutoff_events and broadcasts when status transitions to cutoff_declared', function () {
    Event::fake([LocationStateUpdated::class, CutoffStatusChanged::class]);

    seedLocationRedis(3, [
        'quota' => 5000,
        'issued' => 3840,
        'tokens_remaining' => 1160,
        'queue_head' => 3841,
        'queue_tail' => 5210,
        'status' => Status::OPEN->value,
        'issuance_rate_per_min' => 18.4,
        'arrival_rate_per_min' => 22.1,
    ]);

    app(FifoTickService::class)->tick(3);

    expect(CutoffEvent::query()->count())->toBe(1);

    $event = CutoffEvent::query()->first();
    expect($event)->not->toBeNull()
        ->and($event->location_id)->toBe(3)
        ->and($event->tenant_id)->toBe(1)
        ->and($event->previous_status)->toBe(Status::OPEN->value)
        ->and($event->new_status)->toBe(Status::CUTOFF_DECLARED->value)
        ->and($event->cutoff_position)->toBe(5000)
        ->and($event->mode)->toBe('shadow')
        ->and($event->reason)->toBe('queue_exceeds_remaining');

    expect(Redis::get(LocationRedisKeys::status(3)))->toBe(Status::CUTOFF_DECLARED->value);
    expect(Redis::get(LocationRedisKeys::cutoff(3)))->toBe('5000');

    Event::assertDispatched(LocationStateUpdated::class, function (LocationStateUpdated $broadcast): bool {
        return $broadcast->locationId === 3
            && $broadcast->decision->status === Status::CUTOFF_DECLARED
            && $broadcast->decision->cutoffPosition === 5000;
    });

    Event::assertDispatched(CutoffStatusChanged::class, function (CutoffStatusChanged $broadcast): bool {
        return $broadcast->locationId === 3
            && $broadcast->previousStatus === Status::OPEN
            && $broadcast->decision->status === Status::CUTOFF_DECLARED;
    });
});

it('does not persist cutoff_events when status is unchanged on consecutive ticks', function () {
    Event::fake([LocationStateUpdated::class, CutoffStatusChanged::class]);

    seedLocationRedis(3, [
        'quota' => 5000,
        'issued' => 0,
        'tokens_remaining' => 5000,
        'queue_head' => 0,
        'queue_tail' => 50,
        'status' => Status::OPEN->value,
        'issuance_rate_per_min' => 18.0,
        'arrival_rate_per_min' => 0.0,
    ]);

    $service = app(FifoTickService::class);
    $service->tick(3);
    $service->tick(3);

    expect(CutoffEvent::query()->count())->toBe(0);
    Event::assertNotDispatched(LocationStateUpdated::class);
    Event::assertNotDispatched(CutoffStatusChanged::class);
});

it('skips processing when location mode is disabled', function () {
    Event::fake([LocationStateUpdated::class, CutoffStatusChanged::class]);

    Location::query()->where('location_id', 3)->update(['mode' => 'disabled']);

    seedLocationRedis(3, [
        'quota' => 5000,
        'issued' => 4999,
        'queue_head' => 4999,
        'queue_tail' => 6000,
    ]);

    app(FifoTickService::class)->tick(3);

    expect(CutoffEvent::query()->count())->toBe(0);
    Event::assertNotDispatched(LocationStateUpdated::class);
    Event::assertNotDispatched(CutoffStatusChanged::class);
});

it('runs the fifo tick through the queue job', function () {
    Event::fake([LocationStateUpdated::class, CutoffStatusChanged::class]);

    seedLocationRedis(3, [
        'quota' => 5000,
        'issued' => 3840,
        'tokens_remaining' => 1160,
        'queue_head' => 3841,
        'queue_tail' => 5210,
        'status' => Status::OPEN->value,
        'issuance_rate_per_min' => 18.4,
        'arrival_rate_per_min' => 22.1,
    ]);

    FifoTickJob::dispatchSync(3);

    expect(CutoffEvent::query()->count())->toBe(1);
    Event::assertDispatched(LocationStateUpdated::class);
    Event::assertDispatched(CutoffStatusChanged::class);
});
