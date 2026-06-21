<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Firebase Cloud Messaging client supporting legacy server key or HTTP v1.
 */
final class FcmService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function send(?string $token, string $title, string $body, array $data = []): bool
    {
        if ($token === null || $token === '') {
            Log::debug('FcmService.send.skipped_no_token');

            return false;
        }

        $credentialsPath = config('triosense.fcm.credentials_path');
        $projectId = config('triosense.fcm.project_id');

        if (is_string($credentialsPath) && $credentialsPath !== ''
            && is_string($projectId) && $projectId !== '') {
            return $this->sendHttpV1($token, $title, $body, $data, $credentialsPath, $projectId);
        }

        return $this->sendLegacy($token, $title, $body, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function sendLegacy(string $token, string $title, string $body, array $data): bool
    {
        $serverKey = config('triosense.fcm.server_key');

        Log::info('FcmService.sendLegacy', ['title' => $title]);

        if ($serverKey === null || $serverKey === '') {
            Log::debug('FcmService.sendLegacy.stub_no_server_key');

            return false;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'key='.$serverKey,
                'Content-Type' => 'application/json',
            ])->post('https://fcm.googleapis.com/fcm/send', [
                'to' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => $data,
            ]);

            if (! $response->successful()) {
                Log::warning('FcmService.sendLegacy.failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('FcmService.sendLegacy.exception', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function sendHttpV1(
        string $token,
        string $title,
        string $body,
        array $data,
        string $credentialsPath,
        string $projectId,
    ): bool {
        Log::info('FcmService.sendHttpV1', ['title' => $title, 'project_id' => $projectId]);

        if (! is_readable($credentialsPath)) {
            Log::warning('FcmService.sendHttpV1.credentials_unreadable', [
                'path' => $credentialsPath,
            ]);

            return false;
        }

        $credentials = json_decode((string) file_get_contents($credentialsPath), true);
        if (! is_array($credentials) || ! isset($credentials['client_email'], $credentials['private_key'])) {
            Log::error('FcmService.sendHttpV1.invalid_credentials_json');

            return false;
        }

        $accessToken = $this->fetchGoogleAccessToken($credentials);
        if ($accessToken === null) {
            return false;
        }

        $stringData = [];
        foreach ($data as $key => $value) {
            $stringData[(string) $key] = is_scalar($value) ? (string) $value : json_encode($value);
        }

        try {
            $response = Http::withToken($accessToken)
                ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                    'message' => [
                        'token' => $token,
                        'notification' => [
                            'title' => $title,
                            'body' => $body,
                        ],
                        'data' => $stringData,
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('FcmService.sendHttpV1.failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('FcmService.sendHttpV1.exception', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    private function fetchGoogleAccessToken(array $credentials): ?string
    {
        $now = time();
        $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $claim = rtrim(strtr(base64_encode(json_encode([
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ])), '+/', '-_'), '=');

        $unsigned = $header.'.'.$claim;
        $signature = '';
        $privateKey = openssl_pkey_get_private((string) $credentials['private_key']);

        if ($privateKey === false) {
            Log::error('FcmService.fetchGoogleAccessToken.invalid_private_key');

            return null;
        }

        openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        $jwt = $unsigned.'.'.rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

        try {
            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if (! $response->successful()) {
                Log::error('FcmService.fetchGoogleAccessToken.failed', [
                    'status' => $response->status(),
                ]);

                return null;
            }

            return $response->json('access_token');
        } catch (\Throwable $e) {
            Log::error('FcmService.fetchGoogleAccessToken.exception', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
