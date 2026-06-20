<?php

declare(strict_types=1);

namespace App\Services\Queue;

use App\Domain\Fifo\LocationRedisKeys;
use App\Models\QueueEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Tracks rolling 5-minute event rates in Redis sorted sets.
 */
final class RollingRateTracker
{
    private const WINDOW_SECONDS = 300;

    public function recordEnter(int $locationId, CarbonImmutable $occurredAt): float
    {
        return $this->recordEvent($locationId, 'enter', $occurredAt);
    }

    public function recordExit(int $locationId, CarbonImmutable $occurredAt): float
    {
        return $this->recordEvent($locationId, 'exit', $occurredAt);
    }

    public function recordIssue(int $locationId, CarbonImmutable $occurredAt): float
    {
        return $this->recordEvent($locationId, 'issue', $occurredAt);
    }

    public function arrivalRate(int $locationId): float
    {
        return $this->currentRate($locationId, 'enter');
    }

    public function issuanceRate(int $locationId): float
    {
        return $this->currentRate($locationId, 'issue');
    }

    public function rebuildFromEvents(int $locationId, string $eventType): float
    {
        $since = CarbonImmutable::now()->subSeconds(self::WINDOW_SECONDS);
        $count = QueueEvent::query()
            ->withoutGlobalScopes()
            ->where('location_id', $locationId)
            ->where('event_type', $eventType)
            ->where('occurred_at', '>=', $since)
            ->count();

        $rate = ($count / self::WINDOW_SECONDS) * 60.0;
        $key = $eventType === 'enter'
            ? LocationRedisKeys::arrivalRatePerMin($locationId)
            : LocationRedisKeys::issuanceRatePerMin($locationId);

        Redis::set($key, (string) round($rate, 3));

        Log::debug('RollingRateTracker.rebuildFromEvents', [
            'location_id' => $locationId,
            'event_type' => $eventType,
            'rate' => $rate,
        ]);

        return $rate;
    }

    private function recordEvent(int $locationId, string $eventType, CarbonImmutable $occurredAt): float
    {
        $zsetKey = $this->zsetKey($locationId, $eventType);
        $score = (float) $occurredAt->getTimestampMs();
        $member = $occurredAt->format('Y-m-d H:i:s.u').':'.uniqid('', true);

        Redis::zadd($zsetKey, $score, $member);
        $cutoff = (float) CarbonImmutable::now()->subSeconds(self::WINDOW_SECONDS)->getTimestampMs();
        Redis::zremrangebyscore($zsetKey, '-inf', (string) $cutoff);

        $count = (int) Redis::zcard($zsetKey);
        $rate = ($count / self::WINDOW_SECONDS) * 60.0;

        $targetKey = $eventType === 'enter'
            ? LocationRedisKeys::arrivalRatePerMin($locationId)
            : LocationRedisKeys::issuanceRatePerMin($locationId);

        Redis::set($targetKey, (string) round($rate, 3));

        Log::debug('RollingRateTracker.recordEvent', [
            'location_id' => $locationId,
            'event_type' => $eventType,
            'rate' => $rate,
        ]);

        return $rate;
    }

    private function currentRate(int $locationId, string $eventType): float
    {
        $zsetKey = $this->zsetKey($locationId, $eventType);
        $cutoff = (float) CarbonImmutable::now()->subSeconds(self::WINDOW_SECONDS)->getTimestampMs();
        Redis::zremrangebyscore($zsetKey, '-inf', (string) $cutoff);
        $count = (int) Redis::zcard($zsetKey);

        return ($count / self::WINDOW_SECONDS) * 60.0;
    }

    private function zsetKey(int $locationId, string $eventType): string
    {
        return LocationRedisKeys::prefix($locationId).":rate_window:{$eventType}";
    }
}
