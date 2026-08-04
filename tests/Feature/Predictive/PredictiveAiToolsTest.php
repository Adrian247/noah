<?php

namespace Tests\Feature\Predictive;

use App\Models\Asset;
use App\Services\AI\Contracts\AiTool;
use App\Services\AI\Tools\AiToolRegistry;
use App\Services\Predictive\LogbookIngestService;
use App\Services\Predictive\PredictionServiceClient;
use App\Services\Predictive\PredictiveMaintenanceService;
use App\Support\Predictive\FailureModeCatalog;
use App\Support\Predictive\OemCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\AttachesPredictiveRoutines;
use Tests\Support\UsesMeinCompany;
use Tests\TestCase;

class PredictiveAiToolsTest extends TestCase
{
    use AttachesPredictiveRoutines;
    use RefreshDatabase;
    use UsesMeinCompany;

    private int $companyId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->companyId = (int) $this->meinCompany()->id;
        OemCatalog::sync();
        FailureModeCatalog::syncForCompany($this->companyId);

        $fixture = json_decode(
            (string) file_get_contents(base_path('tests/Fixtures/Predictive/underground-logbook.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        app(LogbookIngestService::class)->ingest($this->companyId, $fixture);
        $this->attachValidatedRoutinesToAssets($this->companyId);
    }

    private function tool(string $name): AiTool
    {
        return app(AiToolRegistry::class)->get($name);
    }

    public function test_prediction_tool_answers_with_evidence_and_citable_assets(): void
    {
        $result = $this->tool('predict_equipment_failures')->execute(
            ['equipment_class' => 'scooptram', 'horizon_days' => 14, 'limit' => 3, 'as_of' => '2020-09-09'],
            $this->companyId,
        );

        $this->assertGreaterThan(0, $result['data']['evaluated_assets']);
        $this->assertNotEmpty($result['data']['predictions']);
        $this->assertNotEmpty($result['data']['scale']);

        foreach ($result['sources'] as $source) {
            $this->assertSame('asset', $source['type']);
            $this->assertStringContainsString('riesgo', $source['label']);
        }

        $first = $result['data']['predictions'][0];
        $this->assertSame('SCOOPTRAM', $first['equipment_class']);
        $this->assertArrayHasKey('why', $first);
        $this->assertLessThanOrEqual(3, count($first['likely_failure_modes']));
    }

    public function test_health_tool_finds_the_asset_by_tag_and_reports_when_it_cannot(): void
    {
        $found = $this->tool('get_equipment_health')->execute(
            ['tag' => 'SS-305', 'as_of' => '2020-09-09'],
            $this->companyId,
        );

        $this->assertSame('SS-305', $found['data']['asset']['tag']);
        $this->assertCount(1, $found['sources']);

        $missing = $this->tool('get_equipment_health')->execute(['tag' => 'NO-EXISTE'], $this->companyId);

        $this->assertArrayHasKey('error', $missing['data']);
        $this->assertSame([], $missing['sources']);
    }

    public function test_failure_mode_tool_narrows_the_catalog_to_the_class(): void
    {
        $all = $this->tool('list_failure_modes')->execute([], $this->companyId);
        $jumbo = $this->tool('list_failure_modes')->execute(['equipment_class' => 'JB'], $this->companyId);

        $this->assertGreaterThan(0, $jumbo['data']['total']);
        $this->assertLessThan($all['data']['total'], $jumbo['data']['total']);
    }

    public function test_ml_service_refines_the_probability_and_keeps_the_explanation(): void
    {
        config(['phoenix.predictive.ml.enabled' => true, 'phoenix.predictive.ml.url' => 'http://ml.test']);

        Http::fake(['ml.test/*' => Http::response([
            'model_version' => 'gbdt-test',
            'predictions' => [['asset_id' => $this->scooptramId(), 'probability' => 0.31]],
        ])]);

        $result = app(PredictiveMaintenanceService::class)->predict($this->companyId, [
            'tags' => ['SS-305'],
            'as_of' => '2020-09-09',
            'persist' => false,
        ]);

        $prediction = $result['predictions'][0];

        $this->assertTrue($result['model']['ml_service_used']);
        $this->assertSame('ml', $result['model']['kind']);
        $this->assertSame('gbdt-test', $result['model']['ml_model_version']);
        $this->assertNotEmpty($result['model']['algorithm_semver'] ?? $result['model']['version']);
        $this->assertSame(0.31, $prediction['probability']);
        $this->assertArrayHasKey('heuristic_probability', $prediction);
        // La atribución por modo de falla la sigue aportando el motor determinístico.
        $this->assertNotEmpty($prediction['failure_modes']);
    }

    public function test_prediction_falls_back_to_the_engine_when_the_ml_service_fails(): void
    {
        config(['phoenix.predictive.ml.enabled' => true, 'phoenix.predictive.ml.url' => 'http://ml.test']);

        Http::fake(['ml.test/*' => Http::response(['detail' => 'sin modelo'], 503)]);

        $result = app(PredictiveMaintenanceService::class)->predict($this->companyId, [
            'tags' => ['SS-305'],
            'as_of' => '2020-09-09',
            'persist' => false,
        ]);

        $this->assertFalse($result['model']['ml_service_used']);
        $this->assertSame('hazard-v2', $result['model']['kind']);
        $this->assertSame('routines', $result['model']['feature_source']);
        $this->assertNotEmpty($result['predictions']);
    }

    public function test_ml_client_reports_unavailable_service_without_throwing(): void
    {
        config(['phoenix.predictive.ml.enabled' => true, 'phoenix.predictive.ml.url' => 'http://ml.test']);
        Http::fake(['ml.test/*' => fn () => throw new \RuntimeException('conexión rechazada')]);

        $this->assertNull(app(PredictionServiceClient::class)->health());
        $this->assertNull(app(PredictionServiceClient::class)->score(
            [['asset_id' => 1, 'probability' => 0.5]],
            [1 => []],
            14,
        ));
    }

    private function scooptramId(): int
    {
        return (int) Asset::withoutGlobalScope('company')
            ->where('company_id', $this->companyId)
            ->where('tag', 'SS-305')
            ->value('id');
    }
}
