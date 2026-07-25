<?php

namespace App\Http\Middleware;

use App\Services\Identity\CompanyAuthorizationService;
use App\Support\CurrentCompany;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireCompanyPermission
{
    public function __construct(
        private readonly CompanyAuthorizationService $authorization,
    ) {}

    /**
     * @param  string  ...$permissions  Slugs de NoahPermission
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $companyId = app(CurrentCompany::class)->id();

        foreach ($permissions as $permission) {
            foreach (explode(',', $permission) as $part) {
                $slug = trim($part);
                if ($slug === '') {
                    continue;
                }
                if ($this->authorization->userHasPermission($user, $companyId, $slug)) {
                    return $next($request);
                }
            }
        }

        return response()->json(['message' => 'Insufficient permissions for this action.'], 403);
    }
}
