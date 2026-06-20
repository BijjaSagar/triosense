<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Location;
use App\Policies\LocationPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        Gate::policy(Location::class, LocationPolicy::class);
    }
}
