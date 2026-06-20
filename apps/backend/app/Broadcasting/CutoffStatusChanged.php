<?php

declare(strict_types=1);

namespace App\Broadcasting;

use App\Domain\Fifo\Decision;
use App\Domain\Fifo\Status;
use Carbon\CarbonImmutable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Emitted only when FIFO status transitions.
 *
 * @see API_CONTRACTS.md §2.2
 */
final class CutoffStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly int $locationId,
        public readonly ?Status $previousStatus,
        public readonly Decision $decision,
        public readonly CarbonImmutable $decidedAt,
    ) {
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('location.'.$this->locationId);
    }

    public function broadcastAs(): string
    {
        return 'CutoffStatusChanged';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'location_id' => $this->locationId,
            'previous_status' => $this->previousStatus?->value,
            'new_status' => $this->decision->status->value,
            'cutoff_position' => $this->decision->cutoffPosition,
            'decided_at' => $this->decidedAt->toIso8601String(),
        ];
    }
}
