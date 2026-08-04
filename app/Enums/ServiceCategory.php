<?php

namespace App\Enums;

enum ServiceCategory: string
{
    case Installation = 'installation';
    case Manufacturing = 'manufacturing';
    case Maintenance = 'maintenance';

    public function label(): string
    {
        return match ($this) {
            self::Installation => 'Instalación',
            self::Manufacturing => 'Fabricación',
            self::Maintenance => 'Mantenimiento',
        };
    }

    public function requiresClientArticle(): bool
    {
        return $this === self::Maintenance;
    }

    public function requiresClient(): bool
    {
        return $this === self::Installation || $this === self::Manufacturing;
    }

    public function deductsInventoryOnManufacturing(): bool
    {
        return $this === self::Manufacturing;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
