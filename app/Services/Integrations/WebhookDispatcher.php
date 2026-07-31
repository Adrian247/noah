<?php

namespace App\Services\Integrations;

use App\Jobs\DispatchWebhookJob;
use App\Models\WebhookSubscription;

class WebhookDispatcher
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function dispatch(int $companyId, string $event, array $payload): void
    {
        WebhookSubscription::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->get()
            ->each(function (WebhookSubscription $subscription) use ($event, $payload): void {
                DispatchWebhookJob::dispatch($subscription->id, $event, $payload);
            });
    }
}
