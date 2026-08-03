<?php

namespace App\Services\Notifications;

use App\Jobs\SendPushNotificationJob;
use App\Models\DevicePushToken;
use Illuminate\Support\Facades\Log;

class PushNotifier
{
    public function __construct(
        private readonly FcmPushClient $fcm,
    ) {}

    /**
     * @param  list<int>|iterable<int>  $userIds
     * @param  array<string, mixed>  $data
     */
    public function notifyUsers(iterable $userIds, string $title, string $body, array $data = []): void
    {
        if (! filter_var(config('phoenix.push.enabled', true), FILTER_VALIDATE_BOOL)) {
            return;
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', is_array($userIds) ? $userIds : iterator_to_array($userIds)))));
        if ($ids === []) {
            return;
        }

        $title = trim(strip_tags($title));
        $body = trim(preg_replace('/\s+/u', ' ', strip_tags($body)) ?? '');
        if ($title === '') {
            $title = 'Phoenix';
        }

        SendPushNotificationJob::dispatch($ids, $title, $body, $this->stringifyData($data));
    }

    /**
     * Entrega síncrona (invocada por el job).
     *
     * @param  list<int>  $userIds
     * @param  array<string, string>  $data
     */
    public function deliver(array $userIds, string $title, string $body, array $data = []): void
    {
        $tokens = DevicePushToken::query()
            ->whereIn('user_id', $userIds)
            ->get();

        if ($tokens->isEmpty()) {
            Log::debug('PushNotifier: no device tokens', ['user_ids' => $userIds]);

            return;
        }

        $driver = (string) config('phoenix.push.driver', 'log');

        foreach ($tokens as $row) {
            try {
                if ($driver === 'fcm') {
                    $this->fcm->send($row->token, $title, $body, $data);
                } else {
                    Log::info('PushNotifier[log]', [
                        'user_id' => $row->user_id,
                        'device_id' => $row->device_id,
                        'platform' => $row->platform,
                        'token_suffix' => substr($row->token, -8),
                        'title' => $title,
                        'body' => $body,
                        'data' => $data,
                    ]);
                }
            } catch (InvalidPushTokenException $e) {
                Log::warning('PushNotifier: removing invalid token', [
                    'token_id' => $row->id,
                    'error' => $e->getMessage(),
                ]);
                $row->delete();
            } catch (\Throwable $e) {
                Log::error('PushNotifier: send failed', [
                    'token_id' => $row->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function stringifyData(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $out[(string) $key] = (string) ($value ?? '');
            }
        }

        return $out;
    }
}
