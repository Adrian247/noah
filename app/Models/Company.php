<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected $fillable = [
        'name',
        'legal_name',
        'logo_path',
        'tax_id',
        'currency',
        'timezone',
        'is_active',
        'billing_labor_rate_per_hour',
        'billing_tax_rate',
        'form_max_image_size_kb',
        'form_allowed_image_mimes',
        'mobile_require_app_lock',
        'mobile_allow_biometric_unlock',
        'ai_monthly_token_quota',
        'ai_monthly_vision_quota',
        'ai_enabled',
        'fiscal_enabled',
        'fiscal_provider',
        'fiscal_settings',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'mobile_require_app_lock' => 'boolean',
            'mobile_allow_biometric_unlock' => 'boolean',
            'billing_labor_rate_per_hour' => 'decimal:2',
            'billing_tax_rate' => 'decimal:4',
            'form_allowed_image_mimes' => 'array',
            'ai_monthly_token_quota' => 'integer',
            'ai_monthly_vision_quota' => 'integer',
            'ai_enabled' => 'boolean',
            'fiscal_enabled' => 'boolean',
            'fiscal_settings' => 'array',
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
