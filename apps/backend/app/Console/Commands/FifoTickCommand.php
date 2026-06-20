<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\FifoTickJob;
use App\Models\Location;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

final class FifoTickCommand extends Command
{
    protected $signature = 'triosense:fifo-tick';

    protected $description = 'Dispatch FIFO tick jobs every second for all active locations';

    public function handle(): never
    {
        $intervalMs = (int) config('triosense.fifo.tick_interval_ms', 1000);

        Log::info('FifoTickCommand.start', ['interval_ms' => $intervalMs]);
        $this->info("FIFO tick dispatcher running (interval {$intervalMs}ms). Press Ctrl+C to stop.");

        // Long-running daemon — exits only on SIGINT/SIGTERM.
        while (true) { // @phpstan-ignore-line
            $started = hrtime(true);

            $locations = Location::query()
                ->withoutGlobalScopes()
                ->where('status', 'active')
                ->pluck('location_id');

            foreach ($locations as $locationId) {
                FifoTickJob::dispatch((int) $locationId);
            }

            $elapsedMs = (hrtime(true) - $started) / 1_000_000;
            $sleepMs = max(0, $intervalMs - (int) $elapsedMs);

            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
        }
    }
}
