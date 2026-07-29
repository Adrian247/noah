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
                'defaults' => [
                    'labor_rate' => (float) config('phoenix.billing.labor_rate_per_hour', 0),
                    'tax_rate' => (float) config('phoenix.billing.tax_rate', 0.16),
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
        ]);

        $company = app(CurrentCompany::class)->company;
        abort_if($company === null, 400, 'Company context required.');

        $company->update([
            'billing_labor_rate_per_hour' => $data['billing_labor_rate_per_hour'],
            'billing_tax_rate' => $data['billing_tax_rate'],
        ]);

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
