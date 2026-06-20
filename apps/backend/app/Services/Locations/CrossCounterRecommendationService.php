<?php

declare(strict_types=1);

namespace App\Services\Locations;

use App\Domain\Fifo\LocationRedisKeys;
use App\Domain\Fifo\Status;
use App\Models\Location;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Surfaces cross-counter redirection recommendations.
 */
final class CrossCounterRecommendationService
{
    public function __construct(
        private readonly LocationStateService $locationState,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getRecommendations(int $tenantId): array
    {
        $locations = Location::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->orderBy('location_id')
            ->get();

        $buffer = (int) config('triosense.cross_counter.buffer', 50);
        $recommendations = [];

        $states = [];
        foreach ($locations as $location) {
            if ($location->festival_mode) {
                Log::debug('CrossCounterRecommendationService.festival_mode_skip', [
                    'location_id' => $location->location_id,
                ]);

                return [];
            }

            $states[$location->location_id] = $this->locationState->getState($location);
        }

        foreach ($locations as $source) {
            $sourceState = $states[$source->location_id] ?? null;

            if ($sourceState === null || $sourceState['status'] !== Status::CUTOFF_DECLARED->value) {
                continue;
            }

            foreach ($locations as $target) {
                if ($target->location_id === $source->location_id) {
                    continue;
                }

                $targetState = $states[$target->location_id] ?? null;

                if ($targetState === null) {
                    continue;
                }

                $queueLength = max(0, $targetState['queue_tail'] - $targetState['queue_head']);
                $available = $targetState['tokens_remaining'] - $queueLength;

                if ($available > $buffer) {
                    $recommendations[] = [
                        'source_location_id' => $source->location_id,
                        'source_location_name' => $source->name,
                        'target_location_id' => $target->location_id,
                        'target_location_name' => $target->name,
                        'target_tokens_remaining' => $targetState['tokens_remaining'],
                        'target_queue_length' => $queueLength,
                        'buffer' => $buffer,
                        'message' => sprintf(
                            'Tokens still available at %s (%d remaining)',
                            $target->name,
                            $targetState['tokens_remaining'],
                        ),
                    ];
                }
            }
        }

        Log::info('CrossCounterRecommendationService.getRecommendations', [
            'tenant_id' => $tenantId,
            'count' => count($recommendations),
        ]);

        return $recommendations;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getRecommendationForLocation(int $tenantId, int $locationId): ?array
    {
        $all = $this->getRecommendations($tenantId);

        foreach ($all as $rec) {
            if ($rec['source_location_id'] === $locationId) {
                return $rec;
            }
        }

        return null;
    }
}
