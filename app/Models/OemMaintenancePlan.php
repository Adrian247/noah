<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OemMaintenancePlan extends Model
{
    protected $fillable = [
        'manufacturer',
        'equipment_class',
        'name',
        'notes',
        'source_url',
        'verified',
    ];

    protected function casts(): array
    {
        return [
            'verified' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OemMaintenancePlanItem::class);
    }

    /** @return list<int> Intervalos distintos en horas, ascendente. */
    public function intervals(): array
    {
        return $this->items()
            ->distinct()
            ->orderBy('interval_hours')
            ->pluck('interval_hours')
            ->map(fn ($h) => (int) $h)
            ->all();
    }
}
