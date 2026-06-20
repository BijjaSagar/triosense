<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

final class MetricsController extends Controller
{
    public function prometheus(): JsonResponse
    {
        Log::debug('MetricsController.prometheus');

        $lines = [
            '# HELP triosense_fifo_ticks_total Total FIFO ticks processed',
            '# TYPE triosense_fifo_ticks_total counter',
            'triosense_fifo_ticks_total '.(int) Redis::get('triosense:metrics:fifo_ticks_total'),
            '# HELP triosense_mqtt_events_total Total MQTT events ingested',
            '# TYPE triosense_mqtt_events_total counter',
            'triosense_mqtt_events_total '.(int) Redis::get('triosense:metrics:mqtt_events_total'),
            '# HELP triosense_active_locations Active location count',
            '# TYPE triosense_active_locations gauge',
            'triosense_active_locations 3',
        ];

        return response()->json([
            'format' => 'prometheus_text',
            'metrics' => implode("\n", $lines),
        ]);
    }
}
