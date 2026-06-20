<?php

declare(strict_types=1);

namespace App\Services\Fifo;

use App\Domain\Fifo\Decision;
use App\Domain\Fifo\LiveState;
use App\Domain\Fifo\LocationRedisKeys;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Atomically writes FIFO decision outputs back to Redis.
 */
final class LocationRedisStateWriter
{
    private readonly string $applyScript;

    public function __construct()
    {
        $this->applyScript = (string) file_get_contents(
            base_path('scripts/lua/apply_fifo_decision.lua')
        );
    }

    public function apply(int $locationId, Decision $decision, LiveState $state): void
    {
        $tokensRemaining = $state->tokensRemaining();
        $status = $decision->status->value;
        $asOfMs = (string) CarbonImmutable::now()->getTimestampMs();
        $cutoffValue = $decision->cutoffPosition === null
            ? ''
            : (string) $decision->cutoffPosition;

        Log::debug('LocationRedisStateWriter.apply', [
            'location_id' => $locationId,
            'status' => $status,
            'cutoff_position' => $decision->cutoffPosition,
            'tokens_remaining' => $tokensRemaining,
        ]);

        Redis::connection()->command('eval', [
            $this->applyScript,
            4,
            LocationRedisKeys::status($locationId),
            LocationRedisKeys::tokensRemaining($locationId),
            LocationRedisKeys::lastEventAt($locationId),
            LocationRedisKeys::cutoff($locationId),
            $status,
            (string) $tokensRemaining,
            $asOfMs,
            $cutoffValue,
        ]);
    }

    /**
     * Seed Redis counters for tests and local development.
     *
     * @param array<string, int|float|string|null> $values
     */
    public function seed(int $locationId, array $values): void
    {
        $map = [
            'quota' => LocationRedisKeys::quota($locationId),
            'issued' => LocationRedisKeys::issued($locationId),
            'tokens_remaining' => LocationRedisKeys::tokensRemaining($locationId),
            'queue_head' => LocationRedisKeys::queueHead($locationId),
            'queue_tail' => LocationRedisKeys::queueTail($locationId),
            'cutoff' => LocationRedisKeys::cutoff($locationId),
            'status' => LocationRedisKeys::status($locationId),
            'issuance_rate_per_min' => LocationRedisKeys::issuanceRatePerMin($locationId),
            'arrival_rate_per_min' => LocationRedisKeys::arrivalRatePerMin($locationId),
        ];

        foreach ($values as $field => $value) {
            if (! array_key_exists($field, $map)) {
                continue;
            }

            if ($value === null) {
                Redis::del($map[$field]);

                continue;
            }

            Redis::set($map[$field], (string) $value);
        }

        if (! array_key_exists('last_event_at', $values)) {
            Redis::set(
                LocationRedisKeys::lastEventAt($locationId),
                (string) \Carbon\CarbonImmutable::now()->getTimestampMs()
            );
        }

        Log::debug('LocationRedisStateWriter.seed', [
            'location_id' => $locationId,
            'fields' => array_keys($values),
        ]);
    }
}
