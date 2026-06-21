<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

/**
 * Registers Sentry when SENTRY_LARAVEL_DSN is set. No-op stub otherwise.
 */
final class SentryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $dsn = config('triosense.sentry.dsn');

        if ($dsn === null || $dsn === '') {
            Log::debug('SentryServiceProvider.skipped_no_dsn');

            return;
        }

        if (! class_exists(\Sentry\Laravel\ServiceProvider::class)) {
            Log::warning('SentryServiceProvider.package_missing', [
                'hint' => 'Run composer require sentry/sentry-laravel on staging hosts.',
            ]);

            return;
        }

        $this->app->register(\Sentry\Laravel\ServiceProvider::class);
    }
}
