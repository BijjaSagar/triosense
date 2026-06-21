<?php

declare(strict_types=1);

use App\Domain\Fifo\LocationRedisKeys;
use App\Jobs\RehydrateLiveStateJob;
use App\Models\Location;
use App\Models\QueueEvent;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Redis;

beforeEach(function (): void {
    try {
        Redis::connection()->ping();
        Redis::connection()->flushdb();
    } catch (Throwable) {
        $this->markTestSkipped('Redis is not available for FIFO replay tests.');
    }

    $this->seed(DatabaseSeeder::class);
    CarbonImmutable::setTestNow(
        CarbonImmutable::parse('2026-06-20 06:00:00', 'Asia/Kolkata')
    );
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
    try {
        Redis::connection()->flushdb();
    } catch (Throwable) {
    }
});

it('replays a synthetic enter/issue sequence into redis', function () {
    $location = Location::query()->findOrFail(1);
    $day = '2026-06-20';

    $sequence = [
        ['enter', '2026-06-20 06:05:00'],
        ['enter', '2026-06-20 06:06:00'],
        ['enter', '2026-06-20 06:07:00'],
        ['issue', '2026-06-20 06:08:00'],
        ['issue', '2026-06-20 06:09:00'],
        ['exit', '2026-06-20 06:10:00'],
    ];

    foreach ($sequence as [$type, $occurredAt]) {
        QueueEvent::query()->create([
            'tenant_id' => $location->tenant_id,
            'location_id' => $location->location_id,
            'edge_device_id' => 1,
            'camera_id' => 1,
            'event_type' => $type,
            'occurred_at' => $occurredAt,
            'received_at' => $occurredAt,
            'track_id' => 'track-'.uniqid(),
            'confidence' => 0.92,
            'metadata_json' => ['v' => 1],
        ]);
    }

    RehydrateLiveStateJob::dispatchSync($location->location_id, $day);

    $locationId = $location->location_id;

    expect((int) Redis::get(LocationRedisKeys::queueTail($locationId)))->toBe(3)
        ->and((int) Redis::get(LocationRedisKeys::issued($locationId)))->toBe(2)
        ->and((int) Redis::get(LocationRedisKeys::queueHead($locationId)))->toBe(2);
});

it('runs triosense:replay artisan command', function () {
    $location = Location::query()->findOrFail(3);

    QueueEvent::query()->create([
        'tenant_id' => $location->tenant_id,
        'location_id' => $location->location_id,
        'edge_device_id' => 1,
        'camera_id' => 1,
        'event_type' => 'enter',
        'occurred_at' => '2026-06-20 07:00:00',
        'received_at' => '2026-06-20 07:00:00',
        'track_id' => 'replay-track-1',
        'confidence' => 0.88,
        'payload_json' => ['v' => 1],
    ]);

    $this->artisan('triosense:replay', [
        'date' => '2026-06-20',
        'location_id' => (string) $location->location_id,
    ])->assertSuccessful();

    expect((int) Redis::get(LocationRedisKeys::queueTail($location->location_id)))->toBe(1);
});
