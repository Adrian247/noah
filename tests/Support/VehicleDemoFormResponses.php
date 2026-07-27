<?php

namespace Tests\Support;

/** Respuestas mínimas válidas para el formulario demo revisión mayor vehículo (NoahDemoSeeder). */
final class VehicleDemoFormResponses
{
    /**
     * @return array<string, mixed>
     */
    public static function required(): array
    {
        return [
            'kilometraje' => 45820,
            'frenos' => 'operativo',
            'filtros' => 'operativo',
            'aceite' => 'operativo',
            'bateria' => 'operativo',
            'luces' => 'operativo',
            'fusibles' => 'operativo',
        ];
    }
}
