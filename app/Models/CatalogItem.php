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
        'is_system_template',
        'source_system_catalog_item_id',
        'import_generation',
        'equipment_type_id',
        'code',
        'name',
        'image_path',
        'is_detached_copy',
        'manufacturer',
        'oem_equipment_model_id',
        'specifications',
    ];

    protected function casts(): array
    {
        return [
            'specifications' => 'array',
            'is_system_template' => 'boolean',
            'is_detached_copy' => 'boolean',
            'import_generation' => 'integer',
        ];
    }

    public function sourceSystemCatalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'source_system_catalog_item_id');
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
