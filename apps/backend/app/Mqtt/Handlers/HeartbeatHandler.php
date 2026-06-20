<?php

declare(strict_types=1);

namespace App\Mqtt\Handlers;

use App\Domain\Fifo\LocationRedisKeys;
use App\Models\EdgeDevice;
use App\Mqtt\Payloads\EventPayloadValidator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

final class HeartbeatHandler
{
    private const STALE_SECONDS = 30;

    public function __construct(
        private readonly EventPayloadValidator $validator,
    ) {
    }

    public function handle(int $locationId, string $deviceUid, string $payload): void
    {
        Log::debug('HeartbeatHandler.handle', [
            'location_id' => $locationId,
            'device_uid' => $deviceUid,
        ]);

        $validated = $this->validator->validateHeartbeat($payload);
        if ($validated === null) {
            return;
        }

        $device = EdgeDevice::query()
            ->withoutGlobalScopes()
            ->where('device_uid', $validated['device_uid'])
            ->where('location_id', $locationId)
            ->first();

        if ($device === null) {
            Log::warning('HeartbeatHandler.unknown_device', [
                'location_id' => $locationId,
                'device_uid' => $validated['device_uid'],
            ]);

            return;
        }

        $timestamp = $validated['timestamp'];
        $device->forceFill([
            'last_heartbeat_at' => $timestamp,
            'status' => 'online',
        ])->save();

        $hbKey = LocationRedisKeys::prefix($locationId).':edge:'.$device->edge_device_id.':hb';
        Redis::set($hbKey, (string) $timestamp->getTimestampMs());

        Log::info('HeartbeatHandler.updated', [
            'edge_device_id' => $device->edge_device_id,
            'location_id' => $locationId,
        ]);
    }

    public function markStaleDevices(): void
    {
        $cutoff = CarbonImmutable::now()->subSeconds(self::STALE_SECONDS);

        $staleCount = EdgeDevice::query()
            ->withoutGlobalScopes()
            ->where('status', 'online')
            ->where(function ($query) use ($cutoff): void {
                $query->whereNull('last_heartbeat_at')
                    ->orWhere('last_heartbeat_at', '<', $cutoff);
            })
            ->update(['status' => 'offline']);

        if ($staleCount > 0) {
            Log::warning('HeartbeatHandler.markStaleDevices', ['count' => $staleCount]);
        }
    }
}
