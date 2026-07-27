<?php

namespace App\Services\Forms;

use App\Models\FormOptionCatalog;
use Illuminate\Validation\ValidationException;

class FormResponseValidator
{
    public function __construct(
        private FormDesignSettings $designSettings,
    ) {}

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $responses
     */
    public function validate(array $schema, array $responses, int $companyId): void
    {
        $errors = [];

        foreach ($schema['sections'] ?? [] as $section) {
            foreach ($section['fields'] ?? [] as $field) {
                $key = $field['key'] ?? null;
                if ($key === null || $key === '') {
                    continue;
                }

                $type = $field['type'] ?? 'text';
                $required = (bool) ($field['required'] ?? false);
                $value = $responses[$key] ?? null;

                if ($type === 'photo') {
                    $this->validatePhoto($field, $value, $errors, $key, $required);

                    continue;
                }

                $empty = $value === null || $value === '';

                if ($required && $empty) {
                    $errors[$key] = 'El campo '.($field['label'] ?? $key).' es obligatorio.';

                    continue;
                }

                if ($empty) {
                    continue;
                }

                if ($type === 'number' && ! is_numeric($value)) {
                    $errors[$key] = 'Debe ser un número.';
                }

                if (in_array($type, ['select', 'options'], true)) {
                    $this->validateSelect($field, (string) $value, $companyId, $errors, $key);
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, string>  $errors
     */
    private function validatePhoto(array $field, mixed $value, array &$errors, string $key, bool $required): void
    {
        $items = PhotoResponseNormalizer::toItems($value);
        $label = $field['label'] ?? $key;
        $allowMultiple = (bool) ($field['allow_multiple'] ?? false);
        $maxImages = $allowMultiple ? (int) ($field['max_images'] ?? 4) : 1;
        $captionRequired = (bool) ($field['caption_required'] ?? false) && (bool) ($field['caption_enabled'] ?? false);

        if ($required && $items === []) {
            $errors[$key] = 'El campo '.$label.' es obligatorio.';

            return;
        }

        if (count($items) > $maxImages) {
            $errors[$key] = 'Máximo '.$maxImages.' imagen(es) permitidas.';
        }

        foreach ($items as $index => $item) {
            if ($item['path'] === '') {
                $errors[$key] = 'Imagen inválida en '.$label.'.';

                return;
            }
            if ($captionRequired && empty($item['caption'])) {
                $errors[$key] = 'La descripción es obligatoria para cada imagen en '.$label.'.';

                return;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, string>  $errors
     */
    private function validateSelect(array $field, string $value, int $companyId, array &$errors, string $key): void
    {
        $catalogId = $field['option_catalog_id'] ?? null;
        if ($catalogId === null) {
            return;
        }

        $catalog = FormOptionCatalog::query()
            ->where('company_id', $companyId)
            ->whereKey($catalogId)
            ->first();

        if ($catalog === null) {
            $errors[$key] = 'Catálogo de opciones no válido.';

            return;
        }

        $allowed = collect($catalog->options ?? [])->pluck('value')->map(fn ($v) => (string) $v)->all();
        if (! in_array($value, $allowed, true)) {
            $errors[$key] = 'Valor no permitido para este selector.';
        }
    }
}
