<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterFcmTokenRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

final class FcmTokenController extends Controller
{
    public function store(RegisterFcmTokenRequest $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return $this->errorResponse('unauthenticated', 'Authentication required.', status: 401);
        }

        $token = $request->validated('fcm_token');

        Log::info('FcmTokenController.store', ['user_id' => $user->user_id]);

        $user->update(['fcm_token' => $token]);

        return $this->successResponse(['registered' => true]);
    }
}
