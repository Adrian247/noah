<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\FormVersion;
use App\Models\ReportTemplateVersion;
use App\Models\RoutineType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoutineTypeDesignApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_link_published_form_and_report_versions(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@noah.local')->first();
        $company = Company::query()->first();
        $type = RoutineType::query()->first();

        $formVersion = FormVersion::query()->where('status', 'published')->first();
        $this->assertNotNull($formVersion);

        $reportVersion = ReportTemplateVersion::query()->where('status', 'published')->first();
        $this->assertNotNull($reportVersion);

        Sanctum::actingAs($admin);

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->putJson("/api/v1/routine-types/{$type->id}/design", [
                'form_version_id' => $formVersion->id,
                'report_template_version_id' => $reportVersion->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.form_version_id', $formVersion->id);
    }

    public function test_admin_can_create_update_and_delete_routine_type(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@noah.local')->first();
        $company = Company::query()->first();

        Sanctum::actingAs($admin);

        $create = $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson('/api/v1/routine-types', ['name' => 'Prueba CRUD'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Prueba CRUD');

        $id = $create->json('data.id');

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->putJson("/api/v1/routine-types/{$id}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->deleteJson("/api/v1/routine-types/{$id}")
            ->assertNoContent();
    }
}
