<?php

namespace Tests\Feature\Api;

use App\Models\Asset;
use App\Models\User;
use App\Services\Predictive\LogbookIngestService;
use App\Support\Predictive\FailureModeCatalog;
use App\Support\Predictive\OemCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Support\AttachesPredictiveRoutines;
use Tests\Support\UsesMeinCompany;
use Tests\TestCase;

class PredictiveMaintenanceApiTest extends TestCase
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

        Sanctum::actingAs(User::query()->where('email', 'admin@pyro-systems.com')->firstOrFail());
    }

    private function forCompany(string $uri): TestResponse
    {
        return $this->withHeader('X-Company-Id', (string) $this->companyId)->getJson($uri);
    }

    public function test_predictions_endpoint_ranks_the_fleet_with_explainable_drivers(): void
    {
        $response = $this->forCompany('/api/v1/predictive/predictions?horizon_days=14&limit=5&as_of=2020-09-09')
            ->assertOk()
            ->assertJsonPath('data.horizon_days', 14)
            ->assertJsonStructure([
                'data' => [
                    'as_of',
                    'evaluated_assets',
                    'risk_summary',
                    'model' => ['kind', 'version', 'ml_service_enabled', 'ml_service_used'],
                    'predictions' => [['asset_id', 'tag', 'probability', 'expected_failures', 'risk_level', 'confidence', 'drivers', 'failure_modes']],
                ],
            ]);

        $predictions = $response->json('data.predictions');
        $this->assertLessThanOrEqual(5, count($predictions));

        // El orden es por fallas esperadas, no por probabilidad, porque esa se satura.
        $expected = array_column($predictions, 'expected_failures');
        $sorted = $expected;
        rsort($sorted);
        $this->assertSame($sorted, $expected);

        foreach ($predictions as $prediction) {
            $this->assertGreaterThan(0, $prediction['probability']);
            $this->assertLessThanOrEqual(1, $prediction['probability']);
            $this->assertContains($prediction['risk_level'], ['low', 'medium', 'high', 'critical']);
        }
    }

    public function test_predictions_can_be_narrowed_by_class_using_a_tag_prefix(): void
    {
        $byName = $this->forCompany('/api/v1/predictive/predictions?equipment_class=SCOOPTRAM&as_of=2020-09-09')
            ->assertOk()
            ->json('data.evaluated_assets');

        $byPrefix = $this->forCompany('/api/v1/predictive/predictions?equipment_class=SS&as_of=2020-09-09')
            ->assertOk()
            ->json('data.evaluated_assets');

        $this->assertGreaterThan(0, $byName);
        $this->assertSame($byName, $byPrefix);
    }

    public function test_predictions_filtered_by_failure_mode_report_that_mode(): void
    {
        $predictions = $this->forCompany('/api/v1/predictive/predictions?failure_mode=FUGA_HIDRAULICA&as_of=2020-09-09')
            ->assertOk()
            ->json('data.predictions');

        $this->assertNotEmpty($predictions);
        foreach ($predictions as $prediction) {
            $this->assertSame('FUGA_HIDRAULICA', $prediction['matched_failure_mode']['code']);
        }
    }

    public function test_health_endpoint_returns_the_evidence_behind_the_prediction(): void
    {
        $asset = Asset::withoutGlobalScope('company')
            ->where('company_id', $this->companyId)
            ->where('tag', 'SS-305')
            ->firstOrFail();

        $this->forCompany("/api/v1/predictive/assets/{$asset->id}/health?as_of=2020-09-09")
            ->assertOk()
            ->assertJsonPath('data.asset.tag', 'SS-305')
            ->assertJsonPath('data.asset.equipment_class', 'SCOOPTRAM')
            ->assertJsonStructure([
                'data' => [
                    'reliability' => ['worked_hours_total', 'availability_7d', 'mtbf_hours', 'mttr_hours'],
                    'prediction' => ['probability', 'expected_failures', 'risk_level', 'drivers'],
                    'recent_failures',
                    'pending_work_orders',
                ],
            ]);
    }

    public function test_failure_modes_endpoint_filters_by_class_and_system(): void
    {
        $all = $this->forCompany('/api/v1/predictive/failure-modes')->assertOk()->json('data');
        $hydraulic = $this->forCompany('/api/v1/predictive/failure-modes?system=hidraulico')->assertOk()->json('data');

        $this->assertNotEmpty($all);
        $this->assertNotEmpty($hydraulic);
        $this->assertLessThan(count($all), count($hydraulic));
        foreach ($hydraulic as $mode) {
            $this->assertStringContainsString('hidraulico', $mode['system']);
        }
    }

    public function test_accuracy_endpoint_reports_no_evaluation_until_windows_close(): void
    {
        $this->forCompany('/api/v1/predictive/accuracy')
            ->assertOk()
            ->assertJsonPath('data.evaluated', 0);
    }

    public function test_predictive_endpoints_require_company_scope(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'admin@pyro-systems.com')->firstOrFail());

        $this->getJson('/api/v1/predictive/predictions')->assertStatus(400);
    }

    public function test_oem_catalog_endpoints_list_verified_models_and_plans(): void
    {
        $models = $this->forCompany('/api/v1/predictive/oem-models?manufacturer=Epiroc')
            ->assertOk()
            ->json('data');

        $this->assertNotEmpty($models);
        foreach ($models as $model) {
            $this->assertSame('Epiroc', $model['manufacturer']);
            $this->assertArrayHasKey('verified', $model);
        }

        $sg = $this->forCompany('/api/v1/predictive/oem-models?q=SG')
            ->assertOk()
            ->json('data');
        $this->assertTrue(collect($sg)->contains(fn (array $m) => str_contains($m['model'], 'SG')));

        $plans = $this->forCompany('/api/v1/predictive/oem-plans?manufacturer=Sandvik')
            ->assertOk()
            ->json('data');

        $this->assertNotEmpty($plans);
        $this->assertTrue(collect($plans)->contains(fn (array $p) => $p['verified'] === true));
        $this->assertNotEmpty($plans[0]['items']);
        $this->assertArrayHasKey('interval_hours', $plans[0]['items'][0]);
    }
}
