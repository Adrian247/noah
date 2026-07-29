<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\ReportTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportDesignerApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_rename_template_and_preview_html(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@pyro-systems.com')->first();
        $company = Company::query()->first();
        $token = $admin->createToken('test')->plainTextToken;
        $template = ReportTemplate::query()->where('company_id', $company->id)->first();

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->putJson("/api/v1/design/reports/{$template->id}", [
                'name' => 'Reporte personalizado demo',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Reporte personalizado demo');

        $response = $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->get("/api/v1/design/reports/{$template->id}/preview");

        $response->assertOk();
        $response->assertHeader('content-type', 'text/html; charset=UTF-8');
        $this->assertStringContainsString('Vista previa', $response->getContent());
        $this->assertStringContainsString('Informe de servicio', $response->getContent());
    }

    public function test_list_thumbnail_preview_uses_scaled_first_page(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@pyro-systems.com')->first();
        $company = Company::query()->first();
        $token = $admin->createToken('test')->plainTextToken;
        $template = ReportTemplate::query()->where('company_id', $company->id)->first();

        $response = $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->get("/api/v1/design/reports/{$template->id}/preview?thumbnail=1");

        $response->assertOk();
        $html = $response->getContent();
        $this->assertStringContainsString('report-thumb-fit', $html);
        $this->assertStringContainsString('report-page', $html);
        $this->assertStringNotContainsString('report-thumb-sheet', $html);
        $this->assertStringNotContainsString('Vista previa — datos de ejemplo', $html);
    }

    public function test_admin_can_save_page_settings_with_components(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@pyro-systems.com')->first();
        $company = Company::query()->first();
        $token = $admin->createToken('test')->plainTextToken;
        $template = ReportTemplate::query()->where('company_id', $company->id)->first();

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->putJson("/api/v1/design/reports/{$template->id}/components", [
                'components' => [
                    ['type' => 'title', 'text' => 'Mi título'],
                    ['type' => 'text', 'text' => 'Texto con **negrita**'],
                ],
                'page_settings' => [
                    'font_family' => 'source_sans',
                    'header' => ['enabled' => true, 'text' => 'Encabezado {{company}}'],
                    'footer' => ['enabled' => false, 'text' => ''],
                    'page_number' => ['enabled' => true, 'start_at' => 1],
                ],
            ])
            ->assertOk();

        $draft = $template->versions()->where('status', 'draft')->first()
            ?? $template->versions()->orderByDesc('version')->first();

        $this->assertSame('source_sans', $draft->page_settings['font_family'] ?? null);
        $this->assertCount(2, $draft->components);
    }

    public function test_admin_can_preview_draft_via_post(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@pyro-systems.com')->first();
        $company = Company::query()->first();
        $token = $admin->createToken('test')->plainTextToken;
        $template = ReportTemplate::query()->where('company_id', $company->id)->first();

        $response = $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->post("/api/v1/design/reports/{$template->id}/preview", [
                'components' => [
                    ['type' => 'title', 'text' => 'Título en vivo', 'align' => 'center'],
                    ['type' => 'paragraph', 'field' => 'kilometraje', 'align' => 'left'],
                ],
                'page_settings' => ['typography' => ['title_pt' => 22, 'body_pt' => 11]],
            ]);

        $response->assertOk();
        $this->assertStringContainsString('Título en vivo', $response->getContent());
    }

    public function test_admin_can_download_preview_pdf_from_draft(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@pyro-systems.com')->first();
        $company = Company::query()->first();
        $token = $admin->createToken('test')->plainTextToken;
        $template = ReportTemplate::query()->where('company_id', $company->id)->first();

        $response = $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->post("/api/v1/design/reports/{$template->id}/preview-pdf", [
                'components' => [
                    ['type' => 'title', 'text' => 'PDF borrador', 'align' => 'center'],
                ],
                'page_settings' => ['typography' => ['title_pt' => 22, 'body_pt' => 11]],
            ]);

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_admin_can_save_description(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@pyro-systems.com')->first();
        $company = Company::query()->first();
        $token = $admin->createToken('test')->plainTextToken;
        $template = ReportTemplate::query()->where('company_id', $company->id)->first();

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->putJson("/api/v1/design/reports/{$template->id}", [
                'name' => $template->name,
                'description' => 'Reporte operativo de rutina',
            ])
            ->assertOk()
            ->assertJsonPath('data.description', 'Reporte operativo de rutina');
    }

    public function test_preview_draft_accepts_empty_components(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@pyro-systems.com')->first();
        $company = Company::query()->first();
        $token = $admin->createToken('test')->plainTextToken;
        $template = ReportTemplate::query()->where('company_id', $company->id)->first();

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->postJson("/api/v1/design/reports/{$template->id}/preview", [
                'components' => [],
                'page_settings' => [],
            ])
            ->assertOk()
            ->assertHeader('content-type', 'text/html; charset=UTF-8');
    }

    public function test_admin_can_upload_cover_image_and_preview_shows_it(): void
    {
        $this->seed();
        Storage::fake('public');
        $admin = User::query()->where('email', 'admin@pyro-systems.com')->first();
        $company = Company::query()->first();
        $token = $admin->createToken('test')->plainTextToken;
        $template = ReportTemplate::query()->where('company_id', $company->id)->first();
        $draft = $template->versions()->where('status', 'draft')->orderByDesc('version')->first()
            ?? $template->versions()->orderByDesc('version')->first();
        $this->assertNotNull($draft);
        $draft->update([
            'page_settings' => array_merge($draft->page_settings ?? [], [
                'cover_page' => [
                    'enabled' => true,
                    'title' => 'Portada con logo',
                    'subtitle' => '',
                    'body' => '',
                    'show_date' => false,
                    'omit_header_footer' => true,
                ],
            ]),
        ]);

        $file = \Illuminate\Http\UploadedFile::fake()->image('cover.png', 400, 200);

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->post("/api/v1/design/reports/{$template->id}/cover-image", [
                'image' => $file,
            ])
            ->assertOk()
            ->assertJsonStructure(['data' => ['image_path', 'image_url', 'page_settings']]);

        $draftAfter = $template->versions()->where('status', 'draft')->orderByDesc('version')->first();
        $this->assertNotNull($draftAfter);
        $path = $draftAfter->page_settings['cover_page']['image_path'] ?? '';
        $this->assertNotSame('', $path);
        Storage::disk('public')->assertExists($path);

        $preview = $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->get("/api/v1/design/reports/{$template->id}/preview");

        $preview->assertOk();
        $this->assertStringContainsString('report-cover-image', $preview->getContent());
        $this->assertStringContainsString('Portada con logo', $preview->getContent());
    }

    public function test_admin_can_list_design_presets(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@pyro-systems.com')->first();
        $company = Company::query()->first();
        $token = $admin->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->getJson('/api/v1/design/reports/presets')
            ->assertOk()
            ->assertJsonPath('data.0.id', 'phoenix_industrial')
            ->assertJsonStructure(['data' => [['id', 'label', 'description', 'layout', 'swatch']]]);
    }

    public function test_admin_can_apply_preset_to_draft_from_routine_form(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@pyro-systems.com')->first();
        $company = Company::query()->first();
        $token = $admin->createToken('test')->plainTextToken;
        $template = ReportTemplate::query()->where('company_id', $company->id)->first();

        $response = $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->postJson("/api/v1/design/reports/{$template->id}/apply-preset", [
                'preset_id' => 'corporate_navy',
                'form_slug' => 'inspeccion-vehiculo-v1',
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.page_settings.theme.preset_id', 'corporate_navy');

        $draft = $template->versions()->where('status', 'draft')->orderByDesc('version')->first();
        $this->assertNotNull($draft);
        $this->assertNotEmpty($draft->components);
        $this->assertSame('corporate_navy', $draft->page_settings['theme']['preset_id'] ?? null);

        $preview = $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->get("/api/v1/design/reports/{$template->id}/preview");

        $preview->assertOk();
        $this->assertStringContainsString('#1e3a5f', $preview->getContent());
    }

    public function test_tenant_admin_can_delete_unlinked_report_template(): void
    {
        $this->seed();
        $tenantAdmin = User::query()->where('email', 'emilio.sanchez@mein-company.com')->first();
        $this->assertNotNull($tenantAdmin);
        $companyId = $tenantAdmin->memberships()->where('is_active', true)->value('company_id');
        $this->assertNotNull($companyId);
        $token = $tenantAdmin->createToken('test')->plainTextToken;

        $create = $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/design/reports', ['name' => 'Reporte desechable']);

        $create->assertCreated();
        $id = (int) $create->json('data.id');

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->deleteJson("/api/v1/design/reports/{$id}")
            ->assertNoContent();

        $this->assertNull(ReportTemplate::query()->withoutGlobalScopes()->find($id));
    }

    public function test_platform_admin_can_delete_unlinked_report_template(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@pyro-systems.com')->first();
        $company = Company::query()->first();
        $token = $admin->createToken('test')->plainTextToken;

        $create = $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->postJson('/api/v1/design/reports', ['name' => 'Reporte plataforma']);

        $create->assertCreated();
        $id = (int) $create->json('data.id');

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->deleteJson("/api/v1/design/reports/{$id}")
            ->assertNoContent();
    }

    public function test_cannot_delete_report_linked_to_routine_type(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@pyro-systems.com')->first();
        $company = Company::query()->first();
        $token = $admin->createToken('test')->plainTextToken;
        $template = ReportTemplate::query()->where('company_id', $company->id)->first();

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->deleteJson("/api/v1/design/reports/{$template->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['report']);
    }
}
