<?php

namespace App\Services\Billing;

use App\Enums\InvoiceEvidenceKind;
use App\Enums\InvoiceStatus;
use App\Models\GeneratedReport;
use App\Models\Invoice;
use App\Models\InvoiceEvidence;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvoiceEvidenceService
{
    /** @var list<string> */
    private const SUPPORTING_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    /** @var list<string> */
    private const SAT_MIMES = [
        'application/pdf',
        'application/xml',
        'text/xml',
    ];

    public function assertDraftEditable(Invoice $invoice): void
    {
        if ($invoice->status !== InvoiceStatus::Draft) {
            throw ValidationException::withMessages([
                'invoice' => ['Solo se pueden adjuntar evidencias en prefacturas (borrador).'],
            ]);
        }
    }

    /**
     * @return list<string>
     */
    public function allowedMimesFor(InvoiceEvidenceKind $kind): array
    {
        if ($kind === InvoiceEvidenceKind::SatCfdi) {
            return self::SAT_MIMES;
        }
        if ($kind === InvoiceEvidenceKind::RoutineReport) {
            return [];
        }

        return self::SUPPORTING_MIMES;
    }

    public function maxSizeKb(): int
    {
        return (int) config('phoenix.billing.evidence_max_kb', 10240);
    }

    public function store(Invoice $invoice, UploadedFile $file, InvoiceEvidenceKind $kind, ?User $user): InvoiceEvidence
    {
        $this->assertDraftEditable($invoice);

        $mime = (string) $file->getMimeType();
        if (! in_array($mime, $this->allowedMimesFor($kind), true)) {
            throw ValidationException::withMessages([
                'file' => ['Tipo de archivo no permitido para esta evidencia.'],
            ]);
        }

        if ($kind === InvoiceEvidenceKind::SatCfdi) {
            $invoice->evidences()
                ->where('kind', InvoiceEvidenceKind::SatCfdi)
                ->get()
                ->each(fn (InvoiceEvidence $existing) => $this->delete($existing));
        }

        $diskName = (string) config('phoenix.evidence.disk', 'evidence');
        $prefix = trim((string) config('phoenix.evidence.path_prefix'), '/');
        $ext = $file->getClientOriginalExtension() ?: 'bin';
        $path = $prefix.'/billing/invoices/'.$invoice->id.'/'.$kind->value.'/'.Str::uuid().'.'.$ext;

        $disk = Storage::disk($diskName);
        $dir = dirname($path);
        if (! $disk->exists($dir)) {
            $disk->makeDirectory($dir);
        }

        $stored = $disk->put($path, file_get_contents($file->getRealPath()));
        if ($stored === false) {
            throw ValidationException::withMessages([
                'file' => ['No se pudo guardar el archivo.'],
            ]);
        }

        return InvoiceEvidence::query()->create([
            'company_id' => $invoice->company_id,
            'invoice_id' => $invoice->id,
            'kind' => $kind,
            'disk' => $diskName,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $mime,
            'size_bytes' => (int) $file->getSize(),
            'uploaded_by' => $user?->id,
        ]);
    }

    public function attachRoutineReport(Invoice $invoice, int $generatedReportId, ?User $user): InvoiceEvidence
    {
        $this->assertDraftEditable($invoice);

        if ($invoice->routine_id === null) {
            throw ValidationException::withMessages([
                'generated_report_id' => ['La prefactura no tiene rutina vinculada.'],
            ]);
        }

        $report = GeneratedReport::query()
            ->where('company_id', $invoice->company_id)
            ->whereKey($generatedReportId)
            ->firstOrFail();

        if ((int) $report->routine_id !== (int) $invoice->routine_id) {
            throw ValidationException::withMessages([
                'generated_report_id' => ['El reporte debe pertenecer a la misma rutina que la prefactura.'],
            ]);
        }

        if ($report->status !== 'ready' || $report->path === null) {
            throw ValidationException::withMessages([
                'generated_report_id' => ['El reporte aún no está listo para adjuntar.'],
            ]);
        }

        $disk = Storage::disk($report->disk);
        if (! $disk->exists($report->path)) {
            throw ValidationException::withMessages([
                'generated_report_id' => ['No se encontró el archivo del reporte.'],
            ]);
        }

        $already = $invoice->evidences()
            ->where('kind', InvoiceEvidenceKind::RoutineReport)
            ->where('generated_report_id', $report->id)
            ->exists();

        if ($already) {
            throw ValidationException::withMessages([
                'generated_report_id' => ['Ese reporte ya está adjunto a esta prefactura.'],
            ]);
        }

        $size = (int) ($disk->size($report->path) ?: 0);
        $filename = 'reporte-inspeccion-rutina-'.$report->routine_id.'-'.$report->id.'.pdf';

        return InvoiceEvidence::query()->create([
            'company_id' => $invoice->company_id,
            'invoice_id' => $invoice->id,
            'kind' => InvoiceEvidenceKind::RoutineReport,
            'generated_report_id' => $report->id,
            'disk' => 'linked',
            'path' => 'generated_report:'.$report->id,
            'original_name' => $filename,
            'mime_type' => 'application/pdf',
            'size_bytes' => $size,
            'uploaded_by' => $user?->id,
        ]);
    }

    public function delete(InvoiceEvidence $evidence): void
    {
        $evidence->loadMissing('invoice');
        $this->assertDraftEditable($evidence->invoice);

        if ($evidence->kind !== InvoiceEvidenceKind::RoutineReport && $evidence->disk !== 'linked') {
            $disk = Storage::disk((string) $evidence->disk);
            if ($evidence->path !== '' && $disk->exists($evidence->path)) {
                $disk->delete($evidence->path);
            }
        }

        $evidence->delete();
    }

    public function stream(InvoiceEvidence $evidence)
    {
        if ($evidence->kind === InvoiceEvidenceKind::RoutineReport) {
            $report = $evidence->generatedReport;
            if ($report === null || $report->path === null || $report->status !== 'ready') {
                abort(404, 'Reporte no disponible.');
            }
            $disk = Storage::disk($report->disk);
            if (! $disk->exists($report->path)) {
                abort(404, 'Archivo no encontrado.');
            }

            return $disk->response(
                $report->path,
                $evidence->original_name,
                ['Content-Type' => $evidence->mime_type ?? 'application/pdf'],
            );
        }

        $disk = Storage::disk((string) $evidence->disk);
        if (! $disk->exists($evidence->path)) {
            abort(404, 'Archivo no encontrado.');
        }

        return $disk->response(
            $evidence->path,
            $evidence->original_name,
            ['Content-Type' => $evidence->mime_type ?? 'application/octet-stream'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(InvoiceEvidence $evidence): array
    {
        $evidence->loadMissing('generatedReport');

        return [
            'id' => $evidence->id,
            'kind' => $evidence->kind->value,
            'generated_report_id' => $evidence->generated_report_id,
            'original_name' => $evidence->original_name,
            'mime_type' => $evidence->mime_type,
            'size_bytes' => $evidence->size_bytes,
            'created_at' => $evidence->created_at?->toIso8601String(),
            'download_url' => url("/api/v1/billing/invoices/{$evidence->invoice_id}/evidences/{$evidence->id}/file"),
        ];
    }
}
