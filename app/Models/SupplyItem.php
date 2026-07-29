<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplyItem extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'supply_type_id',
        'supplier_id',
        'sku',
        'name',
        'sector',
        'material_kind',
        'unit',
        'standard_cost',
        'quantity_on_hand',
        'min_stock',
        'storage_location',
        'notes',
        'is_active',
        'specifications',
    ];

    protected function casts(): array
    {
        return [
            'standard_cost' => 'decimal:4',
            'quantity_on_hand' => 'decimal:4',
            'min_stock' => 'decimal:4',
            'is_active' => 'boolean',
            'specifications' => 'array',
        ];
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function consumptions(): HasMany
    {
        return $this->hasMany(RoutineConsumption::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function supplyType(): BelongsTo
    {
        return $this->belongsTo(SupplyType::class);
    }
}
