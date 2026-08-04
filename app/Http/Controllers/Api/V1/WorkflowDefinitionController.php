<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\WorkflowTemplate;
use App\Http\Controllers\Controller;
use App\Support\PlatformAdmin;
use App\Models\RoutineType;
use App\Models\WorkflowDefinition;
use App\Services\Audit\AuditLogger;
use App\Services\Workflow\WorkflowBlockCompiler;
use App\Services\Workflow\WorkflowDefinitionFactory;
use App\Services\Workflow\WorkflowDefinitionValidator;
use App\Services\Workflow\WorkflowRuntime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WorkflowDefinitionController extends Controller
{
    public function __construct(
        private readonly WorkflowDefinitionFactory $factory,
    ) {}

    public function templates(): JsonResponse
    {
        return response()->json(['data' => $this->factory->catalog()]);
    }

    public function index(): JsonResponse
    {
        $items = WorkflowDefinition::query()
            ->withCount('routineTypes')
            ->orderBy('name')
            ->orderByDesc('version')
            ->get()
            ->map(fn (WorkflowDefinition $workflow) => [
                'id' => $workflow->id,
                'name' => $workflow->name,
                'slug' => $workflow->slug,
                'version' => $workflow->version,
                'status' => $workflow->status,
                'template' => $workflow->definition['meta']['template'] ?? null,
                'routine_types_count' => $workflow->routine_types_count,
            ]);

        return response()->json(['data' => $items]);
    }

    public function store(Request $request, AuditLogger $audit, WorkflowDefinitionValidator $validator): JsonResponse
    {
        $this->authorizeDesigner($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:128'],
            'template' => ['nullable', 'string', Rule::enum(WorkflowTemplate::class)],
            'options' => ['nullable', 'array'],
            'options.include_billing' => ['sometimes', 'boolean'],
            'options.routine_validated_on_approve' => ['sometimes', 'boolean'],
            'options.dual_review' => ['sometimes', 'boolean'],
            'options.include_email_step' => ['sometimes', 'boolean'],
        ]);

        $template = WorkflowTemplate::tryFrom($data['template'] ?? '')
            ?? WorkflowTemplate::StandardBilling;
        $options = $data['options'] ?? [];
        $definition = $this->factory->build($template, $options);
        $validator->validate($definition);

        $slug = $this->uniqueSlug($data['slug'] ?? Str::slug($data['name']));

        $workflow = WorkflowDefinition::query()->create([
            'name' => $data['name'],
            'slug' => $slug,
            'version' => 1,
            'status' => 'draft',
            'definition' => $definition,
        ]);

        $audit->fromRequest($request, 'workflow.created', WorkflowDefinition::class, $workflow->id, [
            'template' => $template->value,
        ]);

        return response()->json(['data' => $this->workflowPayload($workflow)], 201);
    }

    public function duplicate(
        Request $request,
        WorkflowDefinition $workflowDefinition,
        AuditLogger $audit,
        WorkflowDefinitionValidator $validator,
    ): JsonResponse {
        $this->authorizeDesigner($request);

        $baseName = $workflowDefinition->name.' (copia)';
        $slug = $this->uniqueSlug(Str::slug($workflowDefinition->slug.'-copia'));
        $definition = WorkflowRuntime::withDefaultLayout($workflowDefinition->definition ?? []);
        $validator->validate($definition);

        $copy = WorkflowDefinition::query()->create([
            'name' => $baseName,
            'slug' => $slug,
            'version' => 1,
            'status' => 'draft',
            'definition' => $definition,
        ]);

        $audit->fromRequest($request, 'workflow.duplicated', WorkflowDefinition::class, $copy->id, [
            'source_workflow_definition_id' => $workflowDefinition->id,
        ]);

        return response()->json(['data' => $this->workflowPayload($copy)], 201);
    }

    public function update(
        Request $request,
        WorkflowDefinition $workflowDefinition,
        AuditLogger $audit,
    ): JsonResponse {
        $this->authorizeDesigner($request);

        $companyId = $workflowDefinition->company_id;

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:128',
                Rule::unique('workflow_definitions', 'slug')
                    ->where('company_id', $companyId)
                    ->where('version', $workflowDefinition->version)
                    ->ignore($workflowDefinition->id),
            ],
        ]);

        if ($data !== []) {
            $workflowDefinition->update($data);
            $audit->fromRequest($request, 'workflow.updated', WorkflowDefinition::class, $workflowDefinition->id);
        }

        return response()->json(['data' => $this->workflowPayload($workflowDefinition->fresh())]);
    }

    public function show(WorkflowDefinition $workflowDefinition): JsonResponse
    {
        $compiler = app(WorkflowBlockCompiler::class);
        $raw = $workflowDefinition->definition ?? [];
        $definition = $compiler->ensureEditorDefinition($raw);

        if ($compiler->needsBlockGraphUpgrade($raw)) {
            $workflowDefinition->update(['definition' => $definition]);
        }

        return response()->json([
            'data' => [
                ...$this->workflowPayload($workflowDefinition->fresh()),
                'definition' => WorkflowRuntime::withDefaultLayout($definition),
            ],
        ]);
    }

    public function configure(
        Request $request,
        WorkflowDefinition $workflowDefinition,
        AuditLogger $audit,
        WorkflowDefinitionValidator $validator,
    ): JsonResponse {
        $this->authorizeDesigner($request);
        $this->assertEditable($workflowDefinition);

        $data = $request->validate([
            'options' => ['required', 'array'],
            'options.include_billing' => ['sometimes', 'boolean'],
            'options.routine_validated_on_approve' => ['sometimes', 'boolean'],
            'options.dual_review' => ['sometimes', 'boolean'],
            'options.include_email_step' => ['sometimes', 'boolean'],
        ]);

        $definition = $this->factory->applyOptions($workflowDefinition->definition ?? [], $data['options']);
        $validator->validate($definition);

        $workflowDefinition->update(['definition' => $definition]);
        $audit->fromRequest($request, 'workflow.configured', WorkflowDefinition::class, $workflowDefinition->id);

        return response()->json(['data' => $workflowDefinition->fresh()]);
    }

    public function publish(
        Request $request,
        WorkflowDefinition $workflowDefinition,
        AuditLogger $audit,
        WorkflowDefinitionValidator $validator,
    ): JsonResponse {
        $this->authorizeDesigner($request);

        if ($workflowDefinition->status === 'published') {
            return response()->json(['data' => $this->workflowPayload($workflowDefinition)]);
        }

        $definition = WorkflowRuntime::withDefaultLayout($workflowDefinition->definition ?? []);
        $validator->validate($definition);

        $workflowDefinition->update([
            'status' => 'published',
            'definition' => $definition,
        ]);

        $audit->fromRequest($request, 'workflow.published', WorkflowDefinition::class, $workflowDefinition->id);

        return response()->json(['data' => $this->workflowPayload($workflowDefinition->fresh())]);
    }

    public function updateDefinition(
        Request $request,
        WorkflowDefinition $workflowDefinition,
        AuditLogger $audit,
        WorkflowDefinitionValidator $validator,
    ): JsonResponse {
        $this->authorizeDesigner($request);
        $this->assertEditable($workflowDefinition);

        $data = $request->validate([
            'definition' => ['required', 'array'],
            'definition.initial_step' => ['required', 'string'],
            'definition.steps' => ['required', 'array'],
            'definition.transitions' => ['required', 'array'],
            'definition.layout' => ['nullable', 'array'],
            'definition.meta' => ['nullable', 'array'],
        ]);

        $definition = $data['definition'];
        $blockGraph = $definition['meta']['block_graph'] ?? null;
        if (is_array($blockGraph) && isset($blockGraph['nodes'], $blockGraph['edges'])) {
            $layoutNodes = $definition['layout']['nodes'] ?? [];
            if (is_array($layoutNodes)) {
                foreach ($blockGraph['nodes'] as $index => $node) {
                    if (! is_array($node)) {
                        continue;
                    }
                    $nodeId = (string) ($node['id'] ?? '');
                    if ($nodeId !== '' && isset($layoutNodes[$nodeId]) && is_array($layoutNodes[$nodeId])) {
                        $blockGraph['nodes'][$index]['position'] = $layoutNodes[$nodeId];
                    }
                }
            }
            $definition = app(WorkflowBlockCompiler::class)->compile($blockGraph, $definition);
        }

        $definition = WorkflowRuntime::withDefaultLayout($definition);
        $validator->validate($definition);

        $workflowDefinition->update([
            'definition' => $definition,
        ]);

        $audit->fromRequest($request, 'workflow.definition_updated', WorkflowDefinition::class, $workflowDefinition->id);

        return response()->json(['data' => $workflowDefinition->fresh()]);
    }

    public function destroy(
        Request $request,
        WorkflowDefinition $workflowDefinition,
        AuditLogger $audit,
    ): JsonResponse {
        $this->authorizeDesigner($request);

        if ($workflowDefinition->routineTypes()->exists()) {
            throw ValidationException::withMessages([
                'workflow' => ['No se puede eliminar: está asignado a uno o más tipos de servicio. Quita la asignación antes.'],
            ]);
        }

        if ($workflowDefinition->instances()->exists()) {
            throw ValidationException::withMessages([
                'workflow' => ['No se puede eliminar: hay servicios que ejecutaron este workflow.'],
            ]);
        }

        $workflowId = $workflowDefinition->id;
        $workflowName = $workflowDefinition->name;

        $workflowDefinition->delete();

        $audit->fromRequest($request, 'workflow.deleted', WorkflowDefinition::class, $workflowId, [
            'name' => $workflowName,
        ]);

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function updateRoutineTypeWorkflow(Request $request, RoutineType $routineType): JsonResponse
    {
        $this->authorizeDesigner($request);

        $data = $request->validate([
            'workflow_definition_id' => ['nullable', 'exists:workflow_definitions,id'],
        ]);

        if ($data['workflow_definition_id'] ?? null) {
            $workflow = WorkflowDefinition::query()->findOrFail($data['workflow_definition_id']);
            if ($workflow->status !== 'published') {
                throw ValidationException::withMessages([
                    'workflow_definition_id' => ['Solo puedes asignar workflows publicados.'],
                ]);
            }
        }

        $routineType->update($data);

        return response()->json(['data' => $routineType->fresh(['workflowDefinition', 'formVersion', 'reportTemplateVersion'])]);
    }

    private function assertEditable(WorkflowDefinition $workflowDefinition): void
    {
        if ($workflowDefinition->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => ['Solo se puede editar un workflow en borrador. Duplica o crea uno nuevo para cambios mayores.'],
            ]);
        }
    }

    private function authorizeDesigner(Request $request): void
    {
        if (! PlatformAdmin::isPlatformAdmin($request->user())) {
            abort(403, 'Platform administrator access required for workflow design.');
        }
    }

    private function uniqueSlug(string $base): string
    {
        $slug = Str::slug($base);
        if ($slug === '') {
            $slug = 'workflow';
        }

        $candidate = $slug;
        $suffix = 2;
        while (
            WorkflowDefinition::query()
                ->where('slug', $candidate)
                ->where('version', 1)
                ->exists()
        ) {
            $candidate = $slug.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    /**
     * @return array<string, mixed>
     */
    private function workflowPayload(WorkflowDefinition $workflow): array
    {
        if (! $workflow->relationLoaded('routineTypes') && ! isset($workflow->routine_types_count)) {
            $workflow->loadCount('routineTypes');
        }

        return [
            'id' => $workflow->id,
            'name' => $workflow->name,
            'slug' => $workflow->slug,
            'version' => $workflow->version,
            'status' => $workflow->status,
            'template' => $workflow->definition['meta']['template'] ?? null,
            'routine_types_count' => $workflow->routine_types_count ?? 0,
        ];
    }
}
