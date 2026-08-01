<?php

namespace Tests\Unit\Services\AI;

use App\Services\AI\AssistantDashboardBuilder;
use PHPUnit\Framework\TestCase;

class AssistantDashboardBuilderTest extends TestCase
{
    public function test_detects_dashboard_intent(): void
    {
        $builder = new AssistantDashboardBuilder;

        $this->assertTrue($builder->wantsDashboard('muéstrame el dashboard de KPIs'));
        $this->assertTrue($builder->wantsDashboard('¿Cuáles son los indicadores?'));
        $this->assertFalse($builder->wantsDashboard('lista rutinas recientes'));
    }

    public function test_builds_dashboard_from_kpi_payload(): void
    {
        $builder = new AssistantDashboardBuilder;
        $presentation = $builder->fromOperationalKpis([
            'routines' => [
                'total' => 10,
                'by_status' => ['open' => 4, 'validated' => 6],
                'completion_pct' => 60.0,
            ],
            'invoices' => [
                'by_status' => ['issued' => 2],
                'issued_amount' => 1500.5,
            ],
            'assets' => ['total' => 8],
        ]);

        $this->assertNotNull($presentation);
        $this->assertSame('dashboard', $presentation['type']);
        $this->assertSame('KPIs operativos', $presentation['title']);
        $this->assertNotEmpty($presentation['content']['charts']);
        $types = array_column($presentation['content']['charts'], 'type');
        $this->assertContains('kpi', $types);
        $this->assertContains('kpi-grid', $types);
        $this->assertContains('table', $types);
    }

    public function test_returns_null_when_empty_kpis(): void
    {
        $builder = new AssistantDashboardBuilder;

        $this->assertNull($builder->fromOperationalKpis([
            'routines' => ['total' => 0, 'by_status' => []],
            'invoices' => ['by_status' => []],
            'assets' => ['total' => 0],
        ]));
    }
}
