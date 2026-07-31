<?php

namespace App\Support;

final class DashboardWidgets
{
    /**
     * @return list<array{id: string, label: string}>
     */
    public static function catalog(): array
    {
        return [
            ['id' => 'operations', 'label' => 'Operaciones'],
            ['id' => 'catalog', 'label' => 'Catálogo'],
            ['id' => 'inventory', 'label' => 'Inventario'],
            ['id' => 'design', 'label' => 'Diseño'],
            ['id' => 'activity', 'label' => 'Actividad reciente'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function defaultLayout(): array
    {
        return array_column(self::catalog(), 'id');
    }
}
