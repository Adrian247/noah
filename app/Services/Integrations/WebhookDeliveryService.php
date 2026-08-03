<?php

namespace App\Services\Integrations;

use App\Models\WebhookSubscription;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class WebhookDeliveryService
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{success: bool, status: string, http_status?: int}
     */
    public function deliver(
        WebhookSubscription $subscription,
        string $event,
        array $payload,
        bool $bypassEventFilter = false,
    ): array {
        if (! $subscription->is_active) {
            return ['success' => false, 'status' => 'inactive'];
        }

        $events = $subscription->events ?? [];
        if (
            ! $bypassEventFilter
            && ! in_array($event, $events, true)
            && ! in_array('*', $events, true)
        ) {
            return ['success' => false, 'status' => 'event_not_subscribed'];
        }

        $occurredAt = now()->toIso8601String();
        $body = app(WebhookPayloadFormatter::class)->encode(
            $subscription->url,
            $event,
            $payload,
            $occurredAt,
        );

        $request = Http::timeout(15)->withHeaders([
            'Content-Type' => 'application/json',
            'User-Agent' => 'Phoenix-Webhook/1.0',
            'X-Phoenix-Event' => $event,
            'X-Phoenix-Delivery' => (string) Str::uuid(),
        ]);

        if (is_string($subscription->secret) && $subscription->secret !== '') {
            $request = $request->withHeaders([
                'X-Phoenix-Signature' => hash_hmac('sha256', $body, $subscription->secret),
            ]);
        }

        try {
            $response = $request->withBody($body)->post($subscription->url);
            $status = $response->successful() ? 'ok' : 'error:'.$response->status();
            $subscription->update([
                'last_delivered_at' => now(),
                'last_status' => $status,
            ]);

            return [
                'success' => $response->successful(),
                'status' => $status,
                'http_status' => $response->status(),
            ];
        } catch (\Throwable $e) {
            $status = 'error:'.Str::limit($e->getMessage(), 120);
            $subscription->update([
                'last_delivered_at' => now(),
                'last_status' => $status,
            ]);
            report($e);

            return ['success' => false, 'status' => $status];
        }
    }
}
