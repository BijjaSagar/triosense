<?php

declare(strict_types=1);

namespace App\Broadcasting;

use App\Domain\Fifo\Decision;
use App\Domain\Fifo\LiveState;
use App\Domain\Fifo\Status;
use Carbon\CarbonImmutable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Pushed to private-location.{id} whenever live FIFO state changes.
 *
 * @see API_CONTRACTS.md §2.2
 */
final class LocationStateUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  'issue_event'|'enter_event'|'exit_event'|'tick'|'override'  $cause
     */
    public function __construct(
        public readonly int $locationId,
        public readonly CarbonImmutable $asOf,
        public readonly LiveState $state,
        public readonly Decision $decision,
        public readonly string $cause = 'tick',
    ) {
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('location.'.$this->locationId);
    }

    public function broadcastAs(): string
    {
        return 'LocationStateUpdated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'location_id' => $this->locationId,
            'as_of' => $this->asOf->toIso8601String(),
            'tokens_remaining' => $this->state->tokensRemaining(),
            'queue_head' => $this->state->queueHead,
            'queue_tail' => $this->state->queueTail,
            'cutoff_position' => $this->decision->cutoffPosition,
            'status' => $this->decision->status->value,
            'delta' => [
                'cause' => $this->cause,
            ],
        ];
    }
}
