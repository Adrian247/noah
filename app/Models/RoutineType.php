<?php

namespace App\Models;

use App\Enums\ServiceCategory;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoutineType extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'name',
        'slug',
        'service_category',
        'form_version_id',
        'report_template_version_id',
        'workflow_definition_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'service_category' => ServiceCategory::class,
        ];
    }

    public function formVersion(): BelongsTo
    {
        return $this->belongsTo(FormVersion::class);
    }

    public function reportTemplateVersion(): BelongsTo
    {
        return $this->belongsTo(ReportTemplateVersion::class);
    }

    public function workflowDefinition(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class);
    }

    public function routines(): HasMany
    {
        return $this->hasMany(Routine::class);
    }
}
