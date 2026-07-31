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
            'events' => ['*'],
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
}
