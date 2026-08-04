<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Versión del algoritmo predictivo (plataforma). Semver + ciclo draft → published → archived.
 * Solo las publicadas son seleccionables por las empresas.
 */
class PredictiveAlgorithmVersion extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'semver',
        'status',
        'kind',
        'notes',
        'metrics',
        'calibration',
        'regression_report',
        'training_summary',
        'artifact_path',
        'created_by',
        'published_by',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'metrics' => 'array',
            'calibration' => 'array',
            'regression_report' => 'array',
            'training_summary' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class, 'predictive_algorithm_version_id');
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }
}
