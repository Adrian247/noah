<?php

namespace App\Services\Audit;

use App\Models\AuditEntry;
use App\Support\AuditCorrelation;
use Illuminate\Http\Request;

class AuditLogger
{
    public function record(
        ?int $companyId,
        ?int $actorUserId,
        string $action,
        ?string $subjectType = null,
        ?int $subjectId = null,
        array $metadata = [],
        ?string $ip = null,
        ?string $correlationId = null,
    ): AuditEntry {
        return AuditEntry::query()->create([
            'company_id' => $companyId,
            'correlation_id' => $correlationId ?? AuditCorrelation::get(),
            'actor_user_id' => $actorUserId,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'metadata' => $metadata === [] ? null : $metadata,
            'ip' => $ip,
            'occurred_at' => now(),
        ]);
    }

    public function fromRequest(Request $request, string $action, ?string $subjectType = null, ?int $subjectId = null, array $metadata = []): AuditEntry
    {
        $company = app(\App\Support\CurrentCompany::class)->id();

        return $this->record(
            $company,
            $request->user()?->id,
            $action,
            $subjectType,
            $subjectId,
            $metadata,
            $request->ip(),
            AuditCorrelation::get(),
        );
    }
}
