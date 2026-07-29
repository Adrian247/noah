<?php

namespace Tests\Unit\Services\Reports;

use App\Models\Company;
use App\Models\ReportTemplate;
use App\Models\ReportTemplateVersion;
use App\Models\RoutineType;
use App\Services\Reports\ReportTemplateGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReportTemplateGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_blocks_delete_when_routine_type_uses_report_version(): void
    {
        $this->seed();
        $company = Company::query()->first();
        $template = ReportTemplate::query()->where('company_id', $company->id)->first();
        $version = $template->versions()->where('status', 'published')->first()
            ?? $template->versions()->first();
        $this->assertNotNull($version);

        RoutineType::query()->where('company_id', $company->id)->first()?->update([
            'report_template_version_id' => $version->id,
        ]);

        $guard = new ReportTemplateGuard;

        $this->expectException(ValidationException::class);
        $guard->assertCanDelete($template);
    }

    public function test_allows_delete_when_no_routine_type_links(): void
    {
        $this->seed();
        $company = Company::query()->first();
        $template = ReportTemplate::query()->create([
            'company_id' => $company->id,
            'name' => 'Temporal',
            'slug' => 'temporal-delete-'.uniqid(),
        ]);
        ReportTemplateVersion::query()->create([
            'report_template_id' => $template->id,
            'version' => 1,
            'status' => 'draft',
            'components' => [['type' => 'title', 'text' => 'X']],
            'page_settings' => ['size' => 'A4'],
            'created_by' => null,
        ]);

        $guard = new ReportTemplateGuard;
        $guard->assertCanDelete($template);

        $this->assertSame([], $guard->deleteBlockers($template));
    }
}
