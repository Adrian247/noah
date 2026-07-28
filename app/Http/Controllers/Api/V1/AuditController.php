<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MembershipRole;
use App\Http\Controllers\Controller;
use App\Models\AuditEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeViewer($request);

        $entries = AuditEntry::query()
            ->with('actor:id,name,email')
            ->where('company_id', app(\App\Support\CurrentCompany::class)->id())
            ->when($request->query('action'), fn ($q, $action) => $q->where('action', $action))
            ->when($request->query('correlation_id'), fn ($q, $id) => $q->where('correlation_id', $id))
            ->orderByDesc('occurred_at')
            ->paginate((int) $request->query('per_page', 25));

        return response()->json($entries);
    }

    private function authorizeViewer(Request $request): void
    {
        $membership = $request->attributes->get('membership');
        $role = $membership->role;
        $roleValue = $role instanceof MembershipRole ? $role->value : (string) $role;

        if (! in_array($roleValue, [
            MembershipRole::Administrator->value,
            MembershipRole::Auditor->value,
            MembershipRole::Supervisor->value,
        ], true)) {
            abort(403, 'Audit view not allowed.');
        }
    }
}
