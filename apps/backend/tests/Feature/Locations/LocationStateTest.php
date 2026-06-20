<?php

declare(strict_types=1);

use App\Models\EdgeDevice;
use App\Models\QueueEvent;
use App\Models\User;
use App\Domain\Fifo\LocationRedisKeys;
use App\Domain\Fifo\Status;
use App\Services\Fifo\LocationRedisStateWriter;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Redis;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    try {
        Redis::connection()->ping();
        Redis::connection()->flushdb();
    } catch (Throwable) {
        $this->markTestSkipped('Redis is not available for location API tests.');
    }

    $this->seed(DatabaseSeeder::class);
    CarbonImmutable::setTestNow(
        CarbonImmutable::parse('2026-06-20 06:00:00', 'Asia/Kolkata')
    );

    Sanctum::actingAs(User::query()->withoutGlobalScopes()->findOrFail(1));
    setPermissionsTeamId(1);
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
    try {
        Redis::connection()->flushdb();
    } catch (Throwable) {
        // Redis unavailable — nothing to clean up.
    }
});

it('returns live location state from redis', function () {
    app(LocationRedisStateWriter::class)->seed(3, [
        'quota' => 5000,
        'issued' => 100,
        'tokens_remaining' => 4900,
        'queue_head' => 100,
        'queue_tail' => 250,
        'status' => Status::OPEN->value,
        'issuance_rate_per_min' => 18.0,
        'arrival_rate_per_min' => 22.0,
    ]);

    $response = $this->getJson('/api/v1/locations/3/state');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.location_id', 3)
        ->assertJsonPath('data.tokens_remaining', 4900)
        ->assertJsonPath('data.queue_tail', 250)
        ->assertJsonPath('data.status', Status::OPEN->value);
});

it('rehydrates cold redis on state fetch', function () {
    $device = EdgeDevice::query()->withoutGlobalScopes()->where('location_id', 3)->firstOrFail();
    $now = CarbonImmutable::parse('2026-06-20 06:00:00', 'Asia/Kolkata');

    QueueEvent::query()->create([
        'tenant_id' => 1,
        'location_id' => 3,
        'edge_device_id' => $device->edge_device_id,
        'camera_id' => 17,
        'event_type' => 'enter',
        'occurred_at' => $now,
        'received_at' => $now,
        'track_id' => 'trk-cold-1',
        'confidence' => 0.9,
        'metadata_json' => null,
        'created_at' => $now,
    ]);

    $response = $this->getJson('/api/v1/locations/3/state');

    $response->assertOk()
        ->assertJsonPath('data.queue_tail', 1);
});

it('returns paginated queue events for a location', function () {
    $device = EdgeDevice::query()->withoutGlobalScopes()->where('location_id', 3)->firstOrFail();
    $now = CarbonImmutable::parse('2026-06-20 06:00:00', 'Asia/Kolkata');

    for ($i = 1; $i <= 3; $i++) {
        QueueEvent::query()->create([
            'tenant_id' => 1,
            'location_id' => 3,
            'edge_device_id' => $device->edge_device_id,
            'camera_id' => 17,
            'event_type' => 'enter',
            'occurred_at' => $now->addSeconds($i),
            'received_at' => $now->addSeconds($i),
            'track_id' => "trk-{$i}",
            'confidence' => 0.9,
            'metadata_json' => null,
            'created_at' => $now->addSeconds($i),
        ]);
    }

    $response = $this->getJson('/api/v1/locations/3/events?per_page=2');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(2, 'data.items')
        ->assertJsonPath('data.pagination.total', 3);
});

it('denies location state for unauthorized tenant locations', function () {
    $this->getJson('/api/v1/locations/99/state')->assertNotFound();
});

it('lists accessible locations for the authenticated operator', function () {
    $response = $this->getJson('/api/v1/locations');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(3, 'data.locations');
});
