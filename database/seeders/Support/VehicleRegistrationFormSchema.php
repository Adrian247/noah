<?php

namespace Database\Seeders\Support;

/**
 * Ficha técnica de registro para tipo de equipo Vehículo (uso FormUsage::Equipment).
 * Derivada de la ficha L200 2018: motorización, frenos/suspensión, dimensiones y capacidades.
 */
final class VehicleRegistrationFormSchema
{
    /**
     * @return array{sections: list<array<string, mixed>>}
     */
    public static function sections(
        int $combustibleTipoCatalogId,
        int $traccionCatalogId,
    ): array {
        return [
            [
                'title' => 'Identificación',
                'fields' => [
                    [
                        'key' => 'modelo',
                        'type' => 'text',
                        'label' => 'Modelo',
                        'required' => true,
                    ],
                    [
                        'key' => 'anio',
                        'type' => 'number',
                        'label' => 'Año modelo',
                        'required' => true,
                    ],
                    [
                        'key' => 'mercado',
                        'type' => 'text',
                        'label' => 'Mercado / región',
                        'required' => false,
                    ],
                    [
                        'key' => 'chasis',
                        'type' => 'text',
                        'label' => 'Plataforma / chasis',
                        'required' => false,
                    ],
                    [
                        'key' => 'variante',
                        'type' => 'text',
                        'label' => 'Variante / versión',
                        'required' => false,
                    ],
                ],
            ],
            [
                'title' => 'Motorización y desempeño',
                'fields' => [
                    [
                        'key' => 'tipo_combustible',
                        'type' => 'select',
                        'label' => 'Tipo de combustible',
                        'required' => true,
                        'option_catalog_id' => $combustibleTipoCatalogId,
                    ],
                    [
                        'key' => 'motor',
                        'type' => 'text',
                        'label' => 'Motor (cilindrada y configuración)',
                        'required' => true,
                    ],
                    [
                        'key' => 'potencia_hp',
                        'type' => 'number',
                        'label' => 'Potencia máxima (HP)',
                        'required' => false,
                    ],
                    [
                        'key' => 'potencia_rpm',
                        'type' => 'number',
                        'label' => 'RPM a potencia máxima',
                        'required' => false,
                    ],
                    [
                        'key' => 'torque_lb_pie',
                        'type' => 'number',
                        'label' => 'Torque máximo (lb-pie)',
                        'required' => false,
                    ],
                    [
                        'key' => 'torque_rpm',
                        'type' => 'number',
                        'label' => 'RPM a torque máximo',
                        'required' => false,
                    ],
                    [
                        'key' => 'transmision',
                        'type' => 'text',
                        'label' => 'Transmisión',
                        'required' => false,
                    ],
                    [
                        'key' => 'traccion',
                        'type' => 'select',
                        'label' => 'Tracción',
                        'required' => true,
                        'option_catalog_id' => $traccionCatalogId,
                    ],
                    [
                        'key' => 'alimentacion',
                        'type' => 'text',
                        'label' => 'Sistema de alimentación',
                        'required' => false,
                    ],
                ],
            ],
            [
                'title' => 'Suspensión, frenos y dirección',
                'fields' => [
                    [
                        'key' => 'suspension_delantera',
                        'type' => 'textarea',
                        'label' => 'Suspensión delantera',
                        'required' => false,
                    ],
                    [
                        'key' => 'suspension_trasera',
                        'type' => 'textarea',
                        'label' => 'Suspensión trasera',
                        'required' => false,
                    ],
                    [
                        'key' => 'frenos_delanteros',
                        'type' => 'text',
                        'label' => 'Frenos delanteros',
                        'required' => false,
                    ],
                    [
                        'key' => 'frenos_traseros',
                        'type' => 'text',
                        'label' => 'Frenos traseros',
                        'required' => false,
                    ],
                    [
                        'key' => 'asistencias',
                        'type' => 'text',
                        'label' => 'Asistencias de frenado (ABS, EBD, etc.)',
                        'required' => false,
                    ],
                    [
                        'key' => 'direccion',
                        'type' => 'text',
                        'label' => 'Dirección',
                        'required' => false,
                    ],
                ],
            ],
            [
                'title' => 'Dimensiones, peso y capacidades',
                'fields' => [
                    [
                        'key' => 'largo_mm',
                        'type' => 'number',
                        'label' => 'Longitud total (mm)',
                        'required' => false,
                    ],
                    [
                        'key' => 'ancho_mm',
                        'type' => 'number',
                        'label' => 'Anchura total (mm)',
                        'required' => false,
                    ],
                    [
                        'key' => 'alto_mm',
                        'type' => 'number',
                        'label' => 'Altura total (mm)',
                        'required' => false,
                    ],
                    [
                        'key' => 'batalla_mm',
                        'type' => 'number',
                        'label' => 'Distancia entre ejes / batalla (mm)',
                        'required' => false,
                    ],
                    [
                        'key' => 'capacidad_carga_kg',
                        'type' => 'number',
                        'label' => 'Capacidad de carga (kg)',
                        'required' => false,
                    ],
                    [
                        'key' => 'tanque_litros',
                        'type' => 'number',
                        'label' => 'Capacidad del tanque (L)',
                        'required' => false,
                    ],
                    [
                        'key' => 'rines',
                        'type' => 'text',
                        'label' => 'Rines',
                        'required' => false,
                    ],
                ],
            ],
        ];
    }
}
