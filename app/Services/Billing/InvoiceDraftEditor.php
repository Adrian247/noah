<?php

namespace App\Services\Billing;

use App\Enums\InvoiceLineType;
use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Services\Audit\AuditLogger;
use App\Support\CurrentCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InvoiceDraftEditor
{
    public function __construct(
        private readonly InvoiceTotalsCalculator $totals,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateDraft(Invoice $invoice, array $payload, Request $request, AuditLogger $audit): Invoice
    {
        if ($invoice->status !== InvoiceStatus::Draft) {
            throw ValidationException::withMessages([
                'invoice' => ['Solo se pueden editar borradores (prefacturas).'],
            ]);
        }

        $companyId = app(CurrentCompany::class)->id();

        $validated = validator($payload, [
            'client_id' => ['nullable', 'integer', Rule::exists('clients', 'id')->where('company_id', $companyId)],
            'custom_reference' => ['nullable', 'string', 'max:128'],
            'notify_client_on_issue' => ['sometimes', 'boolean'],
            'client_portal_visible' => ['sometimes', 'boolean'],
            'delivery_deferred' => ['sometimes', 'boolean'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.line_type' => ['required', Rule::enum(InvoiceLineType::class)],
            'lines.*.description' => ['required', 'string', 'max:500'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'lines.*.source_routine_consumption_id' => ['nullable', 'integer', 'exists:routine_consumptions,id'],
            'lines.*.metadata' => ['nullable', 'array'],
        ])->validate();

        if ($validated['client_id'] ?? null) {
            $client = Client::query()
                ->where('company_id', $companyId)
                ->where('id', $validated['client_id'])
                ->where('is_active', true)
                ->first();
            if ($client === null) {
                throw ValidationException::withMessages(['client_id' => ['Cliente no válido o inactivo.']]);
            }
        }

        $taxRate = (float) ($invoice->tax_rate_snapshot
            ?? $invoice->company?->billing_tax_rate
            ?? config('phoenix.billing.tax_rate', 0.16));

        return DB::transaction(function () use ($invoice, $validated, $taxRate, $request, $audit) {
            $invoice->lines()->delete();

            foreach ($validated['lines'] as $index => $lineData) {
                $qty = (float) $lineData['quantity'];
                $unit = (float) $lineData['unit_price'];
                InvoiceLine::query()->create([
                    'invoice_id' => $invoice->id,
                    'line_type' => $lineData['line_type'],
                    'sort_order' => $lineData['sort_order'] ?? $index,
                    'source_routine_consumption_id' => $lineData['source_routine_consumption_id'] ?? null,
                    'description' => $lineData['description'],
                    'quantity' => $qty,
                    'unit_price' => $unit,
                    'line_total' => $this->totals->lineTotal($qty, $unit),
                    'metadata' => $lineData['metadata'] ?? null,
                ]);
            }

            $invoice->update([
                'client_id' => $validated['client_id'] ?? null,
                'custom_reference' => array_key_exists('custom_reference', $validated)
                    ? ($validated['custom_reference'] !== '' ? $validated['custom_reference'] : null)
                    : $invoice->custom_reference,
                'notify_client_on_issue' => $validated['notify_client_on_issue'] ?? $invoice->notify_client_on_issue,
                'client_portal_visible' => $validated['client_portal_visible'] ?? $invoice->client_portal_visible,
                'delivery_deferred' => $validated['delivery_deferred'] ?? $invoice->delivery_deferred,
            ]);

            $invoice = $this->totals->applyTaxRate($invoice->fresh(), $taxRate);

            $audit->fromRequest($request, 'invoice.draft_updated', Invoice::class, $invoice->id, [
                'line_count' => count($validated['lines']),
                'client_id' => $validated['client_id'] ?? null,
            ]);

            return $invoice;
        });
    }
}
