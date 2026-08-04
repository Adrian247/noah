<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PredictiveTrainingDocument extends Model
{
    public const STATUS_READY = 'ready';

    public const STATUS_INVALID = 'invalid';

    public const STATUS_CONSUMED = 'consumed';

    protected $fillable = [
        'kind',
        'name',
        'original_filename',
        'mime',
        'disk',
        'path',
        'byte_size',
        'record_count',
        'status',
        'validation_errors',
        'meta',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'validation_errors' => 'array',
            'meta' => 'array',
            'byte_size' => 'integer',
            'record_count' => 'integer',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY;
    }
}
