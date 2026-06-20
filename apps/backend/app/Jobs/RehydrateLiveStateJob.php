<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Location;
use App\Models\QueueEvent;
use App\Services\Queue\QueueEventRedisWriter;
use App\Services\Queue\RollingRateTracker;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Replays today's queue_events into Redis for cold-start recovery.
 */
final class RehydrateLiveStateJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $uniqueFor = 60;

    public function __construct(
        public readonly int $locationId,
        public readonly ?string $date = null,
    ) {
    }

    public function uniqueId(): string
    {
        return 'rehydrate:'.$this->locationId.':'.($this->date ?? 'today');
    }

    public function handle(
        QueueEventRedisWriter $redisWriter,
        RollingRateTracker $rateTracker,
    ): void {
        Log::info('RehydrateLiveStateJob.start', ['location_id' => $this->locationId]);

        $location = Location::query()
            ->withoutGlobalScopes()
            ->find($this->locationId);

        if ($location === null) {
            Log::warning('RehydrateLiveStateJob.location_missing', [
                'location_id' => $this->locationId,
            ]);

            return;
        }

        $timezone = $location->tenant?->timezone ?? 'Asia/Kolkata';
        $day = $this->date ?? CarbonImmutable::now($timezone)->toDateString();
        $start = CarbonImmutable::parse($day.' 00:00:00', $timezone);
        $end = $start->addDay();

        $pattern = "triosense:loc:{$this->locationId}:*";
        $keys = Redis::keys($pattern);
        if ($keys !== []) {
            Redis::del($keys);
        }

        $redisWriter->seedLocation($this->locationId, (int) $location->tenant_id);

        $events = QueueEvent::query()
            ->withoutGlobalScopes()
            ->where('location_id', $this->locationId)
            ->where('occurred_at', '>=', $start)
            ->where('occurred_at', '<', $end)
            ->orderBy('occurred_at')
            ->orderBy('queue_event_id')
            ->get();

        Log::info('RehydrateLiveStateJob.replaying', [
            'location_id' => $this->locationId,
            'event_count' => $events->count(),
        ]);

        foreach ($events as $event) {
            match ($event->event_type) {
                'enter' => $redisWriter->applyEnter($event),
                'exit' => $redisWriter->applyExit($event),
                'issue' => $redisWriter->applyIssue($event),
                default => Log::debug('RehydrateLiveStateJob.skipped_event', [
                    'queue_event_id' => $event->queue_event_id,
                    'event_type' => $event->event_type,
                ]),
            };
        }

        $rateTracker->rebuildFromEvents($this->locationId, 'enter');
        $rateTracker->rebuildFromEvents($this->locationId, 'issue');

        Log::info('RehydrateLiveStateJob.complete', [
            'location_id' => $this->locationId,
            'events_replayed' => $events->count(),
        ]);
    }
}
