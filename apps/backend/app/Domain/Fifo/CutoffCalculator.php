<?php

declare(strict_types=1);

namespace App\Domain\Fifo;

/**
 * The FIFO decision engine.
 *
 * Pure class. No I/O. No Eloquent. No Redis. No HTTP.
 *
 * This is the single source of truth for cutoff decisions. Every change
 * here requires:
 *   1. An ADR in docs/adr/
 *   2. Pest tests covering the new behaviour
 *   3. A replay test against a real day's events
 *
 * See ARCHITECTURE.md §5 for the algorithm in plain language.
 */
final class CutoffCalculator
{
    /**
     * Buffer added to projected arrivals to absorb noise (10% default, 20% festival).
     * Tuned during shadow-mode rollout. See ADR-0004.
     */
    private const float DEFAULT_SAFETY_MARGIN = 0.10;

    private const float FESTIVAL_SAFETY_MARGIN = 0.20;

    /**
     * Below this minute-rate of issuance, we treat throughput as unknown
     * and refuse to forecast — falling back to current-queue comparison only.
     */
    private const float MIN_ISSUANCE_RATE_FOR_FORECAST = 1.0;

    public function decide(LiveState $state): Decision
    {
        if ($state->mode === Mode::DISABLED) {
            return new Decision(
                status: Status::OPEN,
                cutoffPosition: null,
                reason: 'mode_disabled',
            );
        }

        $remaining = $state->tokensRemaining();
        $queueLength = $state->queueLength();

        // 1. Quota exhausted → CLOSED.
        if ($remaining === 0) {
            return new Decision(
                status: Status::CLOSED,
                cutoffPosition: $state->queueHead,
                reason: 'quota_exhausted',
            );
        }

        // 2. People already in queue exceed remaining tokens → CUTOFF_DECLARED.
        //    Cutoff is the position of the last person who will still get a token.
        if ($queueLength >= $remaining) {
            return new Decision(
                status: Status::CUTOFF_DECLARED,
                cutoffPosition: $state->queueHead + $remaining - 1,
                reason: 'queue_exceeds_remaining',
            );
        }

        // 3. Forecast — if projected arrivals before close push us over,
        //    declare APPROACHING_CUTOFF. We do not set a cutoff position
        //    yet because the actual cutoff depends on real future arrivals.
        if ($state->issuanceRatePerMin >= self::MIN_ISSUANCE_RATE_FOR_FORECAST) {
            $projectedArrivals = $state->projectedArrivalsBeforeClose();
            $margin = $state->festivalMode ? self::FESTIVAL_SAFETY_MARGIN : self::DEFAULT_SAFETY_MARGIN;
            $bufferedDemand = (int) ceil(
                ($queueLength + $projectedArrivals) * (1 + $margin)
            );

            if ($bufferedDemand >= $remaining) {
                return new Decision(
                    status: Status::APPROACHING_CUTOFF,
                    cutoffPosition: null,
                    reason: 'forecast_exceeds_remaining',
                );
            }
        }

        // 4. Plenty of capacity.
        return new Decision(
            status: Status::OPEN,
            cutoffPosition: null,
            reason: 'sufficient_capacity',
        );
    }
}
