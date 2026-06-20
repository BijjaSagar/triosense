<?php

declare(strict_types=1);

namespace App\Services\Fifo;

use App\Domain\Fifo\LiveState;
use App\Domain\Fifo\LocationLiveSnapshot;
use App\Domain\Fifo\LocationRedisKeys;
use App\Domain\Fifo\Mode;
use App\Domain\Fifo\Status;
use App\Models\Location;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Log;

/**
 * Builds {@see LiveState} from Redis counters and location metadata.
 */
final class LiveStateReader
{
    public function __construct(
        private readonly RedisFactory $redis,
    ) {
    }

    public function read(Location $location, ?CarbonImmutable $now = null): LiveState
    {
        $locationId = (int) $location->location_id;
        $keys = [
            LocationRedisKeys::quota($locationId),
            LocationRedisKeys::issued($locationId),
            LocationRedisKeys::queueHead($locationId),
            LocationRedisKeys::queueTail($locationId),
            LocationRedisKeys::issuanceRatePerMin($locationId),
            LocationRedisKeys::arrivalRatePerMin($locationId),
        ];

        $values = $this->connection()->mget($keys);

        $quota = $this->intValue($values[0] ?? null, (int) $location->default_quota);
        $issued = $this->intValue($values[1] ?? null, 0);
        $queueHead = $this->intValue($values[2] ?? null, 0);
        $queueTail = $this->intValue($values[3] ?? null, 0);
        $issuanceRate = $this->floatValue($values[4] ?? null, 0.0);
        $arrivalRate = $this->floatValue($values[5] ?? null, 0.0);

        $timezone = $location->tenant?->timezone ?? 'Asia/Kolkata';
        $now ??= CarbonImmutable::now($timezone);
        $closesAt = $this->resolveClosesAt($location, $now, $timezone);

        Log::debug('LiveStateReader.read', [
            'location_id' => $locationId,
            'quota' => $quota,
            'issued' => $issued,
            'queue_head' => $queueHead,
            'queue_tail' => $queueTail,
            'issuance_rate_per_min' => $issuanceRate,
            'arrival_rate_per_min' => $arrivalRate,
            'mode' => $location->mode,
        ]);

        return new LiveState(
            locationId: $locationId,
            quota: $quota,
            issued: $issued,
            queueHead: $queueHead,
            queueTail: $queueTail,
            issuanceRatePerMin: $issuanceRate,
            arrivalRatePerMin: $arrivalRate,
            now: $now,
            closesAt: $closesAt,
            mode: Mode::from($location->mode),
            festivalMode: (bool) $location->festival_mode,
        );
    }

    public function readSnapshot(int $locationId): LocationLiveSnapshot
    {
        $keys = [
            LocationRedisKeys::status($locationId),
            LocationRedisKeys::cutoff($locationId),
            LocationRedisKeys::tokensRemaining($locationId),
            LocationRedisKeys::queueHead($locationId),
            LocationRedisKeys::queueTail($locationId),
        ];

        $values = $this->connection()->mget($keys);
        $statusRaw = is_string($values[0] ?? null) ? $values[0] : Status::OPEN->value;
        $cutoffRaw = $values[1] ?? null;

        Log::debug('LiveStateReader.readSnapshot', [
            'location_id' => $locationId,
            'status' => $statusRaw,
            'cutoff' => $cutoffRaw,
        ]);

        return new LocationLiveSnapshot(
            status: Status::from($statusRaw),
            cutoffPosition: $cutoffRaw !== null && $cutoffRaw !== false ? (int) $cutoffRaw : null,
            tokensRemaining: $this->intValue($values[2] ?? null, 0),
            queueHead: $this->intValue($values[3] ?? null, 0),
            queueTail: $this->intValue($values[4] ?? null, 0),
        );
    }

    private function connection(): Connection
    {
        return $this->redis->connection();
    }

    private function resolveClosesAt(Location $location, CarbonImmutable $now, string $timezone): CarbonImmutable
    {
        $closeTime = (string) $location->closes_at;
        $closesAt = CarbonImmutable::parse($now->toDateString().' '.$closeTime, $timezone);

        if ($closesAt->lessThanOrEqualTo($now)) {
            $closesAt = $closesAt->addDay();
        }

        return $closesAt;
    }

    private function intValue(mixed $value, int $default): int
    {
        if ($value === null || $value === false) {
            return $default;
        }

        return (int) $value;
    }

    private function floatValue(mixed $value, float $default): float
    {
        if ($value === null || $value === false) {
            return $default;
        }

        return (float) $value;
    }
}
