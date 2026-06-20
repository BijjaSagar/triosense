<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Location;
use App\Mqtt\MqttTopicRouter;
use App\Policies\LocationPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MqttTopicRouter::class, function ($app): MqttTopicRouter {
            return new MqttTopicRouter(
                enterHandler: $app->make(\App\Mqtt\Handlers\EnterEventHandler::class),
                exitHandler: $app->make(\App\Mqtt\Handlers\ExitEventHandler::class),
                issueHandler: $app->make(\App\Mqtt\Handlers\IssueEventHandler::class),
                heartbeatHandler: $app->make(\App\Mqtt\Handlers\HeartbeatHandler::class),
                topicPrefix: (string) config('triosense.mqtt.topic_prefix', 'triosense'),
            );
        });
    }

    public function boot(): void
    {
        Gate::policy(Location::class, LocationPolicy::class);
    }
}
