<?php

namespace App\Jobs;

use App\Models\WebhookSubscription;
use App\Services\Integrations\WebhookDeliveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

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

    public function handle(WebhookDeliveryService $delivery): void
    {
        $subscription = WebhookSubscription::query()->find($this->subscriptionId);
        if ($subscription === null) {
            return;
        }

        $delivery->deliver($subscription, $this->event, $this->payload);
    }
}
