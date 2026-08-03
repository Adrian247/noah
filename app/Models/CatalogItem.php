<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogItem extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'equipment_type_id',
        'code',
        'name',
        'manufacturer',
        'oem_equipment_model_id',
        'specifications',
    ];

    protected function casts(): array
    {
        return [
            'specifications' => 'array',
        ];
    }

    public function equipmentType(): BelongsTo
    {
        return $this->belongsTo(EquipmentType::class);
    }

    public function oemEquipmentModel(): BelongsTo
    {
        return $this->belongsTo(OemEquipmentModel::class, 'oem_equipment_model_id');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }
}
