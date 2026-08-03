<?php

namespace App\Enums;

enum ServiceLine: string
{
    case Maintenance = 'maintenance';
    case Fabrication = 'fabrication';
    case Supply = 'supply';

    public function label(): string
    {
        return match ($this) {
            self::Maintenance => 'Mantenimiento',
            self::Fabrication => 'Manufactura',
            self::Supply => 'Suministro',
        };
    }

    public function requiresAsset(): bool
    {
        return $this === self::Maintenance;
    }

    public function requiresClient(): bool
    {
        return $this === self::Fabrication || $this === self::Supply;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
