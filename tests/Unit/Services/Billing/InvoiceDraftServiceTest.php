<?php

namespace Tests\Unit\Services\Billing;

use App\Models\Routine;
use App\Models\RoutineExecution;
use App\Models\User;
use App\Services\Billing\InvoiceDraftService;
use App\Support\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDemoRoutine;
use Tests\TestCase;

class InvoiceDraftServiceTest extends TestCase
{
    use CreatesDemoRoutine;
    use RefreshDatabase;

    public function test_draft_total_matches_consumptions_plus_tax_when_labor_disabled(): void
    {
        config(['phoenix.billing.labor_rate_per_hour' => 0, 'phoenix.billing.tax_rate' => 0.16]);
        $this->seed();

        $technician = User::query()->where('email', 'misael.palos@mein-company.com')->firstOrFail();
        $routine = $this->demoRoutine($technician);
        $execution = $routine->latestExecution ?? $routine->executions()->create([
            'performed_by' => $technician->id,
            'duration_minutes' => 120,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $supply = \App\Models\SupplyItem::query()->first();
        $execution->consumptions()->create([
            'supply_item_id' => $supply->id,
            'quantity' => 1,
            'unit_cost' => 450,
        ]);

        app()->instance(CurrentCompany::class, new CurrentCompany($routine->company));

        $invoice = app(InvoiceDraftService::class)->createFromRoutine($routine, $execution->fresh(['consumptions.supplyItem']));

        $this->assertEqualsWithDelta(450.0, (float) $invoice->subtotal, 0.01);
        $this->assertEqualsWithDelta(72.0, (float) $invoice->tax_total, 0.01);
        $this->assertEqualsWithDelta(522.0, (float) $invoice->total, 0.01);
    }
}
