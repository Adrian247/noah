<?php

namespace Tests\Support;

/** Respuestas mínimas válidas para el formulario demo `revision-mayor-vehiculo-premium` (PhoenixDemoSeeder). */
final class VehicleDemoFormResponses
{
    /**
     * @return array<string, mixed>
     */
    public static function required(): array
    {
        return [
            'kilometraje' => 45820,
            'nivel_combustible' => 'medio',
            'frenos' => 'operativo',
            'filtros' => 'operativo',
            'aceite' => 'operativo',
            'bateria' => 'operativo',
            'luces' => 'operativo',
            'fusibles' => 'operativo',
        ];
    }
}
