<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Support\CurrentCompany;
use App\Support\PlatformAdmin;
use App\Support\PlatformCompanyAccess;
use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class SetCompanyContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $companyId = $request->header('X-Company-Id') ?? $request->query('company_id');

        if ($companyId === null) {
            return response()->json(['message' => 'X-Company-Id header required.'], 400);
        }

        $company = Company::query()->whereKey($companyId)->first();
        if ($company === null) {
            return response()->json(['message' => 'Company not found.'], 404);
        }

        $user = $request->user();
        $isPlatformAdmin = PlatformAdmin::isPlatformAdmin($user);

        if (! $company->is_active && ! $isPlatformAdmin) {
            return response()->json(['message' => 'Company is inactive.'], 403);
        }

        $membership = CompanyMembership::query()
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        if ($membership === null && $isPlatformAdmin) {
            $membership = PlatformCompanyAccess::syntheticMembership($user, $company);
            $request->attributes->set(PlatformCompanyAccess::REQUEST_FLAG, true);
        }

        if ($membership === null) {
            return response()->json(['message' => 'Forbidden for this company.'], 403);
        }

        app()->instance(CurrentCompany::class, new CurrentCompany($company));
        $request->attributes->set('membership', $membership);

        app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);

        return $next($request);
    }
}
