<?php

namespace App\Services\Billing;

use App\Enums\InvoiceEvidenceKind;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Services\Billing\InvoiceEvidenceService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FiscalIssuanceService
{
    public function __construct(
        private readonly FiscalAdapterResolver $resolver,
        private readonly InvoiceEvidenceService $evidences,
    ) {}

    /**
     * @return array{ok: bool, error?: string}
     */
    public function stampBeforeIssue(Invoice $invoice): array
    {
        $invoice->loadMissing('company');
        $company = $invoice->company;
        if ($company === null || ! $company->fiscal_enabled) {
            return ['ok' => true];
        }

        $adapter = $this->resolver->resolve($company);
        if ($adapter === null) {
            return ['ok' => false, 'error' => 'Proveedor fiscal no configurado.'];
        }

        $result = $adapter->issue($invoice);
        if (! $result->success) {
            $invoice->update([
                'status' => InvoiceStatus::FiscalError,
                'fiscal_error' => $result->error,
            ]);

            return ['ok' => false, 'error' => $result->error ?? 'Error fiscal desconocido.'];
        }

        if ($result->xmlContents !== null) {
            $this->attachCfdiEvidence($invoice, $result->xmlContents);
        }

        $invoice->update([
            'fiscal_uuid' => $result->uuid,
            'fiscal_series' => $result->series,
            'fiscal_folio' => $result->folio,
            'fiscal_issued_at' => now(),
            'fiscal_error' => null,
        ]);

        return ['ok' => true];
    }

    private function attachCfdiEvidence(Invoice $invoice, string $xmlContents): void
    {
        $disk = Storage::disk(config('phoenix.billing.evidence_disk', 'local'));
        $path = 'fiscal/'.$invoice->company_id.'/'.$invoice->id.'/'.Str::uuid().'.xml';
        $disk->put($path, $xmlContents);

        $tmp = tempnam(sys_get_temp_dir(), 'cfdi');
        if ($tmp === false) {
            return;
        }

        file_put_contents($tmp, $xmlContents);
        $uploaded = new UploadedFile($tmp, 'cfdi.xml', 'application/xml', null, true);

        $this->evidences->store($invoice, $uploaded, InvoiceEvidenceKind::SatCfdi, null);
    }
}
