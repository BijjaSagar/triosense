<?php

declare(strict_types=1);

namespace App\Services\Locations;

use App\Domain\Fifo\LocationRedisKeys;
use App\Jobs\RehydrateLiveStateJob;
use App\Models\EdgeDevice;
use App\Models\Location;
use App\Models\QueueEvent;
use App\Services\Fifo\LiveStateReader;
use App\Services\Queue\QueueEventRedisWriter;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

/**
 * Reads live location state from Redis with cold-start rehydrate fallback.
 */
final class LocationStateService
{
    public function __construct(
        private readonly LiveStateReader $liveStateReader,
        private readonly QueueEventRedisWriter $redisWriter,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getState(Location $location): array
    {
        $locationId = (int) $location->location_id;

        if ($this->redisWriter->isCold($locationId)) {
            Log::info('LocationStateService.cold_redis_rehydrate', [
                'location_id' => $locationId,
            ]);
            RehydrateLiveStateJob::dispatchSync($locationId);
        }

        $liveState = $this->liveStateReader->read($location);
        $snapshot = $this->liveStateReader->readSnapshot($locationId);
        $lastEventMs = $this->readLastEventAt($locationId);

        return [
            'location_id' => $locationId,
            'location_name' => $location->name,
            'short_code' => $location->short_code,
            'as_of' => $liveState->now->toIso8601String(),
            'quota' => $liveState->quota,
            'issued' => $liveState->issued,
            'tokens_remaining' => $liveState->tokensRemaining(),
            'queue_head' => $liveState->queueHead,
            'queue_tail' => $liveState->queueTail,
            'cutoff_position' => $snapshot->cutoffPosition,
            'status' => $snapshot->status->value,
            'issuance_rate_per_min' => $liveState->issuanceRatePerMin,
            'arrival_rate_per_min' => $liveState->arrivalRatePerMin,
            'last_event_at' => $lastEventMs !== null
                ? CarbonImmutable::createFromTimestampMs($lastEventMs)->toIso8601String()
                : null,
            'edge_devices' => $this->edgeDevicesPayload($location),
        ];
    }

    /**
     * @return LengthAwarePaginator<QueueEvent>
     */
    public function paginateEvents(
        int $tenantId,
        int $locationId,
        int $perPage = 50,
        ?string $eventType = null,
    ): LengthAwarePaginator {
        $query = QueueEvent::query()
            ->where('tenant_id', $tenantId)
            ->where('location_id', $locationId)
            ->orderByDesc('occurred_at')
            ->orderByDesc('queue_event_id');

        if ($eventType !== null && $eventType !== '') {
            $query->where('event_type', $eventType);
        }

        return $query->paginate($perPage);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function edgeDevicesPayload(Location $location): array
    {
        return EdgeDevice::query()
            ->where('tenant_id', $location->tenant_id)
            ->where('location_id', $location->location_id)
            ->get()
            ->map(static fn (EdgeDevice $device): array => [
                'device_uid' => $device->device_uid,
                'status' => $device->status,
                'last_heartbeat_at' => $device->last_heartbeat_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    private function readLastEventAt(int $locationId): ?int
    {
        $value = \Illuminate\Support\Facades\Redis::get(LocationRedisKeys::lastEventAt($locationId));

        if ($value === null || $value === false) {
            return null;
        }

        return (int) $value;
    }
}
