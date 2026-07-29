<?php

namespace Database\Seeders\Support;

/**
 * Formularios de ficha (uso FormUsage::Supply) por tipo de insumo.
 * Campos normalizados desde la tabla de refacciones L200 2018.
 */
final class NormalizedSupplyFormSchemas
{
    /**
     * @return array{key: string, type: string, label: string, required: bool, option_catalog_id: int}
     */
    private static function unidadField(int $unidadCatalogId, bool $required = true): array
    {
        return [
            'key' => 'unidad',
            'type' => 'select',
            'label' => 'Unidad',
            'required' => $required,
            'option_catalog_id' => $unidadCatalogId,
        ];
    }

    /**
     * @return array{sections: list<array<string, mixed>>}
     */
    public static function filtros(int $posicionFiltroCatalogId, int $unidadCatalogId): array
    {
        return [
            'sections' => [
                [
                    'title' => 'Identificación del filtro',
                    'fields' => [
                        [
                            'key' => 'marca',
                            'type' => 'text',
                            'label' => 'Marca',
                            'required' => true,
                        ],
                        [
                            'key' => 'referencia_oem',
                            'type' => 'text',
                            'label' => 'Referencia OEM / catálogo',
                            'required' => true,
                        ],
                        [
                            'key' => 'posicion',
                            'type' => 'select',
                            'label' => 'Posición / aplicación',
                            'required' => true,
                            'option_catalog_id' => $posicionFiltroCatalogId,
                        ],
                        self::unidadField($unidadCatalogId),
                        [
                            'key' => 'notas_mercado',
                            'type' => 'textarea',
                            'label' => 'Notas de mercado / equivalencias',
                            'required' => false,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array{sections: list<array<string, mixed>>}
     */
    public static function frenos(int $posicionFrenoCatalogId, int $unidadCatalogId): array
    {
        return [
            'sections' => [
                [
                    'title' => 'Identificación de frenos / balatas',
                    'fields' => [
                        [
                            'key' => 'marca',
                            'type' => 'text',
                            'label' => 'Marca',
                            'required' => true,
                        ],
                        [
                            'key' => 'referencia_oem',
                            'type' => 'text',
                            'label' => 'Referencia OEM / catálogo',
                            'required' => true,
                        ],
                        [
                            'key' => 'posicion',
                            'type' => 'select',
                            'label' => 'Posición',
                            'required' => true,
                            'option_catalog_id' => $posicionFrenoCatalogId,
                        ],
                        [
                            'key' => 'material',
                            'type' => 'text',
                            'label' => 'Material (p. ej. cerámicas)',
                            'required' => false,
                        ],
                        self::unidadField($unidadCatalogId),
                        [
                            'key' => 'notas_mercado',
                            'type' => 'textarea',
                            'label' => 'Notas de mercado / rango de precio',
                            'required' => false,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array{sections: list<array<string, mixed>>}
     */
    public static function suspension(
        int $posicionSuspensionCatalogId,
        int $tecnologiaCatalogId,
        int $unidadCatalogId,
    ): array {
        return [
            'sections' => [
                [
                    'title' => 'Identificación de suspensión',
                    'fields' => [
                        [
                            'key' => 'marca',
                            'type' => 'text',
                            'label' => 'Marca',
                            'required' => true,
                        ],
                        [
                            'key' => 'referencia_oem',
                            'type' => 'text',
                            'label' => 'Referencia OEM / catálogo',
                            'required' => false,
                        ],
                        [
                            'key' => 'posicion',
                            'type' => 'select',
                            'label' => 'Posición',
                            'required' => true,
                            'option_catalog_id' => $posicionSuspensionCatalogId,
                        ],
                        [
                            'key' => 'tecnologia',
                            'type' => 'select',
                            'label' => 'Tecnología',
                            'required' => false,
                            'option_catalog_id' => $tecnologiaCatalogId,
                        ],
                        self::unidadField($unidadCatalogId),
                        [
                            'key' => 'notas_mercado',
                            'type' => 'textarea',
                            'label' => 'Notas de mercado',
                            'required' => false,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array{sections: list<array<string, mixed>>}
     */
    public static function fluidos(int $unidadCatalogId): array
    {
        return [
            'sections' => [
                [
                    'title' => 'Identificación de fluido / lubricante',
                    'fields' => [
                        [
                            'key' => 'marca',
                            'type' => 'text',
                            'label' => 'Marca',
                            'required' => true,
                        ],
                        [
                            'key' => 'referencia_oem',
                            'type' => 'text',
                            'label' => 'Referencia / especificación comercial',
                            'required' => false,
                        ],
                        [
                            'key' => 'viscosidad',
                            'type' => 'text',
                            'label' => 'Viscosidad (p. ej. 5W-30)',
                            'required' => false,
                        ],
                        [
                            'key' => 'especificacion',
                            'type' => 'text',
                            'label' => 'Especificación técnica (API, ACEA, OEM)',
                            'required' => false,
                        ],
                        [
                            'key' => 'capacidad_litros',
                            'type' => 'number',
                            'label' => 'Capacidad del envase (L)',
                            'required' => false,
                        ],
                        self::unidadField($unidadCatalogId),
                        [
                            'key' => 'notas_mercado',
                            'type' => 'textarea',
                            'label' => 'Notas de mercado',
                            'required' => false,
                        ],
                    ],
                ],
            ],
        ];
    }
}
