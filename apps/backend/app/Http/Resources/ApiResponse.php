<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

trait ApiResponse
{
    /**
     * @param  array<string, mixed>|null  $data
     */
    protected function successResponse(?array $data = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => $this->responseMeta(),
        ], $status);
    }

    /**
     * @param  array<string, mixed>|list<array<string, mixed>>|null  $details
     */
    protected function errorResponse(
        string $code,
        string $message,
        ?array $details = null,
        int $status = 400,
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
            ],
            'meta' => $this->responseMeta(),
        ], $status);
    }

    /**
     * @return array{request_id: string, timestamp: string}
     */
    private function responseMeta(): array
    {
        return [
            'request_id' => (string) Str::uuid(),
            'timestamp' => now()->utc()->toIso8601String(),
        ];
    }
}
