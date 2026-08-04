<?php

namespace App\Services\Catalog;

use App\Enums\FormUsage;
use App\Models\CatalogImportLog;
use App\Models\CatalogItem;
use App\Models\EquipmentType;
use App\Models\FormDefinition;
use App\Models\FormVersion;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Clona artículos de sistema (o catálogo base) hacia el tenant sin enlace vivo.
 */
final class CatalogArticleImportService
{
    /**
     * @return array{
     *     item: CatalogItem,
     *     previous_import: bool,
     *     generation: int,
     *     inconsistent_history: bool,
     *     action: string
     * }
     */
    public function importFromSource(
        CatalogItem $source,
        int $companyId,
        ?User $user = null,
        bool $overwrite = false,
        bool $forceNew = false,
    ): array {
        if (! $source->is_system_template && $source->company_id !== $companyId) {
            throw ValidationException::withMessages([
                'source' => ['El artículo origen no es un plantilla de sistema válida.'],
            ]);
        }

        $existing = CatalogItem::query()
            ->where('company_id', $companyId)
            ->where('source_system_catalog_item_id', $source->id)
            ->orderByDesc('import_generation')
            ->first();

        $generation = ($existing?->import_generation ?? 0) + 1;
        $inconsistent = $existing !== null && ($existing->import_generation > 1 || $forceNew);

        if ($existing !== null && ! $overwrite && ! $forceNew) {
            return [
                'item' => $existing,
                'previous_import' => true,
                'generation' => $existing->import_generation,
                'inconsistent_history' => $inconsistent,
                'action' => 'skipped',
            ];
        }

        $equipmentType = $this->resolveEquipmentType($source, $companyId);

        if ($existing !== null && $overwrite && ! $forceNew) {
            $existing->update([
                'equipment_type_id' => $equipmentType->id,
                'code' => $source->code,
                'name' => $source->name,
                'manufacturer' => $source->manufacturer,
                'oem_equipment_model_id' => $source->oem_equipment_model_id,
                'specifications' => $source->specifications,
                'image_path' => $source->image_path,
                'import_generation' => $generation,
                'is_detached_copy' => false,
            ]);

            $this->log($companyId, $user, $source, $existing, 'overwrite', $generation);

            return [
                'item' => $existing->fresh(['equipmentType']),
                'previous_import' => true,
                'generation' => $generation,
                'inconsistent_history' => $inconsistent,
                'action' => 'overwrite',
            ];
        }

        $code = $this->uniqueCode($companyId, $source->code);

        $item = CatalogItem::query()->create([
            'company_id' => $companyId,
            'is_system_template' => false,
            'source_system_catalog_item_id' => $source->id,
            'import_generation' => $generation,
            'equipment_type_id' => $equipmentType->id,
            'code' => $code,
            'name' => $source->name,
            'manufacturer' => $source->manufacturer,
            'oem_equipment_model_id' => $source->oem_equipment_model_id,
            'specifications' => $source->specifications,
            'image_path' => $source->image_path,
            'is_detached_copy' => false,
        ]);

        $this->log($companyId, $user, $source, $item, 'clone', $generation);

        return [
            'item' => $item->load('equipmentType'),
            'previous_import' => $existing !== null,
            'generation' => $generation,
            'inconsistent_history' => $inconsistent,
            'action' => 'clone',
        ];
    }

    private function resolveEquipmentType(CatalogItem $source, int $companyId): EquipmentType
    {
        $sourceType = $source->equipmentType;
        if ($sourceType === null) {
            throw ValidationException::withMessages([
                'source' => ['El artículo origen no tiene tipo de artículo.'],
            ]);
        }

        $match = EquipmentType::query()
            ->where('company_id', $companyId)
            ->where('code', $sourceType->code)
            ->first();

        if ($match !== null) {
            return $match;
        }

        $formId = $this->cloneArticleForm($sourceType->defaultFormDefinition, $companyId);

        return EquipmentType::query()->create([
            'company_id' => $companyId,
            'code' => $sourceType->code,
            'name' => $sourceType->name,
            'description' => $sourceType->description,
            'default_form_definition_id' => $formId,
            'sort_order' => $sourceType->sort_order,
        ]);
    }

    private function cloneArticleForm(?FormDefinition $sourceForm, int $companyId): ?int
    {
        if ($sourceForm === null) {
            return null;
        }

        $usage = $sourceForm->usage->canonical();
        $slug = Str::slug($sourceForm->slug.'-import-'.Str::random(4));

        $clone = FormDefinition::query()->create([
            'company_id' => $companyId,
            'name' => $sourceForm->name.' (importado)',
            'slug' => $slug,
            'usage' => $usage,
        ]);

        $published = $sourceForm->versions()->where('status', 'published')->orderByDesc('version')->first();
        $schema = $published?->schema ?? ['sections' => []];

        FormVersion::query()->create([
            'form_definition_id' => $clone->id,
            'version' => 1,
            'status' => 'published',
            'schema' => $schema,
        ]);

        FormVersion::query()->create([
            'form_definition_id' => $clone->id,
            'version' => 2,
            'status' => 'draft',
            'schema' => $schema,
        ]);

        return $clone->id;
    }

    private function uniqueCode(int $companyId, string $code): string
    {
        $base = Str::limit($code, 58, '');
        $candidate = $base;
        $i = 1;
        while (CatalogItem::query()->where('company_id', $companyId)->where('code', $candidate)->exists()) {
            $candidate = $base.'-'.$i;
            $i++;
        }

        return $candidate;
    }

    private function log(
        int $companyId,
        ?User $user,
        CatalogItem $source,
        CatalogItem $result,
        string $action,
        int $generation,
    ): void {
        CatalogImportLog::query()->create([
            'company_id' => $companyId,
            'user_id' => $user?->id,
            'source_catalog_item_id' => $source->id,
            'result_catalog_item_id' => $result->id,
            'action' => $action,
            'generation' => $generation,
        ]);
    }
}
