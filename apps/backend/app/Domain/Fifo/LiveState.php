<?php

declare(strict_types=1);

namespace App\Domain\Fifo;

use Carbon\CarbonImmutable;

/**
 * Immutable value object representing the live state of one counter location
 * at one instant in time. This is the sole input to {@see CutoffCalculator}.
 *
 * Constructed from Redis state by {@see \App\Services\Fifo\LiveStateReader}.
 */
final readonly class LiveState
{
    public function __construct(
        public int $locationId,
        public int $quota,
        public int $issued,
        public int $queueHead,
        public int $queueTail,
        public float $issuanceRatePerMin,
        public float $arrivalRatePerMin,
        public CarbonImmutable $now,
        public CarbonImmutable $closesAt,
        public Mode $mode = Mode::SHADOW,
        public bool $festivalMode = false,
    ) {
    }

    public function safetyMargin(): float
    {
        return $this->festivalMode ? 0.20 : 0.10;
    }

    public function tokensRemaining(): int
    {
        return max(0, $this->quota - $this->issued);
    }

    public function queueLength(): int
    {
        return max(0, $this->queueTail - $this->queueHead);
    }

    public function minutesUntilClose(): float
    {
        return max(0.0, $this->now->diffInMinutes($this->closesAt, true));
    }

    /**
     * Projected number of additional arrivals before counter closes,
     * based on rolling arrival rate. Capped at minutes-until-close to
     * avoid runaway extrapolation overnight.
     */
    public function projectedArrivalsBeforeClose(): int
    {
        return (int) ceil($this->arrivalRatePerMin * $this->minutesUntilClose());
    }
}
