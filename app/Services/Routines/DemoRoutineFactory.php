<?php

namespace App\Services\Routines;

use App\Enums\RoutineStatus;
use App\Models\FormOptionCatalog;
use App\Models\Routine;
use App\Models\RoutineType;
use App\Models\Site;
use App\Models\User;
use App\Services\Forms\FormResponseValidator;
use App\Services\Workflow\WorkflowRuntime;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DemoRoutineFactory
{
    /** @var array<string, string> */
    private const SELECT_DEFAULTS = [
        'nivel_combustible' => 'tres_cuartos',
        'filtro_aceite_reemplazado' => 'si',
    ];

    /** @var array<string, int|float> */
    private const NUMBER_DEFAULTS = [
        'kilometraje' => 48250,
        'frenos_espesor_pastillas_mm' => 9,
        'aceite_viscosidad' => 20,
        'bateria_cca' => 640,
    ];

    public function __construct(
        private readonly WorkflowRuntime $workflow,
        private readonly FormResponseValidator $formValidator,
    ) {}

    public function createForCompany(int $companyId, User $assignee): Routine
    {
        $type = RoutineType::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->with('formVersion')
            ->firstOrFail();

        $site = Site::query()->where('company_id', $companyId)->firstOrFail();
        $asset = \App\Models\Asset::query()->where('company_id', $companyId)->firstOrFail();

        $routine = Routine::query()->create([
            'company_id' => $companyId,
            'site_id' => $site->id,
            'asset_id' => $asset->id,
            'routine_type_id' => $type->id,
            'assigned_to' => $assignee->id,
            'status' => RoutineStatus::Assigned,
            'scheduled_at' => now(),
            'is_demo' => true,
        ]);

        $this->workflow->ensureInstance($routine->load('routineType.workflowDefinition'));

        $schema = $type->formVersion?->schema ?? ['sections' => []];
        $responses = $this->fakeResponses($schema, $routine->id, $companyId);
        $this->formValidator->validate($schema, $responses, $companyId);

        $routine->executions()->create([
            'performed_by' => $assignee->id,
            'responses' => $responses,
            'technician_comments' => 'Rutina demo — datos sintéticos para prueba rápida.',
            'corrected_comments' => 'Rutina demo — datos sintéticos para prueba rápida.',
            'duration_minutes' => 75,
            'status' => 'draft',
        ]);

        return $routine->fresh(['asset', 'site', 'routineType', 'latestExecution', 'workflowInstance']);
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function fakeResponses(array $schema, int $routineId, int $companyId): array
    {
        $responses = [];

        foreach ($schema['sections'] ?? [] as $section) {
            foreach ($section['fields'] ?? [] as $field) {
                $key = $field['key'] ?? null;
                if ($key === null) {
                    continue;
                }

                $responses[$key] = $this->fakeValueForField($field, $routineId, $companyId);
            }
        }

        return $responses;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function fakeValueForField(array $field, int $routineId, int $companyId): mixed
    {
        $key = (string) ($field['key'] ?? '');
        $type = $field['type'] ?? 'text';

        if (isset(self::SELECT_DEFAULTS[$key])) {
            return self::SELECT_DEFAULTS[$key];
        }

        if (isset(self::NUMBER_DEFAULTS[$key])) {
            return self::NUMBER_DEFAULTS[$key];
        }

        return match ($type) {
            'number' => random_int(5, 120),
            'textarea' => $key === 'observaciones_recepcion'
                ? 'Sin daños visibles reportados. Cliente solicita revisión estándar premium.'
                : 'Observación demo generada automáticamente.',
            'select', 'options' => $this->firstCatalogValue($field, $companyId) ?? 'operativo',
            'photo' => $this->fakePhotoValue($routineId, $key),
            default => 'OK',
        };
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function firstCatalogValue(array $field, int $companyId): ?string
    {
        $catalogId = $field['option_catalog_id'] ?? null;
        if ($catalogId === null) {
            return null;
        }

        $catalog = FormOptionCatalog::query()
            ->where('company_id', $companyId)
            ->whereKey($catalogId)
            ->first();

        if ($catalog === null || $catalog->options === []) {
            return null;
        }

        $first = $catalog->options[0]['value'] ?? null;

        return $first !== null ? (string) $first : null;
    }

    /**
     * @return list<array{path: string, caption?: string}>
     */
    private function fakePhotoValue(int $routineId, string $fieldKey): array
    {
        $diskName = config('noah.evidence.disk', 'evidence');
        $path = config('noah.evidence.path_prefix').'/'.$routineId.'/demo/'.$fieldKey.'-'.Str::uuid().'.jpg';
        $binary = $this->demoJpegBytes($fieldKey);

        $disk = Storage::disk($diskName);
        $dir = dirname($path);
        if (! $disk->exists($dir)) {
            $disk->makeDirectory($dir);
        }
        $disk->put($path, $binary);

        return [['path' => $path, 'caption' => 'Evidencia demo — '.$fieldKey]];
    }

    private function demoJpegBytes(string $fieldKey): string
    {
        if (function_exists('imagecreatetruecolor')) {
            $width = 640;
            $height = 480;
            $image = imagecreatetruecolor($width, $height);
            if ($image !== false) {
                $background = imagecolorallocate($image, 226, 232, 240);
                $accent = imagecolorallocate($image, 180, 83, 9);
                $text = imagecolorallocate($image, 51, 65, 85);
                imagefilledrectangle($image, 0, 0, $width, $height, $background);
                imagefilledrectangle($image, 0, 0, $width, 56, $accent);
                $label = 'Evidencia demo — '.$fieldKey;
                imagestring($image, 5, 16, 20, 'Noah', $background);
                imagestring($image, 5, 16, (int) ($height / 2), $label, $text);
                ob_start();
                imagejpeg($image, null, 88);
                $jpeg = ob_get_clean();
                imagedestroy($image);
                if (is_string($jpeg) && $jpeg !== '') {
                    return $jpeg;
                }
            }
        }

        $fallback = base64_decode(
            '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAn/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCwAA//2Q==',
            true,
        );

        return $fallback !== false ? $fallback : '';
    }
}
