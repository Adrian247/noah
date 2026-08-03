<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un turno de operación de un activo: horas por estado, horómetro, consumibles y producción.
 */
class EquipmentShiftLog extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'asset_id',
        'logged_on',
        'shift',
        'scheduled_hours',
        'worked_hours',
        'standby_hours',
        'preventive_hours',
        'corrective_hours',
        'operative_fail_hours',
        'no_operator_hours',
        'availability',
        'utilization',
        'hour_meter_start',
        'hour_meter_end',
        'diesel_liters',
        'oil_liters',
        'coolant_liters',
        'production',
        'location_label',
        'equipment_status',
        'failure_text',
        'comments',
        'source',
        'external_ref',
    ];

    protected function casts(): array
    {
        return [
            'logged_on' => 'date',
            'scheduled_hours' => 'float',
            'worked_hours' => 'float',
            'standby_hours' => 'float',
            'preventive_hours' => 'float',
            'corrective_hours' => 'float',
            'operative_fail_hours' => 'float',
            'no_operator_hours' => 'float',
            'availability' => 'float',
            'utilization' => 'float',
            'hour_meter_start' => 'float',
            'hour_meter_end' => 'float',
            'diesel_liters' => 'float',
            'oil_liters' => 'float',
            'coolant_liters' => 'float',
            'production' => 'array',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /** Horas de indisponibilidad por mantenimiento (preventivo + correctivo). */
    public function maintenanceHours(): float
    {
        return round($this->preventive_hours + $this->corrective_hours, 2);
    }

    public function hadUnplannedStop(): bool
    {
        return $this->corrective_hours > 0 || $this->operative_fail_hours > 0;
    }
}
