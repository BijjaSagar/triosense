<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\RehydrateLiveStateJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

final class ReplayCommand extends Command
{
    protected $signature = 'triosense:replay {date : Operating day (YYYY-MM-DD)} {location_id : Location ID to replay}';

    protected $description = 'Replay queue_events for a day into Redis (cold-start recovery or analysis)';

    public function handle(): int
    {
        $date = (string) $this->argument('date');
        $locationId = (int) $this->argument('location_id');

        Log::info('ReplayCommand.start', [
            'date' => $date,
            'location_id' => $locationId,
        ]);

        $this->info("Replaying queue_events for location {$locationId} on {$date}...");

        RehydrateLiveStateJob::dispatchSync($locationId, $date);

        $this->info('Replay complete. Verify Redis keys with redis-cli or GET /locations/{id}/state.');

        Log::info('ReplayCommand.complete', [
            'date' => $date,
            'location_id' => $locationId,
        ]);

        return self::SUCCESS;
    }
}
