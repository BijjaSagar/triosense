<?php

declare(strict_types=1);

use App\Jobs\FifoTickJob;
use App\Models\Location;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function (): void {
    $locations = Location::query()
        ->withoutGlobalScopes()
        ->where('status', 'active')
        ->pluck('location_id');

    foreach ($locations as $locationId) {
        FifoTickJob::dispatch((int) $locationId);
    }
})->everySecond()->name('triosense:fifo-tick-scheduler');
