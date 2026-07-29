<?php

namespace App\Support;

/**
 * Clasificación de materiales de inventario operativo (multi-giro).
 */
final class InventoryTaxonomy
{
    /** @return list<string> */
    public static function sectorValues(): array
    {
        return array_column(self::sectors(), 'value');
    }

    /** @return list<string> */
    public static function materialKindValues(): array
    {
        return array_column(self::materialKinds(), 'value');
    }

    /** @return list<string> */
    public static function movementTypeValues(): array
    {
        return ['in', 'out', 'adjustment', 'consignment', 'consignment_return', 'write_off'];
    }

    public static function consumptionUsageTypeValues(): array
    {
        return ['out', 'consignment', 'write_off'];
    }

    /**
     * @return list<array{value: string, label: string, hint?: string}>
     */
    public static function sectors(): array
    {
        return [
            ['value' => 'industrial', 'label' => 'Industrial', 'hint' => 'Planta, línea de producción, maquinaria pesada.'],
            ['value' => 'mechanical', 'label' => 'Mecánico / automotriz', 'hint' => 'Taller, refacciones, lubricantes, filtros.'],
            ['value' => 'electrical', 'label' => 'Eléctrico', 'hint' => 'Cable, contactores, protecciones, iluminación.'],
            ['value' => 'hydraulic', 'label' => 'Hidráulico / neumático', 'hint' => 'Mangueras, conectores, fluidos, sellos.'],
            ['value' => 'consumable', 'label' => 'Consumibles', 'hint' => 'Abrasivos, EPP desechable, químicos de uso frecuente.'],
            ['value' => 'safety', 'label' => 'Seguridad', 'hint' => 'EPP reutilizable, señalización, botiquín.'],
            ['value' => 'facility', 'label' => 'Instalaciones', 'hint' => 'Limpieza, mantenimiento de edificio, servicios generales.'],
            ['value' => 'other', 'label' => 'Otro', 'hint' => 'Clasificación general.'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function materialKinds(): array
    {
        return [
            ['value' => 'raw', 'label' => 'Materia prima'],
            ['value' => 'spare_part', 'label' => 'Refacción'],
            ['value' => 'consumable', 'label' => 'Consumible'],
            ['value' => 'tool', 'label' => 'Herramienta'],
            ['value' => 'chemical', 'label' => 'Químico / fluido'],
            ['value' => 'packaging', 'label' => 'Empaque'],
            ['value' => 'other', 'label' => 'Otro'],
        ];
    }

    public static function sectorLabel(string $value): string
    {
        foreach (self::sectors() as $row) {
            if ($row['value'] === $value) {
                return $row['label'];
            }
        }

        return $value;
    }

    public static function materialKindLabel(string $value): string
    {
        foreach (self::materialKinds() as $row) {
            if ($row['value'] === $value) {
                return $row['label'];
            }
        }

        return $value;
    }

    public static function movementTypeLabel(string $value): string
    {
        return match ($value) {
            'in' => 'Entrada',
            'out' => 'Salida / consumo',
            'adjustment' => 'Ajuste',
            'consignment' => 'Salida a consigna',
            'consignment_return' => 'Devolución de consigna',
            'write_off' => 'Baja / merma',
            default => $value,
        };
    }

    public static function usageTypeLabel(string $value): string
    {
        return match ($value) {
            'out' => 'Consumo en campo',
            'consignment' => 'Consigna',
            'write_off' => 'Baja / merma',
            default => $value,
        };
    }
}
