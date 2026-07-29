<?php

namespace App\Services\Forms;

use App\Enums\FormUsage;
use App\Models\FormDefinition;
use App\Support\CurrentCompany;

class FormFieldCatalog
{
    /**
     * @param  FormUsage|null  $usage  Por defecto solo formularios de rutina (informes).
     * @return list<array{key: string, label: string, form_name: string, field_type?: string}>
     */
    public function listForCurrentCompany(?FormUsage $usage = FormUsage::Routine): array
    {
        $companyId = app(CurrentCompany::class)->id();
        if ($companyId === null) {
            return $this->baseExecutionFields();
        }

        $fields = $this->baseExecutionFields();

        $query = FormDefinition::query()->where('company_id', $companyId);
        if ($usage !== null) {
            $query->where('usage', $usage);
        }

        $forms = $query
            ->with(['versions' => fn ($q) => $q->where('status', 'published')->orderByDesc('version')])
            ->get();

        foreach ($forms as $form) {
            $version = $form->versions->first();
            if ($version === null) {
                continue;
            }
            foreach ($version->schema['sections'] ?? [] as $section) {
                foreach ($section['fields'] ?? [] as $field) {
                    $key = $field['key'] ?? null;
                    if ($key === null || $key === '') {
                        continue;
                    }
                    $fields[] = [
                        'key' => $key,
                        'label' => ($field['label'] ?? $key).' ('.$form->name.')',
                        'form_name' => $form->name,
                        'field_type' => $field['type'] ?? 'text',
                    ];
                }
            }
        }

        $unique = [];
        foreach ($fields as $f) {
            $unique[$f['key']] = $f;
        }

        return array_values($unique);
    }

    /**
     * @return list<array{key: string, label: string, form_name: string, field_type?: string}>
     */
    private function baseExecutionFields(): array
    {
        return [
            [
                'key' => 'corrected_comments',
                'label' => 'Comentario corregido (IA)',
                'form_name' => 'Ejecución',
                'field_type' => 'textarea',
            ],
            [
                'key' => 'technician_comments',
                'label' => 'Comentario técnico',
                'form_name' => 'Ejecución',
                'field_type' => 'textarea',
            ],
        ];
    }
}
