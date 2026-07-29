<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoutineConsumption extends Model
{
    protected $fillable = [
        'routine_execution_id',
        'supply_item_id',
        'usage_type',
        'inventory_movement_id',
        'quantity',
        'unit_cost',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:4',
        ];
    }

    public function execution(): BelongsTo
    {
        return $this->belongsTo(RoutineExecution::class, 'routine_execution_id');
    }

    public function supplyItem(): BelongsTo
    {
        return $this->belongsTo(SupplyItem::class);
    }
}
