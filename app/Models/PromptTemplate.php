<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromptTemplate extends Model
{
    protected $fillable = [
        'company_id',
        'slug',
        'version',
        'provider',
        'model',
        'temperature',
        'system_prompt',
        'user_template',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'temperature' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public static function activeFor(string $slug, ?int $companyId): ?self
    {
        return static::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->where(function ($q) use ($companyId) {
                $q->whereNull('company_id');
                if ($companyId !== null) {
                    $q->orWhere('company_id', $companyId);
                }
            })
            ->orderByRaw('company_id is null')
            ->orderByDesc('version')
            ->first();
    }
}
