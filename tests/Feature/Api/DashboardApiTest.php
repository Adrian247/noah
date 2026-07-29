<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Routine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_summary_returns_counts(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'admin@pyro-systems.com')->first();
        $company = Company::query()->first();

        Sanctum::actingAs($user);
        $response = $this->withHeader('X-Company-Id', (string) $company->id)
            ->getJson('/api/v1/dashboard/summary');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'routines_pending_validation',
                    'routines_assigned',
                    'routines_validated',
                    'invoices_draft',
                    'operations',
                    'catalog',
                    'design',
                    'inventory',
                    'focus_routines',
                    'recent_activity',
                    'generated_at',
                ],
            ]);
    }
}
