<?php

namespace App\Enums;

/**
 * Familias de algoritmo predictivo de plataforma.
 */
enum PredictiveAlgorithmKind: string
{
    case Maintenance = 'maintenance_hazard_v2';
    case Manufacturing = 'manufacturing_demand_v1';
    case Inventory = 'inventory_demand_v1';

    /** Compatibilidad con versiones seed/legacy. */
    public const LEGACY_MAINTENANCE = 'hazard_routines_v1';

    public function label(): string
    {
        return match ($this) {
            self::Maintenance => 'Mantenimiento (servicios)',
            self::Manufacturing => 'Manufactura (servicios)',
            self::Inventory => 'Inventario (demanda de artículos)',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Maintenance => 'Riesgo de falla / necesidad de servicio de mantenimiento sobre artículos (activos).',
            self::Manufacturing => 'Demanda de servicios de fabricación/instalación por cliente y tipo de servicio.',
            self::Inventory => 'Probabilidad de que un cliente final solicite compra de artículos del catálogo.',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function tryFromFlexible(?string $value): ?self
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value === self::LEGACY_MAINTENANCE || $value === 'maintenance') {
            return self::Maintenance;
        }

        return self::tryFrom($value);
    }
}
