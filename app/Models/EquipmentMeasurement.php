<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Medición de condición genérica (vibración, análisis de aceite, temperatura, horómetro).
 */
class EquipmentMeasurement extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'asset_id',
        'metric',
        'value',
        'unit',
        'measured_at',
        'threshold_warning',
        'threshold_critical',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'float',
            'measured_at' => 'datetime',
            'threshold_warning' => 'float',
            'threshold_critical' => 'float',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /** normal | warning | critical, según los umbrales vigentes al momento de medir. */
    public function conditionLevel(): string
    {
        if ($this->threshold_critical !== null && $this->value >= $this->threshold_critical) {
            return 'critical';
        }
        if ($this->threshold_warning !== null && $this->value >= $this->threshold_warning) {
            return 'warning';
        }

        return 'normal';
    }
}
