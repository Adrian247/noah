<?php

namespace App\Enums;

enum FormUsage: string
{
    case Service = 'service';
    case Article = 'article';
    case Inventory = 'inventory';

    /** @deprecated use Service */
    case Routine = 'routine';
    /** @deprecated use Article */
    case Equipment = 'equipment';
    /** @deprecated use Inventory */
    case Supply = 'supply';

    public function label(): string
    {
        return match ($this) {
            self::Service, self::Routine => 'Servicio',
            self::Article, self::Equipment => 'Artículo',
            self::Inventory, self::Supply => 'Inventario',
        };
    }

    public function canonical(): self
    {
        return match ($this) {
            self::Routine => self::Service,
            self::Equipment => self::Article,
            self::Supply => self::Inventory,
            default => $this,
        };
    }

    public static function tryFromLoose(?string $value): ?self
    {
        if ($value === null || $value === '') {
            return null;
        }

        $case = self::tryFrom($value);
        if ($case !== null) {
            return $case->canonical();
        }

        return null;
    }
}
