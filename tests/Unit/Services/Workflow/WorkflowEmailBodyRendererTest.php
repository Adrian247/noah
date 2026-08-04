<?php

namespace Tests\Unit\Services\Workflow;

use App\Mail\WorkflowStepMail;
use App\Models\Asset;
use App\Models\Company;
use App\Models\Routine;
use App\Models\RoutineType;
use App\Models\Site;
use App\Models\User;
use App\Services\Workflow\WorkflowEmailBodyRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowEmailBodyRendererTest extends TestCase
{
    use RefreshDatabase;

    public function test_renders_html_tokens_and_falls_back_asset_name_to_tag(): void
    {
        $this->seed();
        $company = Company::query()->where('name', 'Sandbox')->firstOrFail();
        $technician = User::query()->where('email', 'technician@sandbox-demo.com')->firstOrFail();
        $site = Site::query()->where('company_id', $company->id)->firstOrFail();
        $type = RoutineType::query()->where('company_id', $company->id)->firstOrFail();
        $asset = Asset::query()->where('company_id', $company->id)->firstOrFail();
        $asset->update(['tag' => 'L200-DEMO']);

        $routine = Routine::query()->create([
            'company_id' => $company->id,
            'site_id' => $site->id,
            'asset_id' => $asset->id,
            'routine_type_id' => $type->id,
            'assigned_to' => $technician->id,
            'status' => \App\Enums\RoutineStatus::Assigned,
        ]);

        $html = app(WorkflowEmailBodyRenderer::class)->render($routine, [
            'body_html' => '<p>Hola {user.name}, activo {asset.name} / {asset.tag}</p>',
        ], $technician);

        $this->assertStringContainsString('<p>Hola Técnico Sandbox, activo', $html);
        $this->assertStringContainsString('L200-DEMO', $html);
        $this->assertStringNotContainsString('{asset.', $html);
        $this->assertStringNotContainsString('activo —', $html);
    }

    public function test_workflow_step_mail_exposes_html_without_escaping_tags(): void
    {
        $this->seed();
        $company = Company::query()->where('name', 'Sandbox')->firstOrFail();
        $technician = User::query()->where('email', 'technician@sandbox-demo.com')->firstOrFail();
        $site = Site::query()->where('company_id', $company->id)->firstOrFail();
        $type = RoutineType::query()->where('company_id', $company->id)->firstOrFail();
        $asset = Asset::query()->where('company_id', $company->id)->firstOrFail();

        $routine = Routine::query()->create([
            'company_id' => $company->id,
            'site_id' => $site->id,
            'asset_id' => $asset->id,
            'routine_type_id' => $type->id,
            'assigned_to' => $technician->id,
            'status' => \App\Enums\RoutineStatus::Assigned,
        ])->load(['asset', 'routineType']);

        $mailable = new WorkflowStepMail(
            $routine,
            'Asunto prueba',
            '<p>Hola Técnico Sandbox, servicio listo.</p>',
        );

        $mailable->assertSeeInHtml('Hola Técnico Sandbox, servicio listo.', false);
        $mailable->assertDontSeeInHtml('&lt;p&gt;');
        $mailable->assertSeeInOrderInText(['Servicio #', 'Hola Técnico Sandbox, servicio listo.']);
    }
}
