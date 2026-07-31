<?php

namespace App\Jobs;

use App\Models\WebhookSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class DispatchWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public int $subscriptionId,
        public string $event,
        public array $payload,
    ) {}

    public function handle(): void
    {
        $subscription = WebhookSubscription::query()->find($this->subscriptionId);
        if ($subscription === null || ! $subscription->is_active) {
            return;
        }

        $events = $subscription->events ?? [];
        if (! in_array($this->event, $events, true) && ! in_array('*', $events, true)) {
            return;
        }

        $body = json_encode([
            'event' => $this->event,
            'occurred_at' => now()->toIso8601String(),
            'data' => $this->payload,
        ], JSON_THROW_ON_ERROR);

        $request = Http::timeout(15)->withHeaders([
            'Content-Type' => 'application/json',
            'User-Agent' => 'Phoenix-Webhook/1.0',
            'X-Phoenix-Event' => $this->event,
            'X-Phoenix-Delivery' => (string) Str::uuid(),
        ]);

        if (is_string($subscription->secret) && $subscription->secret !== '') {
            $request = $request->withHeaders([
                'X-Phoenix-Signature' => hash_hmac('sha256', $body, $subscription->secret),
            ]);
        }

        try {
            $response = $request->withBody($body)->post($subscription->url);
            $subscription->update([
                'last_delivered_at' => now(),
                'last_status' => $response->successful() ? 'ok' : 'error:'.$response->status(),
            ]);
        } catch (\Throwable $e) {
            $subscription->update([
                'last_delivered_at' => now(),
                'last_status' => 'error:'.Str::limit($e->getMessage(), 120),
            ]);
            report($e);
        }
    }
}
