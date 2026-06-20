<?php

declare(strict_types=1);

namespace App\Services\Fifo;

use App\Domain\Fifo\Decision;
use App\Domain\Fifo\LiveState;
use App\Domain\Fifo\LocationRedisKeys;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Log;

/**
 * Atomically writes FIFO decision outputs back to Redis.
 */
final class LocationRedisStateWriter
{
    public function __construct(
        private readonly RedisFactory $redis,
    ) {
    }

    public function apply(int $locationId, Decision $decision, LiveState $state): void
    {
        $tokensRemaining = $state->tokensRemaining();
        $status = $decision->status->value;
        $cutoffKey = LocationRedisKeys::cutoff($locationId);
        $asOfMs = (string) CarbonImmutable::now()->getTimestampMs();

        Log::debug('LocationRedisStateWriter.apply', [
            'location_id' => $locationId,
            'status' => $status,
            'cutoff_position' => $decision->cutoffPosition,
            'tokens_remaining' => $tokensRemaining,
        ]);

        $this->connection()->transaction(function (Connection $tx) use (
            $locationId,
            $status,
            $tokensRemaining,
            $cutoffKey,
            $asOfMs,
            $decision,
        ): void {
            $tx->set(LocationRedisKeys::status($locationId), $status);
            $tx->set(LocationRedisKeys::tokensRemaining($locationId), (string) $tokensRemaining);
            $tx->set(LocationRedisKeys::lastEventAt($locationId), $asOfMs);

            if ($decision->cutoffPosition === null) {
                $tx->del($cutoffKey);
            } else {
                $tx->set($cutoffKey, (string) $decision->cutoffPosition);
            }
        });
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

        $connection = $this->connection();

        foreach ($values as $field => $value) {
            if (! array_key_exists($field, $map)) {
                continue;
            }

            if ($value === null) {
                $connection->del($map[$field]);

                continue;
            }

            $connection->set($map[$field], (string) $value);
        }

        Log::debug('LocationRedisStateWriter.seed', [
            'location_id' => $locationId,
            'fields' => array_keys($values),
        ]);
    }

    private function connection(): Connection
    {
        return $this->redis->connection();
    }
}
