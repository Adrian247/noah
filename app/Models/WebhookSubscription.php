<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class WebhookSubscription extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'name',
        'url',
        'secret',
        'events',
        'is_active',
        'last_delivered_at',
        'last_status',
    ];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'is_active' => 'boolean',
            'last_delivered_at' => 'datetime',
        ];
    }

    protected $hidden = [
        'secret',
    ];
}
