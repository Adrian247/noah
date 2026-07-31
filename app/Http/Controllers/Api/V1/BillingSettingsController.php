<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MembershipRole;
use App\Http\Controllers\Controller;
use App\Support\CurrentCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillingSettingsController extends Controller
{
    public function show(): JsonResponse
    {
        $company = app(CurrentCompany::class)->company;
        abort_if($company === null, 400, 'Company context required.');

        return response()->json([
            'data' => [
                'currency' => $company->currency,
                'billing_labor_rate_per_hour' => $company->billing_labor_rate_per_hour
                    ?? config('phoenix.billing.labor_rate_per_hour', 0),
                'billing_tax_rate' => $company->billing_tax_rate
                    ?? config('phoenix.billing.tax_rate', 0.16),
                'fiscal_enabled' => (bool) $company->fiscal_enabled,
                'fiscal_provider' => $company->fiscal_provider
                    ?? config('phoenix.billing.fiscal.default_provider', 'sandbox'),
                'fiscal_settings' => $company->fiscal_settings ?? [],
                'defaults' => [
                    'labor_rate' => (float) config('phoenix.billing.labor_rate_per_hour', 0),
                    'tax_rate' => (float) config('phoenix.billing.tax_rate', 0.16),
                    'fiscal_provider' => config('phoenix.billing.fiscal.default_provider', 'sandbox'),
                ],
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $this->authorizeBilling($request);

        $data = $request->validate([
            'billing_labor_rate_per_hour' => ['required', 'numeric', 'min:0'],
            'billing_tax_rate' => ['required', 'numeric', 'min:0', 'max:1'],
            'fiscal_enabled' => ['sometimes', 'boolean'],
            'fiscal_provider' => ['sometimes', 'nullable', 'string', 'in:sandbox,mexico_pac'],
            'fiscal_settings' => ['sometimes', 'nullable', 'array'],
            'fiscal_settings.base_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'fiscal_settings.api_key' => ['sometimes', 'nullable', 'string', 'max:512'],
        ]);

        $company = app(CurrentCompany::class)->company;
        abort_if($company === null, 400, 'Company context required.');

        $payload = [
            'billing_labor_rate_per_hour' => $data['billing_labor_rate_per_hour'],
            'billing_tax_rate' => $data['billing_tax_rate'],
        ];

        if (array_key_exists('fiscal_enabled', $data)) {
            $payload['fiscal_enabled'] = (bool) $data['fiscal_enabled'];
        }
        if (array_key_exists('fiscal_provider', $data)) {
            $payload['fiscal_provider'] = $data['fiscal_provider'];
        }
        if (array_key_exists('fiscal_settings', $data)) {
            $payload['fiscal_settings'] = $data['fiscal_settings'];
        }

        $company->update($payload);

        return $this->show();
    }

    private function authorizeBilling(Request $request): void
    {
        $membership = $request->attributes->get('membership');
        $role = $membership->role;
        $roleValue = $role instanceof MembershipRole ? $role->value : (string) $role;

        if (! in_array($roleValue, [MembershipRole::Administrator->value, MembershipRole::Billing->value], true)) {
            abort(403, 'Billing role required.');
        }
    }
}
