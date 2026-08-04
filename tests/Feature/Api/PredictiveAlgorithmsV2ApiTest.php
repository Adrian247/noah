<?php

namespace Tests\Feature\Api;

use App\Enums\PredictiveAlgorithmKind;
use App\Models\Company;
use App\Models\PredictiveAlgorithmVersion;
use App\Models\PredictiveTrainingDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\Support\UsesMeinCompany;
use Tests\TestCase;

class PredictiveAlgorithmsV2ApiTest extends TestCase
{
    use RefreshDatabase;
    use UsesMeinCompany;

    private User $root;

    private int $companyId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->root = User::query()->where('email', 'admin@pyro-systems.com')->firstOrFail();
        $this->companyId = (int) $this->meinCompany()->id;
        Sanctum::actingAs($this->root);
    }

    public function test_training_document_schemas_and_upload_for_each_kind(): void
    {
        $schemas = $this->getJson('/api/v1/platform/predictive/training-documents/schemas')
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'guide' => ['title', 'summary', 'steps', 'documents', 'regression'],
            ])
            ->json('data');

        $kinds = collect($schemas)->pluck('kind')->all();
        $this->assertContains(PredictiveAlgorithmKind::Maintenance->value, $kinds);
        $this->assertContains(PredictiveAlgorithmKind::Manufacturing->value, $kinds);
        $this->assertContains(PredictiveAlgorithmKind::Inventory->value, $kinds);

        foreach ([PredictiveAlgorithmKind::Maintenance, PredictiveAlgorithmKind::Manufacturing, PredictiveAlgorithmKind::Inventory] as $kind) {
            $this->get("/api/v1/platform/predictive/training-documents/templates/{$kind->value}?format=json")
                ->assertOk()
                ->assertHeader('content-disposition');
            $this->get("/api/v1/platform/predictive/training-documents/templates/{$kind->value}?format=csv")
                ->assertOk();
        }

        $payload = json_encode([
            'contract' => 'phoenix.predictive.training/v1',
            'kind' => PredictiveAlgorithmKind::Manufacturing->value,
            'records' => [
                [
                    'client_code' => 'CLI-001',
                    'service_type' => 'Fabricación estructural',
                    'occurred_at' => '2026-01-15',
                    'quantity' => 2,
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $file = UploadedFile::fake()->createWithContent('mfg-train.json', $payload);

        $this->post('/api/v1/platform/predictive/training-documents', [
            'kind' => PredictiveAlgorithmKind::Manufacturing->value,
            'name' => 'Corpus manufactura demo',
            'file' => $file,
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.status', PredictiveTrainingDocument::STATUS_READY)
            ->assertJsonPath('data.record_count', 1);
    }

    public function test_root_can_train_with_regression_per_kind(): void
    {
        Company::query()->where('id', $this->companyId)->update([
            'allow_predictive_training_collection' => true,
        ]);

        $corpus = $this->getJson('/api/v1/platform/predictive/algorithms/corpus')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'note',
                    'overall_volume_level',
                    'overall_volume_label',
                    'opt_in' => ['companies_count', 'reminder'],
                    'kinds',
                ],
            ])
            ->json('data');

        $this->assertNotEmpty($corpus['note']);
        $this->assertGreaterThanOrEqual(1, $corpus['opt_in']['companies_count']);

        $response = $this->postJson('/api/v1/platform/predictive/algorithms/train', [
            'kind' => PredictiveAlgorithmKind::Maintenance->value,
            'notes' => 'Test train maintenance',
            'run_regression' => true,
            'document_ids' => [],
        ])->assertCreated();

        $this->assertSame(PredictiveAlgorithmVersion::STATUS_DRAFT, $response->json('data.status'));
        $this->assertSame(PredictiveAlgorithmKind::Maintenance->value, $response->json('data.kind'));
        $this->assertNotNull($response->json('data.calibration'));
        $this->assertIsArray($response->json('data.regression_report'));

        $id = (int) $response->json('data.id');
        $this->postJson("/api/v1/platform/predictive/algorithms/{$id}/regression")
            ->assertOk()
            ->assertJsonStructure(['data' => ['rows', 'kind']]);
    }

    public function test_tenant_inventory_demand_endpoint_is_available(): void
    {
        $this->withHeader('X-Company-Id', (string) $this->companyId)
            ->getJson('/api/v1/predictive/inventory-demand?horizon_days=30&limit=10')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'as_of',
                    'horizon_days',
                    'kind',
                    'predictions',
                ],
            ]);
    }

    public function test_non_platform_admin_cannot_train(): void
    {
        $tenantAdmin = $this->meinUser('admin@sandbox-demo.com');
        Sanctum::actingAs($tenantAdmin);

        $this->postJson('/api/v1/platform/predictive/algorithms/train', [
            'kind' => PredictiveAlgorithmKind::Inventory->value,
        ])->assertForbidden();
    }

    public function test_root_can_update_algorithm_notes(): void
    {
        Company::query()->where('id', $this->companyId)->update([
            'allow_predictive_training_collection' => true,
        ]);

        $id = (int) $this->postJson('/api/v1/platform/predictive/algorithms/train', [
            'kind' => PredictiveAlgorithmKind::Maintenance->value,
            'notes' => null,
            'run_regression' => false,
            'document_ids' => [],
        ])->assertCreated()->json('data.id');

        $this->patchJson("/api/v1/platform/predictive/algorithms/{$id}", [
            'notes' => 'Corpus SMA + Planta 4400 (F=paro; comments=falla)',
        ])
            ->assertOk()
            ->assertJsonPath('data.notes', 'Corpus SMA + Planta 4400 (F=paro; comments=falla)');

        $this->assertSame(
            'Corpus SMA + Planta 4400 (F=paro; comments=falla)',
            PredictiveAlgorithmVersion::query()->findOrFail($id)->notes,
        );
    }
}
