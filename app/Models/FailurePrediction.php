<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FailurePrediction extends Model
{
    use BelongsToCompany;

    public const RISK_LOW = 'low';

    public const RISK_MEDIUM = 'medium';

    public const RISK_HIGH = 'high';

    public const RISK_CRITICAL = 'critical';

    protected $fillable = [
        'company_id',
        'asset_id',
        'failure_mode_id',
        'predicted_on',
        'horizon_days',
        'probability',
        'expected_failures',
        'risk_level',
        'expected_downtime_hours',
        'drivers',
        'features',
        'model_kind',
        'model_version',
        'outcome_failed',
        'outcome_evaluated_at',
    ];

    protected function casts(): array
    {
        return [
            'predicted_on' => 'date',
            'horizon_days' => 'integer',
            'probability' => 'float',
            'expected_failures' => 'float',
            'expected_downtime_hours' => 'float',
            'drivers' => 'array',
            'features' => 'array',
            'outcome_failed' => 'boolean',
            'outcome_evaluated_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function failureMode(): BelongsTo
    {
        return $this->belongsTo(FailureMode::class);
    }

    /**
     * El nivel se decide por número esperado de fallas y no por probabilidad.
     *
     * En flotas de alta tasa `P(al menos una falla)` se satura cerca de 1 y deja de distinguir
     * entre un equipo delicado y uno que está a punto de quedarse en el taller; el valor esperado
     * sigue ordenando. `critical` es "se espera al menos una falla dentro de la ventana".
     */
    public static function riskLevelFor(float $expectedFailures): string
    {
        return match (true) {
            $expectedFailures >= 1.0 => self::RISK_CRITICAL,
            $expectedFailures >= 0.40 => self::RISK_HIGH,
            $expectedFailures >= 0.15 => self::RISK_MEDIUM,
            default => self::RISK_LOW,
        };
    }

    /** Inversa de `p = 1 - exp(-E)`, para reconstruir el valor esperado desde una probabilidad. */
    public static function expectedFailuresFromProbability(float $probability): float
    {
        $bounded = min(0.999, max(0.0, $probability));

        return round(-log(1 - $bounded), 4);
    }

    /** Umbral de probabilidad a partir del cual la predicción se considera una alerta. */
    public const ALERT_PROBABILITY = 0.45;
}
