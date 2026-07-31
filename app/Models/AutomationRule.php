<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class AutomationRule extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'name',
        'trigger_type',
        'conditions',
        'actions',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'actions' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
