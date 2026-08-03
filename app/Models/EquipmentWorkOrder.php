<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Orden de trabajo planeada por un CMMS externo (p. ej. SAP PM).
 *
 * El incumplimiento del preventivo es una de las variables más predictivas de falla.
 */
class EquipmentWorkOrder extends Model
{
    use BelongsToCompany;

    public const STATUS_PLANNED = 'planned';

    public const STATUS_EXECUTED = 'executed';

    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'company_id',
        'asset_id',
        'order_number',
        'description',
        'work_center',
        'location_code',
        'planned_for',
        'executed_on',
        'status',
        'skip_reason',
        'supervisor',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'planned_for' => 'date',
            'executed_on' => 'date',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
