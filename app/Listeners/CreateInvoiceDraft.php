<?php

namespace App\Listeners;

use App\Events\RoutineValidated;
use App\Services\Billing\InvoiceDraftService;
use App\Support\CurrentCompany;

class CreateInvoiceDraft
{
    public function __construct(private InvoiceDraftService $invoices) {}

    public function handle(RoutineValidated $event): void
    {
        $routine = $event->routine->load('company');
        app()->instance(CurrentCompany::class, new CurrentCompany($routine->company));

        if ($routine->invoice()->exists()) {
            return;
        }

        $event->execution->load(['consumptions.supplyItem']);
        $this->invoices->createFromRoutine($routine, $event->execution);
    }
}
