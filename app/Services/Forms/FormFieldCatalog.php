<?php

namespace App\Services\Forms;

use App\Models\FormDefinition;
use App\Support\CurrentCompany;

class FormFieldCatalog
{
    /**
     * @return list<array{key: string, label: string, form_name: string}>
     */
    public function listForCurrentCompany(): array
    {
        $companyId = app(CurrentCompany::class)->id();
        if ($companyId === null) {
            return $this->baseExecutionFields();
        }

        $fields = $this->baseExecutionFields();

        $forms = FormDefinition::query()
            ->where('company_id', $companyId)
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
     * @return list<array{key: string, label: string, form_name: string}>
     */
    private function baseExecutionFields(): array
    {
        return [
            ['key' => 'corrected_comments', 'label' => 'Comentario corregido (IA)', 'form_name' => 'Ejecución'],
            ['key' => 'technician_comments', 'label' => 'Comentario técnico', 'form_name' => 'Ejecución'],
        ];
    }
}
