<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Support\Predictive\EquipmentClass;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FailureMode extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'system',
        'description',
        'equipment_classes',
        'severity',
        'typical_symptoms',
        'typical_causes',
        'monitoring_signals',
        'precursor_event_codes',
        'mean_repair_hours',
        'text_patterns',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'equipment_classes' => 'array',
            'monitoring_signals' => 'array',
            'precursor_event_codes' => 'array',
            'text_patterns' => 'array',
            'mean_repair_hours' => 'float',
        ];
    }

    public function failures(): HasMany
    {
        return $this->hasMany(EquipmentFailure::class);
    }

    public function predictions(): HasMany
    {
        return $this->hasMany(FailurePrediction::class);
    }

    /** Peso relativo del modo para ordenar riesgo cuando dos probabilidades empatan. */
    public function severityWeight(): float
    {
        return match ($this->severity) {
            'critical' => 1.0,
            'high' => 0.75,
            'medium' => 0.5,
            default => 0.25,
        };
    }

    public function appliesToClass(?string $equipmentClass): bool
    {
        $classes = $this->equipment_classes;
        if ($equipmentClass === null || ! is_array($classes) || $classes === []) {
            return true;
        }

        return EquipmentClass::inList($equipmentClass, $classes);
    }
}
