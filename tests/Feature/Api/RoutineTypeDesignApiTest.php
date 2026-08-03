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
        $admin = User::query()->where('email', 'admin@pyro-systems.com')->first();
        $company = Company::query()->first();
        $type = RoutineType::query()->first();

        $formVersion = FormVersion::query()
            ->where('status', 'published')
            ->whereHas('definition', fn ($q) => $q->where('slug', 'revision-mayor-vehiculo-premium'))
            ->first();
        $this->assertNotNull($formVersion);

        $reportVersion = ReportTemplateVersion::query()
            ->where('status', 'published')
            ->whereHas('template', fn ($q) => $q->where('slug', 'informe-revision-mayor-vehiculo'))
            ->first();
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

    public function test_admin_cannot_link_misaligned_form_and_report(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@pyro-systems.com')->first();
        $company = Company::query()->first();
        $type = RoutineType::query()->first();

        $inspectionForm = FormVersion::query()
            ->where('status', 'published')
            ->whereHas('definition', fn ($q) => $q->where('slug', 'inspeccion-vehiculo-v1'))
            ->firstOrFail();

        $misalignedTemplate = \App\Models\ReportTemplate::query()->create([
            'company_id' => $company->id,
            'name' => 'Informe desalineado test',
            'slug' => 'informe-desalineado-test-'.uniqid(),
        ]);
        $misalignedReport = ReportTemplateVersion::query()->create([
            'report_template_id' => $misalignedTemplate->id,
            'version' => 1,
            'status' => 'published',
            'components' => [
                ['type' => 'paragraph', 'field' => 'frenos'],
                ['type' => 'paragraph', 'field' => 'kilometraje'],
            ],
            'created_by' => $admin->id,
        ]);

        Sanctum::actingAs($admin);

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->putJson("/api/v1/routine-types/{$type->id}/design", [
                'form_version_id' => $inspectionForm->id,
                'report_template_version_id' => $misalignedReport->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['form_version_id', 'report_template_version_id']);
    }

    public function test_tenant_admin_can_link_published_report_to_routine_type(): void
    {
        $this->seed();
        $company = Company::query()->where('name', 'Mein Company')->firstOrFail();
        $tenantAdmin = User::query()->where('email', 'emilio.sanchez@mein-company.com')->firstOrFail();
        $type = RoutineType::query()->where('company_id', $company->id)->firstOrFail();

        $formVersion = FormVersion::query()
            ->where('status', 'published')
            ->whereHas('definition', fn ($q) => $q->where('slug', 'revision-mayor-vehiculo-premium'))
            ->firstOrFail();

        Sanctum::actingAs($tenantAdmin);

        $create = $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson('/api/v1/design/reports', ['name' => 'Informe tenant enlazable']);

        $create->assertCreated();
        $templateId = (int) $create->json('data.id');

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->putJson("/api/v1/design/reports/{$templateId}/components", [
                'components' => [
                    ['type' => 'title', 'text' => 'Informe tenant'],
                    ['type' => 'paragraph', 'field' => 'kilometraje'],
                ],
                'page_settings' => [],
            ])
            ->assertOk();

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson("/api/v1/design/reports/{$templateId}/publish")
            ->assertOk();

        $published = ReportTemplateVersion::query()
            ->where('report_template_id', $templateId)
            ->where('status', 'published')
            ->firstOrFail();

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->putJson("/api/v1/routine-types/{$type->id}/design", [
                'form_version_id' => $formVersion->id,
                'report_template_version_id' => $published->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.report_template_version_id', $published->id);
    }

    public function test_tenant_admin_can_manage_routine_types_without_workflow_design(): void
    {
        $this->seed();
        $company = Company::query()->where('name', 'Mein Company')->firstOrFail();
        $tenantAdmin = User::query()->where('email', 'emilio.sanchez@mein-company.com')->firstOrFail();
        $type = RoutineType::query()->where('company_id', $company->id)->firstOrFail();

        Sanctum::actingAs($tenantAdmin);

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->getJson('/api/v1/routine-types?all=1')
            ->assertOk();

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->getJson('/api/v1/design/workflows')
            ->assertForbidden();

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->putJson("/api/v1/routine-types/{$type->id}/workflow", [
                'workflow_definition_id' => $type->workflow_definition_id,
            ])
            ->assertForbidden();
    }

    public function test_admin_can_create_update_and_delete_routine_type(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@pyro-systems.com')->first();
        $company = Company::query()->first();

        Sanctum::actingAs($admin);

        $create = $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson('/api/v1/routine-types', ['name' => 'Prueba CRUD'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Prueba CRUD')
            ->assertJsonPath('data.workflow_definition_id', fn ($id) => $id !== null);

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
