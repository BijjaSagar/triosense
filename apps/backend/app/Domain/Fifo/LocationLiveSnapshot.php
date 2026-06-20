<?php

declare(strict_types=1);

namespace App\Domain\Fifo;

/**
 * Cached live-state fields read from Redis before a FIFO tick runs.
 */
final readonly class LocationLiveSnapshot
{
    public function __construct(
        public Status $status,
        public ?int $cutoffPosition,
        public int $tokensRemaining,
        public int $queueHead,
        public int $queueTail,
    ) {
    }

    public function statusChanged(Status $newStatus): bool
    {
        return $this->status !== $newStatus;
    }

    public function cutoffChanged(?int $newCutoff): bool
    {
        return $this->cutoffPosition !== $newCutoff;
    }

    public function shouldBroadcast(Decision $decision, LiveState $state): bool
    {
        return $this->statusChanged($decision->status)
            || $this->cutoffChanged($decision->cutoffPosition)
            || $this->tokensRemaining !== $state->tokensRemaining()
            || $this->queueHead !== $state->queueHead
            || $this->queueTail !== $state->queueTail;
    }
}
