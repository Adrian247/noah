<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente FCM HTTP v1 con service account (sin SDK pesado).
 */
class FcmPushClient
{
    /**
     * @param  array<string, string>  $data
     */
    public function send(string $token, string $title, string $body, array $data = []): void
    {
        $projectId = (string) config('phoenix.push.fcm.project_id', '');
        if ($projectId === '') {
            throw new RuntimeException('FCM project_id no configurado.');
        }

        $accessToken = $this->accessToken();
        $payload = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => $data,
                'android' => [
                    'priority' => 'high',
                ],
                'apns' => [
                    'payload' => [
                        'aps' => [
                            'sound' => 'default',
                        ],
                    ],
                ],
            ],
        ];

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(15)
            ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", $payload);

        if ($response->successful()) {
            return;
        }

        $status = $response->status();
        $error = $response->json('error') ?? $response->body();
        $errorCode = is_array($error) ? (string) ($error['status'] ?? '') : '';
        $errorMessage = is_array($error) ? (string) ($error['message'] ?? json_encode($error)) : (string) $error;

        if ($status === 404 || in_array($errorCode, ['NOT_FOUND', 'INVALID_ARGUMENT', 'UNREGISTERED'], true)
            || str_contains(strtoupper($errorMessage), 'UNREGISTERED')
            || str_contains(strtoupper($errorMessage), 'NOT_FOUND')) {
            throw new InvalidPushTokenException($errorMessage !== '' ? $errorMessage : 'Invalid FCM token');
        }

        throw new RuntimeException('FCM send failed: '.$errorMessage);
    }

    private function accessToken(): string
    {
        return Cache::remember('phoenix.fcm.access_token', 3000, function (): string {
            $path = (string) config('phoenix.push.fcm.credentials', '');
            if ($path === '' || ! is_readable($path)) {
                throw new RuntimeException('FCM credentials file no legible: '.$path);
            }

            $credentials = json_decode((string) file_get_contents($path), true);
            if (! is_array($credentials)) {
                throw new RuntimeException('FCM credentials JSON inválido.');
            }

            $now = time();
            $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $claim = $this->base64UrlEncode(json_encode([
                'iss' => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]));

            $unsigned = $header.'.'.$claim;
            $privateKey = openssl_pkey_get_private((string) ($credentials['private_key'] ?? ''));
            if ($privateKey === false) {
                throw new RuntimeException('FCM private_key inválida.');
            }

            $signature = '';
            if (! openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
                throw new RuntimeException('No se pudo firmar el JWT de FCM.');
            }

            $jwt = $unsigned.'.'.$this->base64UrlEncode($signature);

            $tokenResponse = Http::asForm()
                ->timeout(15)
                ->post('https://oauth2.googleapis.com/token', [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ]);

            if (! $tokenResponse->successful()) {
                throw new RuntimeException('FCM OAuth falló: '.$tokenResponse->body());
            }

            $accessToken = $tokenResponse->json('access_token');
            if (! is_string($accessToken) || $accessToken === '') {
                throw new RuntimeException('FCM OAuth sin access_token.');
            }

            return $accessToken;
        });
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
