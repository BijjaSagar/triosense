<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Sends push notifications via Firebase Cloud Messaging.
 */
final class PushNotificationService
{
    public function __construct(
        private readonly FcmService $fcm,
    ) {
    }
    /**
     * @param  list<int>  $userIds
     * @param  array<string, mixed>  $data
     */
    public function sendToUsers(array $userIds, string $title, string $body, array $data = []): void
    {
        if ($userIds === []) {
            return;
        }

        $users = User::query()
            ->whereIn('user_id', $userIds)
            ->whereNotNull('fcm_token')
            ->get(['user_id', 'fcm_token']);

        foreach ($users as $user) {
            $this->send($user->fcm_token, $title, $body, $data);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function send(?string $token, string $title, string $body, array $data = []): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        Log::info('PushNotificationService.send', [
            'title' => $title,
            'has_token' => true,
        ]);

        return $this->fcm->send($token, $title, $body, $data);
    }
}
