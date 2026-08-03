<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\WebhookSubscription;
use App\Services\Identity\CompanyAuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Support\UsesMeinCompany;
use Tests\TestCase;

class Phase4WebhookTestTest extends TestCase
{
    use RefreshDatabase;
    use UsesMeinCompany;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        app(CompanyAuthorizationService::class)->bootstrapAllCompanies();
    }

    public function test_webhook_test_endpoint_delivers_sample_payload(): void
    {
        $company = $this->meinCompany();
        $admin = $this->meinUser('emilio.sanchez@mein-company.com');
        Sanctum::actingAs($admin);

        $subscription = WebhookSubscription::query()->create([
            'company_id' => $company->id,
            'name' => 'Hook test',
            'url' => 'https://webhook.site/test',
            'secret' => 'secret',
            'events' => ['routine.validated'],
            'is_active' => true,
        ]);

        Http::fake(['webhook.site/*' => Http::response('', 200)]);

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson("/api/v1/integrations/webhooks/{$subscription->id}/test")
            ->assertOk()
            ->assertJsonPath('data.success', true)
            ->assertJsonPath('data.status', 'ok');

        Http::assertSent(fn ($request) => $request->url() === 'https://webhook.site/test'
            && $request->hasHeader('X-Phoenix-Event', 'webhook.test'));
    }

    public function test_webhook_test_formats_slack_incoming_payload(): void
    {
        $company = $this->meinCompany();
        $admin = $this->meinUser('emilio.sanchez@mein-company.com');
        Sanctum::actingAs($admin);

        $subscription = WebhookSubscription::query()->create([
            'company_id' => $company->id,
            'name' => 'Slack',
            'url' => 'https://hooks.slack.com/services/T00/B00/secret',
            'secret' => 'secret',
            'events' => ['routine.validated'],
            'is_active' => true,
        ]);

        Http::fake(['hooks.slack.com/*' => Http::response('ok', 200)]);

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson("/api/v1/integrations/webhooks/{$subscription->id}/test")
            ->assertOk()
            ->assertJsonPath('data.success', true);

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return str_contains($request->url(), 'hooks.slack.com')
                && is_array($body)
                && isset($body['text'])
                && str_contains($body['text'], 'Prueba de entrega Phoenix');
        });
    }
}
