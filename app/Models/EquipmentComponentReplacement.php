<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Reemplazo de un componente mayor: base para vida remanente por componente y no por máquina.
 */
class EquipmentComponentReplacement extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'asset_id',
        'component',
        'description',
        'replaced_at',
        'hour_meter',
        'expected_life_hours',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'replaced_at' => 'datetime',
            'hour_meter' => 'float',
            'expected_life_hours' => 'float',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /** Fracción de vida consumida (0..n) dado el horómetro actual; null si falta información. */
    public function lifeUsedFraction(?float $currentHourMeter): ?float
    {
        if ($currentHourMeter === null || $this->hour_meter === null) {
            return null;
        }
        if ($this->expected_life_hours === null || $this->expected_life_hours <= 0) {
            return null;
        }

        return round(max(0.0, $currentHourMeter - $this->hour_meter) / $this->expected_life_hours, 4);
    }
}
