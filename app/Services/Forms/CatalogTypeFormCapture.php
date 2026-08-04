<?php

namespace App\Services\Forms;

use App\Models\FormDefinition;
use App\Models\EquipmentType;
use App\Models\SupplyType;

class CatalogTypeFormCapture
{
    public function forEquipmentType(EquipmentType $type, FormDesignSettings $designSettings): array
    {
        $type->loadMissing('defaultFormDefinition');

        return $this->buildPayload(
            $type->defaultFormDefinition,
            $designSettings,
            'Asigna un formulario de uso Equipo en Catálogos → Tipos de equipo.',
        );
    }

    public function forSupplyType(SupplyType $type, FormDesignSettings $designSettings): array
    {
        $type->loadMissing('defaultFormDefinition');

        return $this->buildPayload(
            $type->defaultFormDefinition,
            $designSettings,
            'Asigna un formulario de uso Insumo en Inventario → Tipos de artículo.',
        );
    }

  /**
     * @return array{
     *     configured: bool,
     *     message?: string,
     *     form?: array{id: int, name: string},
     *     schema?: array<string, mixed>,
     *     form_design?: array{settings: array<string, mixed>, option_catalogs: mixed}
     * }
     */
    private function buildPayload(
        ?FormDefinition $form,
        FormDesignSettings $designSettings,
        string $missingMessage,
    ): array {
        if ($form === null) {
            return [
                'configured' => false,
                'message' => $missingMessage,
            ];
        }

        $published = $form->versions()
            ->where('status', 'published')
            ->orderByDesc('version')
            ->first();

        if ($published === null) {
            return [
                'configured' => false,
                'message' => "El formulario «{$form->name}» no tiene versión publicada. Publícalo en Diseño → Formularios.",
            ];
        }

        return [
            'configured' => true,
            'form' => [
                'id' => $form->id,
                'name' => $form->name,
            ],
            'schema' => $published->schema,
            'form_design' => [
                'settings' => $designSettings->forCurrentCompany(),
                'option_catalogs' => $designSettings->optionCatalogsForCurrentCompany(),
            ],
        ];
    }
}
