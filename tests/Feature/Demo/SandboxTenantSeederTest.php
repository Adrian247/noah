<?php

namespace Tests\Feature\Demo;

use App\Models\Asset;
use App\Models\Client;
use App\Models\Company;
use App\Models\Routine;
use App\Models\User;
use Database\Seeders\PhoenixDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SandboxTenantSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_sandbox_tenant_follows_playground_flag(): void
    {
        $this->seed();

        $company = Company::query()->where('name', 'Sandbox')->first();
        $this->assertNotNull($company);
        $this->assertSame('sandbox', $company->fiscal_provider);

        $admin = User::query()->where('email', \App\Support\DemoAccounts::DEFAULT_LOGIN_EMAIL)->first();
        $this->assertNotNull($admin);
        $this->assertTrue(
            $admin->memberships()
                ->where('company_id', $company->id)
                ->where('is_active', true)
                ->exists(),
        );

        if (PhoenixDemoSeeder::shouldSeedSandboxPlayground()) {
            $this->assertGreaterThan(0, Client::query()->where('company_id', $company->id)->count());
            $this->assertGreaterThan(0, Asset::query()->where('company_id', $company->id)->count());
            $this->assertTrue((bool) $company->allow_predictive_training_collection);

            return;
        }

        $this->assertFalse((bool) $company->allow_predictive_training_collection);
        $this->assertNull($company->predictive_algorithm_version_id);
        $this->assertSame(1, Client::query()->where('company_id', $company->id)->count());
        $this->assertSame(0, Asset::query()->where('company_id', $company->id)->count());
        $this->assertSame(0, Routine::query()->where('company_id', $company->id)->count());
    }
}
