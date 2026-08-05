<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\OemEquipmentModel;
use App\Models\OemMaintenancePlan;
use App\Enums\PredictiveAlgorithmKind;
use App\Services\Predictive\ClientDemandPredictionService;
use App\Services\Predictive\PredictiveAlgorithmVersionService;
use App\Services\Predictive\PredictiveMaintenanceService;
use App\Services\Predictive\ServiceDemandEngine;
use App\Support\CurrentCompany;
use App\Support\Predictive\EquipmentClass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API de mantenimiento predictivo.
 *
 * Mismo servicio que consumen las tools del asistente, para que el tablero y la conversación
 * nunca discrepen en el número que reportan.
 */
class PredictiveMaintenanceController extends Controller
{
    public function __construct(
        private readonly PredictiveMaintenanceService $service,
        private readonly ClientDemandPredictionService $demand,
        private readonly ServiceDemandEngine $demandEngine,
        private readonly PredictiveAlgorithmVersionService $algorithms,
    ) {}

    public function predictions(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'asset_ids' => ['nullable', 'array', 'max:200'],
            'asset_ids.*' => ['integer'],
            'tags' => ['nullable', 'array', 'max:200'],
            'tags.*' => ['string', 'max:64'],
            'equipment_class' => ['nullable', 'string', 'max:64'],
            'site_id' => ['nullable', 'integer'],
            'failure_mode' => ['nullable', 'string', 'max:96'],
            'horizon_days' => ['nullable', 'integer', 'in:7,14,30'],
            'min_probability' => ['nullable', 'numeric', 'between:0,1'],
            'limit' => ['nullable', 'integer', 'between:1,200'],
            'as_of' => ['nullable', 'date'],
            'persist' => ['nullable'],
        ]);

        if (array_key_exists('persist', $filters)) {
            $filters['persist'] = filter_var($filters['persist'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        return response()->json([
            'data' => $this->service->predict($this->companyId(), $filters),
        ]);
    }

    public function clientDemand(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'service_category' => ['nullable', 'string', 'in:manufacturing,installation,fabrication,supply,demand'],
            'service_line' => ['nullable', 'string', 'in:fabrication,supply,demand,manufacturing,installation'],
            'client_id' => ['nullable', 'integer'],
            'horizon_days' => ['nullable', 'integer', 'between:7,90'],
            'limit' => ['nullable', 'integer', 'between:1,100'],
            'as_of' => ['nullable', 'date'],
        ]);

        $category = $filters['service_category'] ?? $filters['service_line'] ?? null;
        $filters['calibration'] = $this->algorithms->publishedCalibration(PredictiveAlgorithmKind::Manufacturing);

        if (in_array($category, ['manufacturing', 'fabrication'], true)) {
            return response()->json([
                'data' => $this->algorithms->attachPublishedModel(
                    $this->demandEngine->predictManufacturing($this->companyId(), $filters),
                    PredictiveAlgorithmKind::Manufacturing,
                ),
            ]);
        }

        if ($category === null || $category === '' || $category === 'demand') {
            $manufacturing = $this->demandEngine->predictManufacturing($this->companyId(), $filters);
            $installation = $this->demand->predict($this->companyId(), array_merge($filters, [
                'service_category' => 'installation',
            ]));
            $merged = array_merge($manufacturing['predictions'] ?? [], $installation['predictions'] ?? []);
            usort($merged, fn (array $a, array $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));
            $limit = max(1, min(100, (int) ($filters['limit'] ?? 20)));

            return response()->json([
                'data' => $this->algorithms->attachPublishedModel([
                    'as_of' => $manufacturing['as_of'] ?? $installation['as_of'] ?? null,
                    'horizon_days' => $filters['horizon_days'] ?? 30,
                    'kind' => 'client_demand_merged_v1',
                    'evaluated' => (int) ($manufacturing['evaluated'] ?? 0) + (int) ($installation['evaluated'] ?? 0),
                    'returned' => min($limit, count($merged)),
                    'predictions' => array_slice($merged, 0, $limit),
                    'notes' => array_values(array_filter(array_merge(
                        $manufacturing['notes'] ?? [],
                        $installation['notes'] ?? [],
                    ))),
                ], PredictiveAlgorithmKind::Manufacturing),
            ]);
        }

        return response()->json([
            'data' => $this->algorithms->attachPublishedModel(
                $this->demand->predict($this->companyId(), $filters),
                PredictiveAlgorithmKind::Manufacturing,
            ),
        ]);
    }

    public function inventoryDemand(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'client_id' => ['nullable', 'integer'],
            'horizon_days' => ['nullable', 'integer', 'between:7,90'],
            'limit' => ['nullable', 'integer', 'between:1,100'],
            'as_of' => ['nullable', 'date'],
        ]);

        $filters['calibration'] = $this->algorithms->publishedCalibration(PredictiveAlgorithmKind::Inventory);

        return response()->json([
            'data' => $this->algorithms->attachPublishedModel(
                $this->demandEngine->predictInventory($this->companyId(), $filters),
                PredictiveAlgorithmKind::Inventory,
            ),
        ]);
    }

    public function health(Asset $asset, Request $request): JsonResponse
    {
        $request->validate(['as_of' => ['nullable', 'date']]);

        return response()->json([
            'data' => $this->service->health($this->companyId(), $asset, $request->query('as_of')),
        ]);
    }

    public function failureModes(Request $request): JsonResponse
    {
        $request->validate([
            'equipment_class' => ['nullable', 'string', 'max:64'],
            'system' => ['nullable', 'string', 'max:64'],
        ]);

        return response()->json([
            'data' => $this->service->failureModes(
                $this->companyId(),
                $request->query('equipment_class'),
                $request->query('system'),
            ),
        ]);
    }

    public function accuracy(): JsonResponse
    {
        return response()->json(['data' => $this->service->accuracyReport($this->companyId())]);
    }

    /** Cierra las ventanas ya vencidas contra lo que realmente pasó. */
    public function evaluate(Request $request): JsonResponse
    {
        $request->validate(['as_of' => ['nullable', 'date']]);

        return response()->json([
            'data' => $this->service->evaluateOutcomes($this->companyId(), $request->input('as_of')),
        ]);
    }

    /** Catálogo global de modelos OEM (Epiroc, Sandvik, Metso). */
    public function oemModels(Request $request): JsonResponse
    {
        $request->validate([
            'manufacturer' => ['nullable', 'string', 'max:64'],
            'equipment_class' => ['nullable', 'string', 'max:64'],
            'q' => ['nullable', 'string', 'max:96'],
        ]);

        $query = OemEquipmentModel::query()->orderBy('manufacturer')->orderBy('family')->orderBy('model');

        if ($manufacturer = $request->query('manufacturer')) {
            $query->where('manufacturer', $manufacturer);
        }

        if ($class = $request->query('equipment_class')) {
            $canonical = EquipmentClass::canonical($class) ?? $class;
            $query->where('equipment_class', $canonical);
        }

        if ($q = trim((string) $request->query('q'))) {
            $like = '%'.mb_strtolower($q).'%';
            $query->where(function ($inner) use ($like): void {
                $inner->whereRaw('LOWER(model) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(family) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(description, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(manufacturer) LIKE ?', [$like]);
            });
        }

        return response()->json([
            'data' => $query->get()->map(fn (OemEquipmentModel $m) => [
                'id' => $m->id,
                'manufacturer' => $m->manufacturer,
                'family' => $m->family,
                'model' => $m->model,
                'equipment_class' => $m->equipment_class,
                'application' => $m->application,
                'description' => $m->description,
                'specifications' => $m->specifications,
                'source_url' => $m->source_url,
                'verified' => (bool) $m->verified,
            ]),
        ]);
    }

    /** Planes de mantenimiento OEM por fabricante/clase, con tareas por intervalo. */
    public function oemPlans(Request $request): JsonResponse
    {
        $request->validate([
            'manufacturer' => ['nullable', 'string', 'max:64'],
            'equipment_class' => ['nullable', 'string', 'max:64'],
        ]);

        $query = OemMaintenancePlan::query()
            ->with(['items' => fn ($q) => $q->orderBy('interval_hours')->orderBy('id')])
            ->orderBy('manufacturer')
            ->orderBy('equipment_class');

        if ($manufacturer = $request->query('manufacturer')) {
            $query->where('manufacturer', $manufacturer);
        }

        if ($class = $request->query('equipment_class')) {
            $canonical = EquipmentClass::canonical($class) ?? $class;
            $query->where('equipment_class', $canonical);
        }

        return response()->json([
            'data' => $query->get()->map(fn (OemMaintenancePlan $plan) => [
                'id' => $plan->id,
                'manufacturer' => $plan->manufacturer,
                'equipment_class' => $plan->equipment_class,
                'name' => $plan->name,
                'notes' => $plan->notes,
                'source_url' => $plan->source_url,
                'verified' => (bool) $plan->verified,
                'intervals' => $plan->intervals(),
                'items' => $plan->items->map(fn ($item) => [
                    'id' => $item->id,
                    'interval_hours' => (int) $item->interval_hours,
                    'task' => $item->task,
                    'system' => $item->system,
                ]),
            ]),
        ]);
    }

    private function companyId(): int
    {
        return (int) app(CurrentCompany::class)->id();
    }
}
