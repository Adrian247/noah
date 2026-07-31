<?php

namespace Tests\Unit\Jobs;

use App\Jobs\DispatchWebhookJob;
use App\Models\Company;
use App\Models\WebhookSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DispatchWebhookJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_delivers_phoenix_json_with_signature_headers(): void
    {
        $this->seed();

        $company = Company::query()->firstOrFail();
        $subscription = WebhookSubscription::query()->create([
            'company_id' => $company->id,
            'name' => 'Webhook.site',
            'url' => 'https://webhook.site/test-endpoint',
            'secret' => 'test-secret',
            'events' => ['routine.validated'],
            'is_active' => true,
        ]);

        Http::fake([
            'webhook.site/*' => Http::response('', 200),
        ]);

        $job = new DispatchWebhookJob($subscription->id, 'routine.validated', [
            'routine_id' => 99,
            'status' => 'validated',
        ]);
        $job->handle(app(\App\Services\Integrations\WebhookDeliveryService::class));

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return $request->url() === 'https://webhook.site/test-endpoint'
                && $body['event'] === 'routine.validated'
                && $body['data']['routine_id'] === 99
                && $request->hasHeader('X-Phoenix-Event', 'routine.validated')
                && $request->hasHeader('X-Phoenix-Signature');
        });

        $subscription->refresh();
        $this->assertSame('ok', $subscription->last_status);
    }
}
