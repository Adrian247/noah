<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplyType extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'description',
        'default_form_definition_id',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function supplyItems(): HasMany
    {
        return $this->hasMany(SupplyItem::class);
    }

    public function defaultFormDefinition(): BelongsTo
    {
        return $this->belongsTo(FormDefinition::class, 'default_form_definition_id');
    }
}
