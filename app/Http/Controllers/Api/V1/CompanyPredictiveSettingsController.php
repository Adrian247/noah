<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MembershipRole;
use App\Http\Controllers\Controller;
use App\Models\PredictiveAlgorithmVersion;
use App\Services\Audit\AuditLogger;
use App\Services\Predictive\PredictiveAlgorithmVersionService;
use App\Support\CurrentCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyPredictiveSettingsController extends Controller
{
    public function __construct(
        private readonly PredictiveAlgorithmVersionService $versions,
        private readonly AuditLogger $audit,
    ) {}

    public function show(): JsonResponse
    {
        $company = app(CurrentCompany::class)->company;
        abort_if($company === null, 400, 'Company context required.');

        $selected = $company->predictiveAlgorithmVersion;

        return response()->json([
            'data' => [
                'allow_predictive_training_collection' => (bool) $company->allow_predictive_training_collection,
                'predictive_algorithm_version_id' => $company->predictive_algorithm_version_id,
                'selected_version' => $selected ? [
                    'id' => $selected->id,
                    'semver' => $selected->semver,
                    'kind' => $selected->kind,
                ] : null,
                'available_versions' => $this->versions->publishedForCompanies(),
                'legal_notice' => $this->legalNotice(),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $this->authorizeAdministrator($request);

        $data = $request->validate([
            'allow_predictive_training_collection' => ['required', 'boolean'],
            'predictive_algorithm_version_id' => ['nullable', 'integer', 'exists:predictive_algorithm_versions,id'],
        ]);

        $company = app(CurrentCompany::class)->company;
        abort_if($company === null, 400, 'Company context required.');

        if (! empty($data['predictive_algorithm_version_id'])) {
            $version = PredictiveAlgorithmVersion::query()->findOrFail($data['predictive_algorithm_version_id']);
            if (! $version->isPublished()) {
                abort(422, 'Solo se pueden seleccionar versiones publicadas del algoritmo.');
            }
        }

        $company->update([
            'allow_predictive_training_collection' => $data['allow_predictive_training_collection'],
            'predictive_algorithm_version_id' => $data['predictive_algorithm_version_id'] ?? null,
        ]);

        $this->audit->fromRequest(
            $request,
            'predictive.company_settings_updated',
            'company',
            $company->id,
            [
                'allow_predictive_training_collection' => $data['allow_predictive_training_collection'],
                'predictive_algorithm_version_id' => $data['predictive_algorithm_version_id'] ?? null,
            ],
        );

        return $this->show();
    }

    private function legalNotice(): string
    {
        return 'Permitir a Phoenix recopilar información de rutinas para entrenamiento. '
            .'Al activar esta opción, Phoenix podrá usar el historial de rutinas aplicadas '
            .'(fechas, tipos, resultados de validación, consumos y comentarios técnicos) '
            .'únicamente para entrenar y mejorar el algoritmo predictivo dentro de la plataforma. '
            .'Esta información no se vende, no se comparte con terceros ni se expone fuera de '
            .'la aplicación Phoenix, salvo obligación legal. El tratamiento se limita a fines '
            .'de confiabilidad operativa y mejora del modelo; puedes desactivar la recolección '
            .'en cualquier momento. Los datos de clientes sin este permiso no entran al corpus '
            .'de entrenamiento multi-empresa.';
    }

    private function authorizeAdministrator(Request $request): void
    {
        $membership = $request->attributes->get('membership');
        $role = $membership->role;
        $roleValue = $role instanceof MembershipRole ? $role->value : (string) $role;

        if ($roleValue !== MembershipRole::Administrator->value) {
            abort(403, 'Administrator role required.');
        }
    }
}
