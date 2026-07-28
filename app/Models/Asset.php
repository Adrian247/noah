<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Asset extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'site_id',
        'catalog_item_id',
        'tag',
        'serial_number',
        'location_label',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class);
    }

    public function routines(): HasMany
    {
        return $this->hasMany(Routine::class);
    }

    public function clientAssignments(): HasMany
    {
        return $this->hasMany(AssetClientAssignment::class);
    }

    public function activeClientAssignment(): HasOne
    {
        return $this->hasOne(AssetClientAssignment::class)->whereNull('unassigned_at')->latestOfMany('assigned_at');
    }
}
