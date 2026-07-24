<?php

namespace App\Models;

use App\Enums\RoutineStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Routine extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'site_id',
        'asset_id',
        'routine_type_id',
        'assigned_to',
        'status',
        'scheduled_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => RoutineStatus::class,
            'scheduled_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function routineType(): BelongsTo
    {
        return $this->belongsTo(RoutineType::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function generatedReports(): HasMany
    {
        return $this->hasMany(GeneratedReport::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function workflowInstance(): HasOne
    {
        return $this->hasOne(WorkflowInstance::class);
    }

    public function executions(): HasMany
    {
        return $this->hasMany(RoutineExecution::class);
    }

    public function latestExecution(): HasOne
    {
        return $this->hasOne(RoutineExecution::class)->latestOfMany();
    }
}
