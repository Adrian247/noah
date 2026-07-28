<?php

namespace App\Enums;

enum FormUsage: string
{
    case Routine = 'routine';
    case Equipment = 'equipment';
    case Supply = 'supply';

    public function label(): string
    {
        return match ($this) {
            self::Routine => 'Rutina',
            self::Equipment => 'Equipo',
            self::Supply => 'Insumo',
        };
    }
}
