<?php

namespace App\Services\Billing;

use App\Contracts\Billing\FiscalAdapter;
use App\Models\Company;
use App\Services\Billing\Fiscal\MexicoPacAdapter;
use App\Services\Billing\Fiscal\SandboxPacAdapter;

class FiscalAdapterResolver
{
    public function resolve(Company $company): ?FiscalAdapter
    {
        if (! $company->fiscal_enabled) {
            return null;
        }

        $provider = $company->fiscal_provider
            ?? config('phoenix.billing.fiscal.default_provider', 'sandbox');

        return match ($provider) {
            'mexico_pac' => app(MexicoPacAdapter::class, ['settings' => $company->fiscal_settings ?? []]),
            'sandbox' => app(SandboxPacAdapter::class),
            default => null,
        };
    }
}
