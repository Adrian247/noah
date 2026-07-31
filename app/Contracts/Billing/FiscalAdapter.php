<?php

namespace App\Contracts\Billing;

use App\Models\Invoice;
use App\Services\Billing\FiscalResult;

interface FiscalAdapter
{
    public function issue(Invoice $invoice): FiscalResult;

    public function cancel(Invoice $invoice): FiscalResult;
}
