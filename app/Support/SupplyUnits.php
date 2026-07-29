<?php

namespace App\Support;

use App\Models\FormOptionCatalog;

/**
 * Catálogo de unidades de insumo (conteo, volumen, masa, longitud y empaque).
 * Valores estables en snake/abreviatura corta para `supply_items.unit` y fichas.
 */
final class SupplyUnits
{
    public const CATALOG_SLUG = 'unidad-insumo';

    /**
     * @return list<array{value: string, label: string, description: string}>
     */
    public const OPTIONS = [
        // —— Conteo / presentación ——
        [
            'value' => 'pza',
            'label' => 'Pieza',
            'description' => 'Unidad individual (filtro, sensor, tornillo, etc.).',
        ],
        [
            'value' => 'jgo',
            'label' => 'Juego',
            'description' => 'Conjunto completo para una aplicación (p. ej. balatas delanteras).',
        ],
        [
            'value' => 'par',
            'label' => 'Par',
            'description' => 'Dos piezas iguales (p. ej. amortiguadores).',
        ],
        [
            'value' => 'kit',
            'label' => 'Kit',
            'description' => 'Paquete de servicio con varios componentes (junta + filtros, etc.).',
        ],
        [
            'value' => 'set',
            'label' => 'Set',
            'description' => 'Conjunto comercial distinto de un juego de aplicación (herramientas, surtido).',
        ],

        // —— Empaque comercial ——
        [
            'value' => 'caja',
            'label' => 'Caja',
            'description' => 'Caja del proveedor (cantidad interna variable).',
        ],
        [
            'value' => 'pqt',
            'label' => 'Paquete',
            'description' => 'Empaque multipieza (bolsa, blister, paquete cerrado).',
        ],
        [
            'value' => 'bolsa',
            'label' => 'Bolsa',
            'description' => 'Contenido a granel o granel en bolsa (abrazaderas, remaches).',
        ],
        [
            'value' => 'rollo',
            'label' => 'Rollo',
            'description' => 'Material en rollo (cinta, empaque, manguera en bobina).',
        ],
        [
            'value' => 'bote',
            'label' => 'Bote / envase',
            'description' => 'Envase comercial sin medir el volumen interno (grasa, pasta).',
        ],
        [
            'value' => 'tambor',
            'label' => 'Tambor',
            'description' => 'Tambor o tina industrial (aceite, refrigerante a granel).',
        ],
        [
            'value' => 'cubeta',
            'label' => 'Cubeta',
            'description' => 'Cubeta típica de 19 L / 5 gal en lubricantes y químicos.',
        ],

        // —— Volumen ——
        [
            'value' => 'lt',
            'label' => 'Litro',
            'description' => 'Volumen en litros (aceite, refrigerante, líquido de frenos).',
        ],
        [
            'value' => 'ml',
            'label' => 'Mililitro',
            'description' => 'Volumen en mililitros (aditivos, selladores líquidos).',
        ],
        [
            'value' => 'gal',
            'label' => 'Galón',
            'description' => 'Galón (≈ 3.785 L); usado en algunos envases de aceite/anticongelante.',
        ],

        // —— Masa ——
        [
            'value' => 'kg',
            'label' => 'Kilogramo',
            'description' => 'Masa en kilogramos (grasa, soldadura, químicos sólidos).',
        ],
        [
            'value' => 'g',
            'label' => 'Gramo',
            'description' => 'Masa en gramos (aditivos, pastas, dosis pequeñas).',
        ],
        [
            'value' => 'ton',
            'label' => 'Tonelada',
            'description' => 'Tonelada métrica (insumos a granel / pedido industrial).',
        ],
        [
            'value' => 'lb',
            'label' => 'Libra',
            'description' => 'Libra (≈ 0.454 kg); referencias de importación o ficha técnica.',
        ],

        // —— Longitud ——
        [
            'value' => 'm',
            'label' => 'Metro',
            'description' => 'Longitud en metros (manguera, cable, empaque, banda).',
        ],
        [
            'value' => 'cm',
            'label' => 'Centímetro',
            'description' => 'Longitud en centímetros.',
        ],
        [
            'value' => 'mm',
            'label' => 'Milímetro',
            'description' => 'Longitud en milímetros (cortes precisos, empaque).',
        ],
        [
            'value' => 'ft',
            'label' => 'Pie',
            'description' => 'Pie lineal (≈ 0.305 m); referencias en fichas importadas.',
        ],
        [
            'value' => 'in',
            'label' => 'Pulgada',
            'description' => 'Pulgada lineal; diámetros o longitudes en estándar imperial.',
        ],

        // —— Área ——
        [
            'value' => 'm2',
            'label' => 'Metro cuadrado',
            'description' => 'Área (láminas, aislamiento, recubrimientos).',
        ],
        [
            'value' => 'ft2',
            'label' => 'Pie cuadrado',
            'description' => 'Área en pies cuadrados (fichas o proveedores en estándar imperial).',
        ],

        // —— Tiempo de servicio / consumo ——
        [
            'value' => 'hr',
            'label' => 'Hora',
            'description' => 'Hora de servicio o consumo facturable ligado a insumo/servicio.',
        ],
        [
            'value' => 'dia',
            'label' => 'Día',
            'description' => 'Día de renta o consumo (insumos/servicios por jornada).',
        ],
    ];

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::OPTIONS, 'value');
    }

    /**
     * Opciones del catálogo de la empresa actual, o defaults si no existe.
     *
     * @return list<array{value: string, label: string, description?: string}>
     */
    public static function optionsForCurrentCompany(): array
    {
        $companyId = app(CurrentCompany::class)->id();
        if ($companyId === null) {
            return self::OPTIONS;
        }

        $catalog = FormOptionCatalog::query()
            ->where('company_id', $companyId)
            ->where('slug', self::CATALOG_SLUG)
            ->first();

        if ($catalog === null || ! is_array($catalog->options) || $catalog->options === []) {
            return self::OPTIONS;
        }

        return array_values($catalog->options);
    }
}
