<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneratedReport extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'routine_id',
        'routine_execution_id',
        'report_template_version_id',
        'status',
        'disk',
        'path',
        'mime',
        'error_message',
    ];

    public function routine(): BelongsTo
    {
        return $this->belongsTo(Routine::class);
    }

    public function execution(): BelongsTo
    {
        return $this->belongsTo(RoutineExecution::class, 'routine_execution_id');
    }

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(ReportTemplateVersion::class, 'report_template_version_id');
    }
}
