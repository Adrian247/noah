<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BillingSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_billing_user_can_update_settings(): void
    {
        $this->seed();
        $billing = User::query()->where('email', 'billing@sandbox-demo.com')->first();
        $company = Company::query()->first();

        Sanctum::actingAs($billing);

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->putJson('/api/v1/billing/settings', [
                'billing_labor_rate_per_hour' => 250,
                'billing_tax_rate' => 0.16,
            ])
            ->assertOk()
            ->assertJsonPath('data.billing_labor_rate_per_hour', '250.00');

        $company->refresh();
        $this->assertEquals('250.00', $company->billing_labor_rate_per_hour);
    }
}
