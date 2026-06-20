<?php

declare(strict_types=1);

namespace App\Services\Fifo;

use App\Broadcasting\CutoffStatusChanged;
use App\Broadcasting\LocationStateUpdated;
use App\Domain\Fifo\CutoffCalculator;
use App\Domain\Fifo\LiveStateReader;
use App\Domain\Fifo\Mode;
use App\Domain\Fifo\Decision;
use App\Domain\Fifo\LiveState;
use App\Domain\Fifo\Status;
use App\Models\CutoffEvent;
use App\Models\Location;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Orchestrates one FIFO tick for a single location.
 *
 * Side effects live here; {@see CutoffCalculator} stays pure.
 */
final class FifoTickService
{
    public function __construct(
        private readonly LiveStateReader $liveStateReader,
        private readonly CutoffCalculator $calculator,
        private readonly LocationRedisStateWriter $redisWriter,
    ) {
    }

    public function tick(int $locationId, ?CarbonImmutable $now = null): void
    {
        Log::info('FifoTickService.tick.start', ['location_id' => $locationId]);

        $location = Location::query()
            ->with('tenant')
            ->where('location_id', $locationId)
            ->first();

        if ($location === null) {
            Log::warning('FifoTickService.tick.location_missing', ['location_id' => $locationId]);

            throw new RuntimeException("Location {$locationId} not found.");
        }

        if ($location->mode === Mode::DISABLED->value) {
            Log::debug('FifoTickService.tick.skipped_disabled', ['location_id' => $locationId]);

            return;
        }

        $previous = $this->liveStateReader->readSnapshot($locationId);
        $state = $this->liveStateReader->read($location, $now);
        $decision = $this->calculator->decide($state);
        $decidedAt = $state->now;

        Log::debug('FifoTickService.tick.decision', [
            'location_id' => $locationId,
            'previous_status' => $previous->status->value,
            'new_status' => $decision->status->value,
            'cutoff_position' => $decision->cutoffPosition,
            'reason' => $decision->reason,
        ]);

        $this->redisWriter->apply($locationId, $decision, $state);

        if ($previous->statusChanged($decision->status)) {
            $this->persistCutoffEvent($location, $previous->status, $decision, $state, $decidedAt);
        }

        if ($previous->shouldBroadcast($decision, $state)) {
            Event::dispatch(new LocationStateUpdated(
                locationId: $locationId,
                asOf: $decidedAt,
                state: $state,
                decision: $decision,
                cause: 'tick',
            ));

            if ($previous->statusChanged($decision->status)) {
                Event::dispatch(new CutoffStatusChanged(
                    locationId: $locationId,
                    previousStatus: $previous->status,
                    decision: $decision,
                    decidedAt: $decidedAt,
                ));
            }
        }

        Log::info('FifoTickService.tick.complete', [
            'location_id' => $locationId,
            'status' => $decision->status->value,
        ]);
    }

    private function persistCutoffEvent(
        Location $location,
        Status $previousStatus,
        Decision $decision,
        LiveState $state,
        CarbonImmutable $decidedAt,
    ): void {
        $eventMode = $location->mode === Mode::LIVE->value ? 'live' : 'shadow';

        $record = CutoffEvent::query()->create([
            'tenant_id' => $location->tenant_id,
            'location_id' => $location->location_id,
            'decided_at' => $decidedAt,
            'mode' => $eventMode,
            'previous_status' => $previousStatus->value,
            'new_status' => $decision->status->value,
            'queue_head' => $state->queueHead,
            'queue_tail' => $state->queueTail,
            'tokens_remaining' => $state->tokensRemaining(),
            'cutoff_position' => $decision->cutoffPosition,
            'issuance_rate' => $state->issuanceRatePerMin,
            'arrival_rate' => $state->arrivalRatePerMin,
            'reason' => $decision->reason,
            'created_at' => $decidedAt,
        ]);

        Log::info('FifoTickService.persistCutoffEvent', [
            'location_id' => $location->location_id,
            'cutoff_event_id' => $record->cutoff_event_id,
            'previous_status' => $previousStatus->value,
            'new_status' => $decision->status->value,
        ]);
    }
}
