<?php

namespace Tests\Unit\Services\Reports;

use App\Models\FormDefinition;
use App\Models\FormVersion;
use App\Models\ReportTemplate;
use App\Models\ReportTemplateVersion;
use App\Models\User;
use App\Services\Reports\FormReportFieldAlignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormReportFieldAlignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_detects_missing_image_and_paragraph_fields(): void
    {
        $this->seed();

        $form = FormDefinition::query()->where('slug', 'revision-mayor-vehiculo-premium')->firstOrFail();
        $formVersion = $form->versions()->where('status', 'published')->firstOrFail();
        $author = User::query()->where('email', 'admin@sandbox-demo.com')->firstOrFail();

        $template = ReportTemplate::query()->create([
            'company_id' => $form->company_id,
            'name' => 'Misaligned',
            'slug' => 'misaligned-test',
        ]);
        $reportVersion = ReportTemplateVersion::query()->create([
            'report_template_id' => $template->id,
            'version' => 1,
            'status' => 'published',
            'components' => [
                ['type' => 'paragraph', 'field' => 'kilometraje'],
                ['type' => 'image', 'field' => 'foto_inexistente'],
                ['type' => 'paragraph', 'field' => 'campo_fantasma'],
            ],
            'created_by' => $author->id,
        ]);

        $result = app(FormReportFieldAlignment::class)->compare($formVersion, $reportVersion);

        $this->assertFalse($result['aligned']);
        $this->assertContains('foto_inexistente', $result['missing']);
        $this->assertContains('campo_fantasma', $result['missing']);
        $this->assertContains('foto_inexistente', $result['missing_images']);
        $this->assertNotContains('kilometraje', $result['missing']);
    }
}
