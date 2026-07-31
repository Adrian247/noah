<?php

namespace App\Services\Integrations;

use App\Models\Invoice;
use App\Models\Routine;
use App\Models\SupplyItem;
use App\Services\Automation\AutomationRuleRunner;

class OperationalEventBridge
{
    public function __construct(
        private readonly WebhookDispatcher $webhooks,
        private readonly AutomationRuleRunner $automation,
    ) {}

    public function routineValidated(Routine $routine): void
    {
        $payload = [
            'routine_id' => $routine->id,
            'status' => $routine->status instanceof \BackedEnum ? $routine->status->value : (string) $routine->status,
            'asset_tag' => $routine->asset?->tag,
        ];

        $this->webhooks->dispatch($routine->company_id, 'routine.validated', $payload);
        $this->automation->runTrigger($routine->company_id, 'routine.validated', $payload);
    }

    public function routineRejected(Routine $routine, string $reason): void
    {
        $payload = [
            'routine_id' => $routine->id,
            'reason' => $reason,
            'asset_tag' => $routine->asset?->tag,
        ];

        $this->webhooks->dispatch($routine->company_id, 'routine.rejected', $payload);
        $this->automation->runTrigger($routine->company_id, 'routine.rejected', $payload);
    }

    public function invoiceIssued(Invoice $invoice): void
    {
        $payload = [
            'invoice_id' => $invoice->id,
            'number' => $invoice->number,
            'total' => (float) $invoice->total,
            'routine_id' => $invoice->routine_id,
        ];

        $this->webhooks->dispatch($invoice->company_id, 'invoice.issued', $payload);
        $this->automation->runTrigger($invoice->company_id, 'invoice.issued', $payload);
    }

    public function inventoryLowStock(SupplyItem $item): void
    {
        if ($item->min_stock === null || (float) $item->quantity_on_hand > (float) $item->min_stock) {
            return;
        }

        $payload = [
            'supply_item_id' => $item->id,
            'sku' => $item->sku,
            'name' => $item->name,
            'quantity_on_hand' => (float) $item->quantity_on_hand,
            'min_stock' => (float) $item->min_stock,
        ];

        $this->webhooks->dispatch($item->company_id, 'inventory.low_stock', $payload);
        $this->automation->runTrigger($item->company_id, 'inventory.low_stock', $payload);
    }
}
