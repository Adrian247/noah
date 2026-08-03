<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo de referencia global de modelos OEM (Epiroc, Sandvik, Metso/Outotec).
 *
 * No pertenece a una empresa: se consulta al dar de alta equipos y para inferir el plan
 * de mantenimiento por intervalos. `verified` distingue dato confirmado en fuente OEM.
 */
class OemEquipmentModel extends Model
{
    protected $fillable = [
        'manufacturer',
        'family',
        'model',
        'equipment_class',
        'application',
        'description',
        'specifications',
        'source_url',
        'verified',
    ];

    protected function casts(): array
    {
        return [
            'specifications' => 'array',
            'verified' => 'boolean',
        ];
    }
}
