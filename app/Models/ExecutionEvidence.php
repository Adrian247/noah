<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExecutionEvidence extends Model
{
    use BelongsToCompany;

    protected $table = 'execution_evidences';

    protected $fillable = [
        'company_id',
        'routine_execution_id',
        'disk',
        'path',
        'mime',
        'original_name',
        'size_bytes',
    ];

    public function execution(): BelongsTo
    {
        return $this->belongsTo(RoutineExecution::class, 'routine_execution_id');
    }
}
