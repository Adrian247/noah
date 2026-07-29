<?php

namespace Database\Seeders\Support;

/** Esquema publicado para `inspeccion-vehiculo-v1` (uso Rutina; change 037). */
final class NormalizedVehicleFormSchema
{
    /**
     * @return array{sections: list<array<string, mixed>>}
     */
    public static function sections(
        int $estadoCatalogId,
        int $combustibleCatalogId,
        int $siNoCatalogId,
    ): array {
        $component = static fn (string $key, string $label, bool $required = true) => [
            'key' => $key,
            'type' => 'options',
            'label' => $label,
            'required' => $required,
            'option_catalog_id' => $estadoCatalogId,
        ];

        return [
            [
                'title' => 'Recepción e identificación',
                'fields' => [
                    [
                        'key' => 'kilometraje',
                        'type' => 'number',
                        'label' => 'Kilometraje (km)',
                        'required' => true,
                    ],
                    [
                        'key' => 'nivel_combustible',
                        'type' => 'select',
                        'label' => 'Nivel de combustible',
                        'required' => false,
                        'option_catalog_id' => $combustibleCatalogId,
                    ],
                    [
                        'key' => 'observaciones_recepcion',
                        'type' => 'textarea',
                        'label' => 'Observaciones de ingreso',
                        'required' => false,
                    ],
                ],
            ],
            [
                'title' => 'Motor y fluidos',
                'fields' => [
                    $component('motor_estado', 'Estado general motor / fugas'),
                    $component('aceite_motor', 'Nivel y estado aceite motor'),
                    [
                        'key' => 'filtro_aceite_reemplazado',
                        'type' => 'select',
                        'label' => '¿Filtro de aceite reemplazado?',
                        'required' => false,
                        'option_catalog_id' => $siNoCatalogId,
                    ],
                ],
            ],
            [
                'title' => 'Frenos',
                'fields' => [
                    $component('frenos_delanteros', 'Frenos delanteros (disco)'),
                    $component('frenos_traseros', 'Frenos traseros (tambor)'),
                    $component('liquido_frenos', 'Líquido de frenos / ABS', false),
                ],
            ],
            [
                'title' => 'Filtros y aire',
                'fields' => [
                    $component('filtro_aire', 'Filtro de aire'),
                    $component('filtro_habitaculo', 'Filtro habitáculo', false),
                ],
            ],
            [
                'title' => 'Suspensión y dirección',
                'fields' => [
                    $component('suspension', 'Suspensión / amortiguadores', false),
                    $component('direccion', 'Dirección hidráulica', false),
                ],
            ],
            [
                'title' => 'Eléctrico básico',
                'fields' => [
                    $component('bateria', 'Batería y bornes', false),
                    $component('luces', 'Luces exteriores', false),
                ],
            ],
            [
                'title' => 'Cierre',
                'fields' => [
                    [
                        'key' => 'comentarios_cierre',
                        'type' => 'textarea',
                        'label' => 'Comentarios finales',
                        'required' => false,
                    ],
                    [
                        'key' => 'foto_evidencia',
                        'type' => 'photo',
                        'label' => 'Evidencia general (1 foto)',
                        'required' => false,
                        'allow_multiple' => false,
                        'caption_enabled' => true,
                        'caption_required' => false,
                    ],
                ],
            ],
        ];
    }
}
