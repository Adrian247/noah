<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MembershipRole;
use App\Events\FormVersionPublished;
use App\Http\Controllers\Controller;
use App\Models\FormDefinition;
use App\Models\FormVersion;
use App\Services\Audit\AuditLogger;
use App\Services\Forms\FormDesignSettings;
use App\Services\Forms\FormSchemaValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FormDefinitionController extends Controller
{
    public function index(): JsonResponse
    {
        $forms = FormDefinition::query()
            ->with(['versions' => fn ($q) => $q->orderByDesc('version')])
            ->orderBy('name')
            ->get()
            ->map(function (FormDefinition $form) {
                $latest = $form->versions->first();
                $published = $form->versions->firstWhere('status', 'published');
                $draft = $form->versions->firstWhere('status', 'draft');

                return [
                    'id' => $form->id,
                    'name' => $form->name,
                    'slug' => $form->slug,
                    'latest_version' => $latest ? [
                        'id' => $latest->id,
                        'version' => $latest->version,
                        'status' => $latest->status,
                        'published_at' => $latest->published_at,
                    ] : null,
                    'published_version' => $published ? [
                        'id' => $published->id,
                        'version' => $published->version,
                        'status' => $published->status,
                        'published_at' => $published->published_at,
                    ] : null,
                    'draft_version' => $draft ? [
                        'id' => $draft->id,
                        'version' => $draft->version,
                        'status' => $draft->status,
                    ] : null,
                ];
            });

        return response()->json(['data' => $forms]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeDesigner($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:128'],
        ]);

        $slug = $data['slug'] ?? Str::slug($data['name']);

        $form = FormDefinition::query()->create([
            'name' => $data['name'],
            'slug' => $slug,
        ]);

        FormVersion::query()->create([
            'form_definition_id' => $form->id,
            'version' => 1,
            'status' => 'draft',
            'schema' => ['sections' => []],
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['data' => $form->load('versions')], 201);
    }

    public function show(FormDefinition $form, FormDesignSettings $designSettings): JsonResponse
    {
        return response()->json([
            'data' => $form->load(['versions' => fn ($q) => $q->orderByDesc('version')]),
            'form_design' => [
                'settings' => $designSettings->forCurrentCompany(),
                'option_catalogs' => $designSettings->optionCatalogsForCurrentCompany(),
            ],
        ]);
    }

    public function updateSchema(Request $request, FormDefinition $form, AuditLogger $audit, FormSchemaValidator $schemaValidator): JsonResponse
    {
        $this->authorizeDesigner($request);

        $data = $request->validate([
            'schema' => ['required', 'array'],
            'schema.sections' => ['required', 'array'],
        ]);

        $schemaValidator->validate($data['schema']);

        $draft = $form->versions()->where('status', 'draft')->orderByDesc('version')->first();

        if ($draft === null) {
            $next = (int) $form->versions()->max('version') + 1;
            $draft = FormVersion::query()->create([
                'form_definition_id' => $form->id,
                'version' => $next,
                'status' => 'draft',
                'schema' => $data['schema'],
                'created_by' => $request->user()->id,
            ]);
        } else {
            $draft->update(['schema' => $data['schema']]);
        }

        $audit->fromRequest($request, 'form.schema_updated', FormDefinition::class, $form->id, [
            'version' => $draft->version,
        ]);

        return response()->json(['data' => $draft->fresh()]);
    }

    public function publish(Request $request, FormDefinition $form, AuditLogger $audit): JsonResponse
    {
        $this->authorizeDesigner($request);

        $draft = $form->versions()->where('status', 'draft')->orderByDesc('version')->first();
        if ($draft === null) {
            return response()->json(['message' => 'No draft version to publish.'], 422);
        }

        $draft->update([
            'status' => 'published',
            'published_at' => now(),
        ]);

        FormVersionPublished::dispatch($draft->fresh());

        $audit->fromRequest($request, 'form.version_published', FormDefinition::class, $form->id, [
            'version' => $draft->version,
        ]);

        $nextVersion = $draft->version + 1;
        $newDraft = FormVersion::query()->create([
            'form_definition_id' => $form->id,
            'version' => $nextVersion,
            'status' => 'draft',
            'schema' => $draft->schema,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'data' => [
                'published' => $draft->fresh(),
                'draft' => $newDraft,
            ],
        ]);
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
