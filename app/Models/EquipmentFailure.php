<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentFailure extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'asset_id',
        'failure_mode_id',
        'started_at',
        'ended_at',
        'downtime_hours',
        'maintenance_type',
        'reported_text',
        'hour_meter',
        'cost',
        'source',
        'external_ref',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'downtime_hours' => 'float',
            'hour_meter' => 'float',
            'cost' => 'float',
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
}
