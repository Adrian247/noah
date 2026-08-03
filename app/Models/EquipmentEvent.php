<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Evento o alarma reportada por el control de la máquina (PLC, telemetría OEM).
 *
 * Convención de severidad observada en controles Metso IC: `A` alarma, `W` advertencia,
 * `M` mensaje de estado. Se normaliza al ingerir.
 */
class EquipmentEvent extends Model
{
    use BelongsToCompany;

    public const SEVERITY_ALARM = 'alarm';

    public const SEVERITY_WARNING = 'warning';

    public const SEVERITY_MESSAGE = 'message';

    protected $fillable = [
        'company_id',
        'asset_id',
        'occurred_at',
        'code',
        'name',
        'severity',
        'occurrences',
        'source',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'occurrences' => 'integer',
            'payload' => 'array',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public static function severityFromCode(string $code): string
    {
        return match (strtoupper(substr(trim($code), 0, 1))) {
            'A' => self::SEVERITY_ALARM,
            'W' => self::SEVERITY_WARNING,
            default => self::SEVERITY_MESSAGE,
        };
    }
}
