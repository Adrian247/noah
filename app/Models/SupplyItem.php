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
        'unit',
        'standard_cost',
        'specifications',
    ];

    protected function casts(): array
    {
        return [
            'standard_cost' => 'decimal:4',
            'specifications' => 'array',
        ];
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
