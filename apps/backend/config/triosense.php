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
    ],
];
