<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'supply_item_id',
        'routine_id',
        'routine_execution_id',
        'movement_type',
        'quantity',
        'reference',
        'notes',
        'recorded_by',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'occurred_at' => 'datetime',
        ];
    }

    public function supplyItem(): BelongsTo
    {
        return $this->belongsTo(SupplyItem::class);
    }

    public function routine(): BelongsTo
    {
        return $this->belongsTo(Routine::class);
    }

    public function routineExecution(): BelongsTo
    {
        return $this->belongsTo(RoutineExecution::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
