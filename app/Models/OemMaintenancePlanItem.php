<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OemMaintenancePlanItem extends Model
{
    protected $fillable = [
        'oem_maintenance_plan_id',
        'interval_hours',
        'task',
        'system',
        'detail',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'interval_hours' => 'integer',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(OemMaintenancePlan::class, 'oem_maintenance_plan_id');
    }
}
