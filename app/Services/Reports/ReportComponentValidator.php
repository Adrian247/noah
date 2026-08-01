<?php

namespace App\Services\Reports;

use Illuminate\Validation\ValidationException;

/**
 * Valida el árbol de componentes del diseñador antes de persistir.
 */
class ReportComponentValidator
{
    private const ALLOWED_TYPES = [
        'title',
        'subtitle',
        'paragraph',
        'text',
        'image',
        'section_template',
        'divider',
    ];

    /**
     * @param  list<mixed>  $components
     * @return list<array<string, mixed>>
     */
    public function validate(array $components): array
    {
        $normalized = [];

        foreach ($components as $index => $component) {
            if (! is_array($component)) {
                throw ValidationException::withMessages([
                    "components.{$index}" => ['Cada bloque debe ser un objeto.'],
                ]);
            }

            $type = (string) ($component['type'] ?? '');
            if (! in_array($type, self::ALLOWED_TYPES, true)) {
                throw ValidationException::withMessages([
                    "components.{$index}.type" => ['Tipo de bloque no permitido: '.$type],
                ]);
            }

            $row = ['type' => $type];

            if (isset($component['align']) && is_string($component['align'])) {
                $align = $component['align'];
                if (! in_array($align, ['left', 'center', 'right'], true)) {
                    throw ValidationException::withMessages([
                        "components.{$index}.align" => ['Alineación no válida.'],
                    ]);
                }
                $row['align'] = $align;
            }

            if (isset($component['color']) && is_string($component['color']) && $component['color'] !== '') {
                $color = trim($component['color']);
                if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color) === 1) {
                    $row['color'] = $color;
                }
            }

            if (isset($component['size_pt']) && is_numeric($component['size_pt'])) {
                $row['size_pt'] = max(6, min(72, (int) $component['size_pt']));
            }

            if (in_array($type, ['title', 'subtitle', 'text'], true)) {
                $row['text'] = (string) ($component['text'] ?? '');
            }

            if (in_array($type, ['paragraph', 'image'], true)) {
                $field = trim((string) ($component['field'] ?? ''));
                if ($field === '') {
                    throw ValidationException::withMessages([
                        "components.{$index}.field" => ['El campo del formulario es obligatorio.'],
                    ]);
                }
                $row['field'] = $field;
                if ($type === 'paragraph') {
                    if (isset($component['label']) && is_string($component['label'])) {
                        $row['label'] = mb_substr($component['label'], 0, 200);
                    }
                    if (array_key_exists('show_field_key', $component)) {
                        $row['show_field_key'] = (bool) $component['show_field_key'];
                    }
                }
            }

            if ($type === 'section_template') {
                $id = (int) ($component['section_template_id'] ?? 0);
                if ($id < 1) {
                    throw ValidationException::withMessages([
                        "components.{$index}.section_template_id" => ['Selecciona una sección reutilizable.'],
                    ]);
                }
                $row['section_template_id'] = $id;
            }

            if ($type === 'divider') {
                $style = (string) ($component['style'] ?? 'solid');
                if (! in_array($style, ['solid', 'dashed', 'dotted'], true)) {
                    $style = 'solid';
                }
                $row['style'] = $style;
                $row['margin_pt'] = max(0, min(80, (int) ($component['margin_pt'] ?? 16)));
            }

            $normalized[] = $row;
        }

        return $normalized;
    }
}
