<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\ReportSectionTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportSectionTemplateApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_and_create_section_templates(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@pyro-systems.com')->first();
        $company = Company::query()->first();
        $token = $admin->createToken('test')->plainTextToken;
        $headers = [
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $company->id,
        ];

        $this->getJson('/api/v1/design/reports/section-templates', $headers)
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name', 'slug', 'body']]]);

        $create = $this->postJson('/api/v1/design/reports/section-templates', [
            'name' => 'Bloque legal',
            'slug' => 'bloque-legal-test',
            'description' => 'Prueba',
            'body' => '<p>Texto <strong>rico</strong></p>',
        ], $headers);

        $create->assertCreated();
        $id = $create->json('data.id');
        $this->assertNotNull($id);

        $this->putJson("/api/v1/design/reports/section-templates/{$id}", [
            'name' => 'Bloque legal actualizado',
            'body' => '<p>Actualizado</p>',
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.name', 'Bloque legal actualizado');

        $this->deleteJson("/api/v1/design/reports/section-templates/{$id}", [], $headers)
            ->assertNoContent();

        $this->assertNull(ReportSectionTemplate::query()->find($id));
    }

    public function test_report_show_includes_section_templates(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@pyro-systems.com')->first();
        $company = Company::query()->first();
        $token = $admin->createToken('test')->plainTextToken;
        $template = \App\Models\ReportTemplate::query()->where('company_id', $company->id)->first();

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->getJson("/api/v1/design/reports/{$template->id}")
            ->assertOk()
            ->assertJsonStructure(['section_templates' => [['id', 'name', 'slug', 'body']]])
            ->assertJsonPath('section_templates.0.slug', 'alcance-servicio-premium');
    }
}
