<?php

declare(strict_types=1);

return [
    'mqtt' => [
        'host' => env('TRIOSENSE_MQTT_HOST', '127.0.0.1'),
        'port' => (int) env('TRIOSENSE_MQTT_PORT', 1883),
        'tls' => filter_var(env('TRIOSENSE_MQTT_TLS', false), FILTER_VALIDATE_BOOL),
        'username' => env('TRIOSENSE_MQTT_USERNAME'),
        'password' => env('TRIOSENSE_MQTT_PASSWORD'),
        'client_id' => env('TRIOSENSE_MQTT_CLIENT_ID', 'triosense-backend-local'),
        'topic_prefix' => env('TRIOSENSE_MQTT_TOPIC_PREFIX', 'triosense'),
    ],

    'fifo' => [
        'tick_interval_ms' => (int) env('TRIOSENSE_FIFO_TICK_INTERVAL_MS', 1000),
        'default_mode' => env('TRIOSENSE_FIFO_DEFAULT_MODE', 'shadow'),
        'festival_tick_interval_ms' => (int) env('TRIOSENSE_FIFO_FESTIVAL_TICK_INTERVAL_MS', 500),
    ],

    'pa' => [
        'controller_url' => env('TRIOSENSE_PA_CONTROLLER_URL'),
        'default_audio_path' => env('TRIOSENSE_PA_DEFAULT_AUDIO_PATH', '/var/lib/triosense/pa/default.mp3'),
        'tts_provider' => env('TRIOSENSE_PA_TTS_PROVIDER', 'stub'),
    ],

    'fcm' => [
        'server_key' => env('TRIOSENSE_FCM_SERVER_KEY'),
        'project_id' => env('TRIOSENSE_FCM_PROJECT_ID'),
        'credentials_path' => env('TRIOSENSE_FCM_CREDENTIALS_PATH'),
    ],

    'cross_counter' => [
        'buffer' => (int) env('TRIOSENSE_CROSS_COUNTER_BUFFER', 50),
    ],

    'sentry' => [
        'dsn' => env('SENTRY_LARAVEL_DSN'),
    ],
];
