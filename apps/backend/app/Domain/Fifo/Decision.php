<?php

declare(strict_types=1);

namespace App\Domain\Fifo;

/**
 * Immutable result of a single FIFO decision.
 *
 * Returned by {@see CutoffCalculator::decide()}. Consumed by
 * App\Jobs\FifoTickJob which is responsible for the side effects
 * (broadcasting, MQTT commands, audit logging).
 */
final readonly class Decision
{
    public function __construct(
        public Status $status,
        public ?int $cutoffPosition,
        public string $reason,
    ) {
    }
}
