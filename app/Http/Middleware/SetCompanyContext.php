<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Support\CurrentCompany;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCompanyContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $companyId = $request->header('X-Company-Id') ?? $request->query('company_id');

        if ($companyId === null) {
            return response()->json(['message' => 'X-Company-Id header required.'], 400);
        }

        $company = Company::query()->whereKey($companyId)->where('is_active', true)->first();
        if ($company === null) {
            return response()->json(['message' => 'Company not found.'], 404);
        }

        $user = $request->user();
        $membership = CompanyMembership::query()
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        if ($membership === null) {
            return response()->json(['message' => 'Forbidden for this company.'], 403);
        }

        app()->instance(CurrentCompany::class, new CurrentCompany($company));
        $request->attributes->set('membership', $membership);

        return $next($request);
    }
}
