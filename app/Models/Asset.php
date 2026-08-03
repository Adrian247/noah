<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Support\Predictive\EquipmentClass;
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

    public function shiftLogs(): HasMany
    {
        return $this->hasMany(EquipmentShiftLog::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(EquipmentEvent::class);
    }

    public function failures(): HasMany
    {
        return $this->hasMany(EquipmentFailure::class);
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(EquipmentWorkOrder::class);
    }

    public function componentReplacements(): HasMany
    {
        return $this->hasMany(EquipmentComponentReplacement::class);
    }

    public function measurements(): HasMany
    {
        return $this->hasMany(EquipmentMeasurement::class);
    }

    public function failurePredictions(): HasMany
    {
        return $this->hasMany(FailurePrediction::class);
    }

    /**
     * Clase funcional canónica del equipo (SCOOPTRAM, CAMION_BAJO_PERFIL, QUEBRADORA…).
     *
     * Se toma de `metadata.equipment_class` y, si falta, del prefijo del tag (SS-305 → SCOOPTRAM).
     */
    public function equipmentClass(): ?string
    {
        $fromMetadata = $this->metadata['equipment_class'] ?? null;
        if (is_string($fromMetadata) && trim($fromMetadata) !== '') {
            return EquipmentClass::canonical($fromMetadata);
        }

        if (preg_match('/^([A-Za-z]{2,})[-_]?\d/', (string) $this->tag, $m) === 1) {
            return EquipmentClass::canonical($m[1]);
        }

        return null;
    }

    public function activeClientAssignment(): HasOne
    {
        return $this->hasOne(AssetClientAssignment::class)->whereNull('unassigned_at')->latestOfMany('assigned_at');
    }
}
