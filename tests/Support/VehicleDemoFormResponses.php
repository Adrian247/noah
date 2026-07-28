<?php

namespace Tests\Support;

/** Respuestas mínimas válidas para el formulario normalizado `inspeccion-vehiculo-v1` (NoahDemoSeeder). */
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
            'motor_estado' => 'operativo',
            'aceite_motor' => 'operativo',
            'filtro_aceite_reemplazado' => 'si',
            'frenos_delanteros' => 'operativo',
            'frenos_traseros' => 'operativo',
            'liquido_frenos' => 'operativo',
            'filtro_aire' => 'operativo',
            'filtro_habitaculo' => 'operativo',
            'suspension' => 'operativo',
            'direccion' => 'operativo',
            'bateria' => 'operativo',
            'luces' => 'operativo',
        ];
    }
}
