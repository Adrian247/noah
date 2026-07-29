<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\InvoiceEvidenceKind;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoiceEvidence;
use App\Services\Billing\InvoiceEvidenceService;
use App\Services\Identity\CompanyAuthorizationService;
use App\Support\CurrentCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceEvidenceController extends Controller
{
    public function __construct(
        private readonly CompanyAuthorizationService $authorization,
        private readonly InvoiceEvidenceService $evidences,
    ) {}

    public function store(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorizePermission($request, 'billing.draft.edit');

        $kindValue = $request->validate([
            'kind' => ['required', 'string', Rule::in(array_column(InvoiceEvidenceKind::cases(), 'value'))],
        ])['kind'];

        $kind = InvoiceEvidenceKind::from($kindValue);

        if ($kind === InvoiceEvidenceKind::RoutineReport) {
            $validated = $request->validate([
                'generated_report_id' => ['required', 'integer', 'exists:generated_reports,id'],
            ]);

            $evidence = $this->evidences->attachRoutineReport(
                $invoice,
                (int) $validated['generated_report_id'],
                $request->user(),
            );

            return response()->json(['data' => $this->evidences->toApiArray($evidence)], 201);
        }

        $maxKb = $this->evidences->maxSizeKb();
        $mimes = array_map(
            fn (string $mime) => match ($mime) {
                'image/jpeg' => 'jpeg,jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'application/pdf' => 'pdf',
                'application/xml', 'text/xml' => 'xml',
                'application/msword' => 'doc',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
                'application/vnd.ms-excel' => 'xls',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
                default => null,
            },
            $this->evidences->allowedMimesFor($kind),
        );
        $mimes = array_values(array_filter(array_unique($mimes)));

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:'.$maxKb, 'mimes:'.implode(',', $mimes)],
        ]);

        $evidence = $this->evidences->store(
            $invoice,
            $validated['file'],
            $kind,
            $request->user(),
        );

        return response()->json(['data' => $this->evidences->toApiArray($evidence)], 201);
    }

    public function destroy(Request $request, Invoice $invoice, InvoiceEvidence $evidence): JsonResponse
    {
        $this->authorizePermission($request, 'billing.draft.edit');

        if ($evidence->invoice_id !== $invoice->id) {
            abort(404);
        }

        $this->evidences->delete($evidence);

        return response()->json(null, 204);
    }

    public function download(Request $request, Invoice $invoice, InvoiceEvidence $evidence): StreamedResponse|JsonResponse
    {
        $this->authorizePermission($request, 'billing.draft');

        if ($evidence->invoice_id !== $invoice->id) {
            abort(404);
        }

        return $this->evidences->stream($evidence);
    }

    private function authorizePermission(Request $request, string $permission): void
    {
        $user = $request->user();
        $companyId = app(CurrentCompany::class)->id();

        if ($user === null || ! $this->authorization->userHasPermission($user, $companyId, $permission)) {
            abort(403, 'Insufficient permissions for billing action.');
        }
    }
}
