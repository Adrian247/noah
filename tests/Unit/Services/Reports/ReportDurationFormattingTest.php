<?php

namespace Tests\Unit\Services\Reports;

use App\Models\RoutineExecution;
use App\Services\Reports\ReportHtmlBuilder;
use Tests\TestCase;

class ReportDurationFormattingTest extends TestCase
{
    public function test_duration_minutes_renders_as_hh_mm(): void
    {
        $builder = app(ReportHtmlBuilder::class);
        $execution = new RoutineExecution([
            'duration_minutes' => 90,
            'responses' => [],
        ]);

        $html = $builder->buildPreview(
            [['type' => 'paragraph', 'field' => 'duration_minutes']],
            [],
            ['duration_minutes' => 90],
        );

        $this->assertStringContainsString('01:30', $html);
        $this->assertStringContainsString('Tiempo en sitio', $html);
    }
}
