<?php

namespace Tests\Feature\Predictive;

use App\Models\Asset;
use App\Models\EquipmentFailure;
use App\Models\EquipmentShiftLog;
use App\Services\Predictive\LogbookIngestService;
use App\Services\Predictive\PredictiveMaintenanceService;
use App\Support\Predictive\FailureModeCatalog;
use App\Support\Predictive\OemCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\UsesMeinCompany;
use Tests\TestCase;

/**
 * Regresión con las bitácoras reales que originaron el módulo.
 *
 * Los fixtures son el recorte de `Bitácora Planta 4400.xlsm` y `SanMartin - Reporte Diario SEP 09
 * 2020.xlsm` (ver ml/phoenix-predict/scripts/make_test_fixture.py). Fijan tres cosas que ya se
 * rompieron durante el desarrollo: que la ingesta sea idempotente, que la taxonomía siga
 * clasificando el texto libre del original, y que el motor conserve poder de discriminación
 * medible en lugar de degradarse en silencio con un cambio de pesos.
 */
class LogbookRegressionTest extends TestCase
{
    use RefreshDatabase;
    use UsesMeinCompany;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function fixture(string $name): array
    {
        $path = base_path("tests/Fixtures/Predictive/{$name}.json");

        return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    private function ingest(string $name): array
    {
        return app(LogbookIngestService::class)->ingest((int) $this->meinCompany()->id, $this->fixture($name));
    }

    public function test_plant_logbook_ingests_and_repeating_it_creates_nothing_new(): void
    {
        $companyId = (int) $this->meinCompany()->id;

        $first = $this->ingest('plant-logbook');

        $this->assertSame(18, $first['assets']);
        $this->assertSame(1481, $first['shift_logs']);
        $this->assertSame(102, $first['failures']);

        $counts = [
            'assets' => Asset::withoutGlobalScope('company')->where('company_id', $companyId)->count(),
            'shift_logs' => EquipmentShiftLog::withoutGlobalScope('company')->where('company_id', $companyId)->count(),
            'failures' => EquipmentFailure::withoutGlobalScope('company')->where('company_id', $companyId)->count(),
        ];

        $this->ingest('plant-logbook');

        $this->assertSame(
            $counts['shift_logs'],
            EquipmentShiftLog::withoutGlobalScope('company')->where('company_id', $companyId)->count(),
            'La segunda ingesta duplicó bitácoras.',
        );
        $this->assertSame(
            $counts['failures'],
            EquipmentFailure::withoutGlobalScope('company')->where('company_id', $companyId)->count(),
            'La segunda ingesta duplicó fallas.',
        );
    }

    public function test_free_text_failures_are_classified_by_the_taxonomy(): void
    {
        $companyId = (int) $this->meinCompany()->id;

        $this->ingest('plant-logbook');
        $this->ingest('underground-logbook');

        $total = EquipmentFailure::withoutGlobalScope('company')->where('company_id', $companyId)->count();
        $classified = EquipmentFailure::withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->whereNotNull('failure_mode_id')
            ->count();

        $this->assertGreaterThan(0, $total);
        // Las bitácoras escriben el mismo modo de muchas formas ("reparacio de eje", "eje dañado").
        // Si la cobertura baja de 90 % es que la taxonomía perdió patrones.
        $this->assertGreaterThanOrEqual(0.90, $classified / $total, sprintf(
            'Cobertura de clasificación %.1f %% (%d de %d).',
            $classified / $total * 100,
            $classified,
            $total,
        ));
    }

    public function test_shift_log_dates_stay_inside_the_period_each_logbook_covers(): void
    {
        $companyId = (int) $this->meinCompany()->id;
        $this->ingest('underground-logbook');

        $span = EquipmentShiftLog::withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->selectRaw('MIN(logged_on) as first_day, MAX(logged_on) as last_day')
            ->first();

        // El libro original trae dos renglones tecleados con fecha de enero 2020; el ETL los
        // descarta porque extienden la cobertura ocho meses y falsean cualquier ventana.
        $this->assertSame('2020-09-01', (string) $span->first_day);
        $this->assertSame('2020-09-13', (string) $span->last_day);
    }

    public function test_engine_keeps_measurable_discrimination_on_real_history(): void
    {
        $companyId = (int) $this->meinCompany()->id;

        OemCatalog::sync();
        FailureModeCatalog::syncForCompany($companyId);
        $this->ingest('plant-logbook');

        $report = app(PredictiveMaintenanceService::class)->backtest($companyId, 14, 7);

        $this->assertGreaterThan(100, $report['rows']);
        $this->assertGreaterThan(0, $report['positives']);

        // Piso deliberadamente holgado respecto al 0.76 medido: protege contra una regresión real
        // sin volverse frágil cuando se recalibra un factor.
        $this->assertGreaterThanOrEqual(0.65, $report['roc_auc'], sprintf(
            'ROC AUC cayó a %s sobre %d observaciones.',
            $report['roc_auc'],
            $report['rows'],
        ));

        // Y sobre todo: los niveles tienen que ordenar. Riesgo bajo debe fallar menos que alto.
        $levels = $report['by_risk_level'];
        $this->assertArrayHasKey('low', $levels);
        $highRate = max(
            $levels['high']['observed_rate'] ?? 0,
            $levels['critical']['observed_rate'] ?? 0,
        );
        $this->assertGreaterThan(
            $levels['low']['observed_rate'],
            $highRate,
            'Los equipos marcados de riesgo alto no fallan más que los de riesgo bajo.',
        );
    }

    public function test_training_dataset_only_labels_windows_the_data_can_answer(): void
    {
        $companyId = (int) $this->meinCompany()->id;

        FailureModeCatalog::syncForCompany($companyId);
        $this->ingest('underground-logbook');

        // El reporte de mina cubre 13 días: ninguna fecha de corte deja 14 días de futuro
        // observable, así que el dataset tiene que salir vacío en lugar de inventar ceros.
        $dataset = app(PredictiveMaintenanceService::class)->trainingDataset($companyId, 14, 3);

        $this->assertSame(0, $dataset['total']);
        $this->assertStringContainsString('futuro observable', implode(' ', $dataset['notes']));
    }
}
