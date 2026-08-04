<?php

namespace Tests\Feature\Demo;

use App\Models\Asset;
use App\Models\Client;
use App\Models\Company;
use App\Models\FailureMode;
use App\Models\Routine;
use App\Models\User;
use Database\Seeders\Support\TenantDemoProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VirginTenantSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_mein_and_domg_are_seeded_virgin_with_staff_and_internal_client_only(): void
    {
        $this->seed();

        foreach ([TenantDemoProfile::mein(), TenantDemoProfile::domG()] as $profile) {
            $company = Company::query()->where('name', $profile->companyName)->first();
            $this->assertNotNull($company, $profile->companyName.' missing');
            $this->assertFalse((bool) $company->allow_predictive_training_collection);
            $this->assertNull($company->predictive_algorithm_version_id);

            $this->assertSame(
                1,
                Client::query()->where('company_id', $company->id)->count(),
                $profile->companyName.' should keep only the internal client',
            );
            $this->assertTrue(
                Client::query()
                    ->where('company_id', $company->id)
                    ->where('code', $profile->clientCode)
                    ->exists(),
            );

            $this->assertSame(0, Asset::query()->where('company_id', $company->id)->count());
            $this->assertSame(0, Routine::query()->where('company_id', $company->id)->count());
            $this->assertSame(0, FailureMode::query()->where('company_id', $company->id)->count());
            $this->assertSame(0, DB::table('equipment_work_orders')->where('company_id', $company->id)->count());

            foreach ($profile->staff as $staff) {
                $user = User::query()->where('email', $staff['email'])->first();
                $this->assertNotNull($user, $staff['email']);
                $this->assertTrue(
                    $user->memberships()
                        ->where('company_id', $company->id)
                        ->where('is_active', true)
                        ->exists(),
                    $staff['email'].' membership',
                );
            }
        }
    }

    public function test_refresh_re_purges_predictive_residue_on_virgin_tenants(): void
    {
        $this->seed();

        $mein = Company::query()->where('name', 'Mein Company')->firstOrFail();
        FailureMode::query()->create([
            'company_id' => $mein->id,
            'code' => 'TEST-FM',
            'name' => 'Modo residual',
            'system' => 'motor_diesel',
            'severity' => 'medium',
            'sort_order' => 1,
        ]);
        DB::table('equipment_work_orders')->insert([
            'company_id' => $mein->id,
            'asset_id' => null,
            'order_number' => 'WO-RESIDUE-1',
            'planned_for' => now()->toDateString(),
            'status' => 'planned',
            'source' => 'manual',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->seed();

        $this->assertSame(0, FailureMode::query()->where('company_id', $mein->id)->count());
        $this->assertSame(0, DB::table('equipment_work_orders')->where('company_id', $mein->id)->count());
        $this->assertTrue(
            User::query()->where('email', 'emilio.sanchez@mein-company.com')->exists(),
        );
    }
}
