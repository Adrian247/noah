<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MembershipRole;
use App\Http\Controllers\Controller;
use App\Models\FormOptionCatalog;
use App\Support\CurrentCompany;
use App\Services\Audit\AuditLogger;
use App\Services\Forms\FormDesignSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FormOptionCatalogController extends Controller
{
    public function index(FormDesignSettings $settings): JsonResponse
    {
        return response()->json([
            'data' => $settings->optionCatalogsForCurrentCompany(),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): JsonResponse
    {
        $this->authorizeDesigner($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:128'],
            'options' => ['required', 'array', 'min:1'],
            'options.*.value' => ['required', 'string', 'max:255'],
            'options.*.label' => ['required', 'string', 'max:255'],
            'options.*.description' => ['nullable', 'string', 'max:500'],
        ]);

        $companyId = app(CurrentCompany::class)->id();
        if ($companyId === null) {
            abort(400, 'Company context required.');
        }

        $slug = $data['slug'] ?? Str::slug($data['name']);

        $catalog = FormOptionCatalog::query()->create([
            'company_id' => $companyId,
            'name' => $data['name'],
            'slug' => $slug,
            'options' => $data['options'],
        ]);

        $audit->fromRequest($request, 'form.option_catalog_created', FormOptionCatalog::class, $catalog->id);

        return response()->json(['data' => $catalog], 201);
    }

    public function update(Request $request, FormOptionCatalog $optionCatalog, AuditLogger $audit): JsonResponse
    {
        $this->authorizeDesigner($request);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'options' => ['sometimes', 'array', 'min:1'],
            'options.*.value' => ['required_with:options', 'string', 'max:255'],
            'options.*.label' => ['required_with:options', 'string', 'max:255'],
            'options.*.description' => ['nullable', 'string', 'max:500'],
        ]);

        $optionCatalog->update($data);
        $audit->fromRequest($request, 'form.option_catalog_updated', FormOptionCatalog::class, $optionCatalog->id);

        return response()->json(['data' => $optionCatalog->fresh()]);
    }

    public function destroy(Request $request, FormOptionCatalog $optionCatalog, AuditLogger $audit): JsonResponse
    {
        $this->authorizeDesigner($request);
        $id = $optionCatalog->id;
        $optionCatalog->delete();
        $audit->fromRequest($request, 'form.option_catalog_deleted', FormOptionCatalog::class, $id);

        return response()->json(null, 204);
    }

    private function authorizeDesigner(Request $request): void
    {
        $membership = $request->attributes->get('membership');
        $role = $membership->role;
        $roleValue = $role instanceof MembershipRole ? $role->value : (string) $role;

        if ($roleValue !== MembershipRole::Administrator->value) {
            abort(403, 'Administrator role required for form design.');
        }
    }
}
