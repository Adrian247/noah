<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiInvocation extends Model
{
    protected $fillable = [
        'company_id',
        'user_id',
        'use_case',
        'provider',
        'model',
        'input_tokens',
        'output_tokens',
        'input_excerpt',
        'output_excerpt',
        'status',
        'tool_calls',
    ];

    protected function casts(): array
    {
        return [
            'tool_calls' => 'array',
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
