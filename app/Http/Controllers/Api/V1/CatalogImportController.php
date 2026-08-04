<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CatalogItem;
use App\Services\Catalog\CatalogArticleImportService;
use App\Support\CurrentCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogImportController extends Controller
{
    public function __construct(
        private readonly CatalogArticleImportService $importService,
    ) {}

    public function systemCatalog(): JsonResponse
    {
        $items = CatalogItem::withoutGlobalScope('company')
            ->where('is_system_template', true)
            ->with(['equipmentType:id,code,name'])
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $items]);
    }

    public function import(Request $request): JsonResponse
    {
        $data = $request->validate([
            'source_catalog_item_id' => ['required', 'integer', 'exists:catalog_items,id'],
            'overwrite' => ['sometimes', 'boolean'],
            'force_new' => ['sometimes', 'boolean'],
        ]);

        $source = CatalogItem::withoutGlobalScope('company')->findOrFail($data['source_catalog_item_id']);
        $companyId = app(CurrentCompany::class)->id();
        if ($companyId === null) {
            abort(400, 'Company context required.');
        }

        $result = $this->importService->importFromSource(
            $source,
            $companyId,
            $request->user(),
            (bool) ($data['overwrite'] ?? false),
            (bool) ($data['force_new'] ?? false),
        );

        $warnings = [];
        if ($result['previous_import'] && $result['action'] === 'skipped') {
            $warnings[] = 'Este artículo ya fue importado anteriormente.';
        }
        if ($result['inconsistent_history']) {
            $warnings[] = 'Se detectaron múltiples importaciones previas; revise posibles inconsistencias.';
        }

        return response()->json([
            'data' => $result['item'],
            'meta' => [
                'action' => $result['action'],
                'generation' => $result['generation'],
                'warnings' => $warnings,
            ],
        ], $result['action'] === 'clone' ? 201 : 200);
    }
}
