<?php

declare(strict_types=1);

namespace App\Services\Queue;

use App\Models\EdgeDevice;
use App\Models\QueueEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * Persists MQTT events to queue_events with deduplication.
 */
final class QueueEventPersister
{
    public function __construct(
        private readonly QueueEventRedisWriter $redisWriter,
    ) {
    }

    /**
     * @param  array{
     *     v: int,
     *     device_uid: string,
     *     camera_id: int|null,
     *     occurred_at: CarbonImmutable,
     *     track_id: string|null,
     *     confidence: float|null,
     *     metadata: array<string, mixed>|null
     * }  $payload
     */
    public function persist(
        int $locationId,
        string $eventType,
        array $payload,
    ): ?QueueEvent {
        $device = EdgeDevice::query()
            ->withoutGlobalScopes()
            ->where('device_uid', $payload['device_uid'])
            ->where('location_id', $locationId)
            ->first();

        if ($device === null) {
            Log::warning('QueueEventPersister.unknown_device', [
                'location_id' => $locationId,
                'device_uid' => $payload['device_uid'],
                'event_type' => $eventType,
            ]);

            return null;
        }

        $receivedAt = CarbonImmutable::now();

        if ($this->isDuplicate($device->edge_device_id, $payload, $eventType)) {
            Log::debug('QueueEventPersister.duplicate_skipped', [
                'location_id' => $locationId,
                'device_uid' => $payload['device_uid'],
                'event_type' => $eventType,
            ]);

            return null;
        }

        if ($this->redisWriter->isCold($locationId)) {
            $this->redisWriter->seedLocation($locationId, (int) $device->tenant_id);
        }

        $event = QueueEvent::query()->create([
            'tenant_id' => $device->tenant_id,
            'location_id' => $locationId,
            'edge_device_id' => $device->edge_device_id,
            'camera_id' => $payload['camera_id'],
            'event_type' => $eventType,
            'occurred_at' => $payload['occurred_at'],
            'received_at' => $receivedAt,
            'track_id' => $payload['track_id'],
            'confidence' => $payload['confidence'],
            'metadata_json' => $payload['metadata'],
            'created_at' => $receivedAt,
        ]);

        Log::info('QueueEventPersister.persisted', [
            'queue_event_id' => $event->queue_event_id,
            'location_id' => $locationId,
            'event_type' => $eventType,
        ]);

        return $event;
    }

    /**
     * @param  array{
     *     v: int,
     *     device_uid: string,
     *     camera_id: int|null,
     *     occurred_at: CarbonImmutable,
     *     track_id: string|null,
     *     confidence: float|null,
     *     metadata: array<string, mixed>|null
     * }  $payload
     */
    private function isDuplicate(int $edgeDeviceId, array $payload, string $eventType): bool
    {
        if ($payload['track_id'] === null) {
            return false;
        }

        return QueueEvent::query()
            ->withoutGlobalScopes()
            ->where('edge_device_id', $edgeDeviceId)
            ->where('event_type', $eventType)
            ->where('occurred_at', $payload['occurred_at'])
            ->where('track_id', $payload['track_id'])
            ->exists();
    }
}
