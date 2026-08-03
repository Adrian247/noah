<?php

namespace Tests\Feature\Demo;

use App\Models\Asset;
use App\Models\Client;
use App\Models\Company;
use App\Models\Routine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SandboxTenantSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_sandbox_tenant_is_seeded_virgin_with_admin_only(): void
    {
        $this->seed();

        $company = Company::query()->where('name', 'Sandbox')->first();
        $this->assertNotNull($company);
        $this->assertFalse((bool) $company->allow_predictive_training_collection);
        $this->assertNull($company->predictive_algorithm_version_id);
        $this->assertSame('sandbox', $company->fiscal_provider);

        $this->assertSame(0, Client::query()->where('company_id', $company->id)->count());
        $this->assertSame(0, Asset::query()->where('company_id', $company->id)->count());
        $this->assertSame(0, Routine::query()->where('company_id', $company->id)->count());

        $admin = User::query()->where('email', \App\Support\DemoAccounts::DEFAULT_LOGIN_EMAIL)->first();
        $this->assertNotNull($admin);
        $this->assertTrue(
            $admin->memberships()
                ->where('company_id', $company->id)
                ->where('is_active', true)
                ->exists(),
        );
    }
}
