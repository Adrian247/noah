<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected $fillable = [
        'name',
        'legal_name',
        'tax_id',
        'currency',
        'timezone',
        'is_active',
        'billing_labor_rate_per_hour',
        'billing_tax_rate',
        'form_max_image_size_kb',
        'form_allowed_image_mimes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'billing_labor_rate_per_hour' => 'decimal:2',
            'billing_tax_rate' => 'decimal:4',
            'form_allowed_image_mimes' => 'array',
        ];
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(CompanyMembership::class);
    }
}
