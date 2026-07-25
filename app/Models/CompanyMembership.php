<?php

namespace App\Models;

use App\Enums\MembershipRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyMembership extends Model
{
    protected $fillable = ['company_id', 'user_id', 'role', 'is_active', 'module_access'];

    protected function casts(): array
    {
        return [
            'role' => MembershipRole::class,
            'is_active' => 'boolean',
            'module_access' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
