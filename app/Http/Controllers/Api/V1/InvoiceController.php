<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\InvoiceStatus;
use App\Enums\MembershipRole;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController extends Controller
{
    public function index(): JsonResponse
    {
        $invoices = Invoice::query()
            ->with(['lines', 'routine'])
            ->orderByDesc('id')
            ->paginate(15);

        return response()->json($invoices);
    }

    public function show(Invoice $invoice): JsonResponse
    {
        return response()->json(['data' => $invoice->load(['lines', 'routine'])]);
    }

    public function issue(Request $request, Invoice $invoice, AuditLogger $audit): JsonResponse
    {
        $this->authorizeBilling($request);

        if ($invoice->status !== InvoiceStatus::Draft) {
            return response()->json(['message' => 'Only drafts can be issued.'], 422);
        }

        $invoice->update([
            'status' => InvoiceStatus::Issued,
            'number' => $invoice->number ?? 'INV-'.str_pad((string) $invoice->id, 6, '0', STR_PAD_LEFT),
            'issued_at' => now(),
        ]);

        $invoice->routine?->update(['status' => \App\Enums\RoutineStatus::Invoiced]);

        $audit->fromRequest($request, 'invoice.issued', Invoice::class, $invoice->id, [
            'number' => $invoice->number,
        ]);

        return response()->json(['data' => $invoice->fresh('lines')]);
    }

    private function authorizeBilling(Request $request): void
    {
        $membership = $request->attributes->get('membership');
        $role = $membership->role;
        $roleValue = $role instanceof MembershipRole ? $role->value : (string) $role;

        if (! in_array($roleValue, [MembershipRole::Administrator->value, MembershipRole::Billing->value], true)) {
            abort(403, 'Billing role required.');
        }
    }
}
