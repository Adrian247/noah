<?php

namespace App\Services\Billing;

use App\Enums\InvoiceEvidenceKind;
use App\Models\Invoice;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class InvoiceDeliveryPackageBuilder
{
    public function __construct(
        private readonly InvoicePdfExporter $pdfExporter,
    ) {}

    public function zipFilename(Invoice $invoice): string
    {
        $base = $this->packageBasename($invoice);

        return $base.'-paquete.zip';
    }

    public function packageBasename(Invoice $invoice): string
    {
        if (filled($invoice->custom_reference)) {
            return Str::slug($invoice->custom_reference) ?: 'factura-'.$invoice->id;
        }

        if (filled($invoice->number)) {
            return Str::slug($invoice->number) ?: 'factura-'.$invoice->id;
        }

        return 'factura-'.$invoice->id;
    }

    /**
     * @return string Absolute path to a temporary zip file (caller must unlink).
     */
    public function buildToTempFile(Invoice $invoice): string
    {
        $invoice->load(['lines', 'client', 'company', 'evidences.generatedReport']);

        $tmpZip = tempnam(sys_get_temp_dir(), 'noah_inv_zip_');
        if ($tmpZip === false) {
            throw new \RuntimeException('No se pudo crear el archivo temporal del paquete.');
        }

        $zip = new ZipArchive;
        if ($zip->open($tmpZip, ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('No se pudo abrir el archivo ZIP.');
        }

        $pdfName = 'factura-'.($invoice->number ?? $invoice->id).'.pdf';
        $zip->addFromString($pdfName, $this->pdfExporter->contents($invoice));

        foreach ($invoice->evidences as $evidence) {
            if ($evidence->kind === InvoiceEvidenceKind::RoutineReport) {
                $report = $evidence->generatedReport;
                if ($report === null || $report->path === null) {
                    continue;
                }
                $disk = Storage::disk($report->disk);
                if (! $disk->exists($report->path)) {
                    continue;
                }
                $safeName = $this->safeArchiveName($evidence->original_name);
                $zip->addFromString('reportes/'.$safeName, $disk->get($report->path));

                continue;
            }

            $disk = Storage::disk((string) $evidence->disk);
            if (! $disk->exists($evidence->path)) {
                continue;
            }

            $folder = $evidence->kind === InvoiceEvidenceKind::SatCfdi ? 'sat/' : 'evidencias/';
            $safeName = $this->safeArchiveName($evidence->original_name);
            $zip->addFromString($folder.$safeName, $disk->get($evidence->path));
        }

        $zip->close();

        return $tmpZip;
    }

    public function downloadResponse(Invoice $invoice): BinaryFileResponse
    {
        $path = $this->buildToTempFile($invoice);

        return response()->download($path, $this->zipFilename($invoice))->deleteFileAfterSend(true);
    }

    /**
     * @return array{bytes: string, filename: string}
     */
    public function buildInMemory(Invoice $invoice): array
    {
        $path = $this->buildToTempFile($invoice);
        $bytes = file_get_contents($path);
        unlink($path);

        if ($bytes === false) {
            throw new \RuntimeException('No se pudo leer el paquete ZIP.');
        }

        return [
            'bytes' => $bytes,
            'filename' => $this->zipFilename($invoice),
        ];
    }

    private function safeArchiveName(string $name): string
    {
        $name = str_replace(['\\', '/'], '-', $name);
        $name = preg_replace('/[^\p{L}\p{N}\.\-_\s]/u', '_', $name) ?? 'archivo';

        return trim($name) !== '' ? trim($name) : 'archivo';
    }
}
