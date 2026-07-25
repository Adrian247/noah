<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoutineExecution extends Model
{
    protected $fillable = [
        'routine_id',
        'performed_by',
        'responses',
        'technician_comments',
        'corrected_comments',
        'duration_minutes',
        'status',
        'submitted_at',
        'validated_at',
        'validated_by',
        'rejection_reason',
        'rejected_at',
        'rejected_by',
    ];

    protected function casts(): array
    {
        return [
            'responses' => 'array',
            'submitted_at' => 'datetime',
            'validated_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function routine(): BelongsTo
    {
        return $this->belongsTo(Routine::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function consumptions(): HasMany
    {
        return $this->hasMany(RoutineConsumption::class);
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(ExecutionEvidence::class, 'routine_execution_id');
    }
}
