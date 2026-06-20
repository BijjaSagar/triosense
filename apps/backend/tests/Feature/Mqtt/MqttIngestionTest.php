<?php

declare(strict_types=1);

use App\Jobs\RehydrateLiveStateJob;
use App\Models\EdgeDevice;
use App\Models\QueueEvent;
use App\Mqtt\MqttTopicRouter;
use App\Domain\Fifo\LocationRedisKeys;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Redis;

beforeEach(function (): void {
    try {
        Redis::connection()->ping();
        Redis::connection()->flushdb();
    } catch (Throwable) {
        $this->markTestSkipped('Redis is not available for MQTT integration tests.');
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
        // Redis unavailable — nothing to clean up.
    }
});

function mqttEnterPayload(string $deviceUid = 'edge-sim-03'): string
{
    return json_encode([
        'v' => 1,
        'device_uid' => $deviceUid,
        'camera_id' => 17,
        'occurred_at' => '2026-06-20T06:42:13.123Z',
        'track_id' => 'trk-9842',
        'confidence' => 0.94,
        'metadata' => ['frame_number' => 1],
    ], JSON_THROW_ON_ERROR);
}

it('persists enter events and increments queue_tail in redis', function () {
    $router = app(MqttTopicRouter::class);

    $router->dispatch('triosense/loc/3/event/enter', mqttEnterPayload());

    expect(QueueEvent::query()->withoutGlobalScopes()->count())->toBe(1);

    $event = QueueEvent::query()->withoutGlobalScopes()->first();
    expect($event)->not->toBeNull()
        ->and($event->event_type)->toBe('enter')
        ->and($event->location_id)->toBe(3);

    expect((int) Redis::get(LocationRedisKeys::queueTail(3)))->toBe(1);
});

it('ignores malformed mqtt payloads without crashing', function () {
    $router = app(MqttTopicRouter::class);

    $router->dispatch('triosense/loc/3/event/enter', 'not-json');
    $router->dispatch('triosense/loc/3/event/enter', json_encode(['v' => 1]));

    expect(QueueEvent::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('deduplicates identical enter events', function () {
    $router = app(MqttTopicRouter::class);
    $payload = mqttEnterPayload();

    $router->dispatch('triosense/loc/3/event/enter', $payload);
    $router->dispatch('triosense/loc/3/event/enter', $payload);

    expect(QueueEvent::query()->withoutGlobalScopes()->count())->toBe(1);
    expect((int) Redis::get(LocationRedisKeys::queueTail(3)))->toBe(1);
});

it('updates edge device heartbeat and marks stale devices offline', function () {
    $router = app(MqttTopicRouter::class);

    $payload = json_encode([
        'v' => 1,
        'device_uid' => 'edge-sim-03',
        'timestamp' => '2026-06-20T06:00:30Z',
        'uptime_seconds' => 100,
        'cpu_percent' => 30.0,
        'mem_percent' => 50.0,
        'temp_celsius' => 60.0,
        'cameras' => [],
        'buffer_size' => 0,
    ], JSON_THROW_ON_ERROR);

    $router->dispatch('triosense/loc/3/edge/edge-sim-03/heartbeat', $payload);

    $device = EdgeDevice::query()->withoutGlobalScopes()->where('device_uid', 'edge-sim-03')->first();
    expect($device)->not->toBeNull()
        ->and($device->status)->toBe('online')
        ->and($device->last_heartbeat_at)->not->toBeNull();

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-20 06:02:00', 'Asia/Kolkata'));
    app(\App\Mqtt\Handlers\HeartbeatHandler::class)->markStaleDevices();

    $device->refresh();
    expect($device->status)->toBe('offline');
});

it('rehydrates redis state from todays queue events', function () {
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
        'track_id' => 'trk-1',
        'confidence' => 0.9,
        'metadata_json' => null,
        'created_at' => $now,
    ]);

    QueueEvent::query()->create([
        'tenant_id' => 1,
        'location_id' => 3,
        'edge_device_id' => $device->edge_device_id,
        'camera_id' => 18,
        'event_type' => 'issue',
        'occurred_at' => $now->addMinute(),
        'received_at' => $now->addMinute(),
        'track_id' => 'trk-2',
        'confidence' => 0.9,
        'metadata_json' => null,
        'created_at' => $now->addMinute(),
    ]);

    RehydrateLiveStateJob::dispatchSync(3);

    expect((int) Redis::get(LocationRedisKeys::queueTail(3)))->toBe(1)
        ->and((int) Redis::get(LocationRedisKeys::queueHead(3)))->toBe(1)
        ->and((int) Redis::get(LocationRedisKeys::issued(3)))->toBe(1);
});
