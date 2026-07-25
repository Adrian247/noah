<?php

namespace App\Http\Middleware;

use App\Services\Identity\CompanyAuthorizationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireCompanyModuleAccess
{
    public function __construct(
        private readonly CompanyAuthorizationService $authorization,
    ) {}

    /**
     * @param  'read'|'write'  $level
     */
    public function handle(Request $request, Closure $next, string $moduleId, string $level = 'read'): Response
    {
        $membership = $request->attributes->get('membership');
        if ($membership === null) {
            return response()->json(['message' => 'Company context required.'], 400);
        }

        $modules = $this->authorization->modulesForMembership($membership);
        $state = $modules[$moduleId] ?? ['read' => false, 'write' => false, 'visible' => false];

        if ($level === 'write') {
            if (! $state['write']) {
                return response()->json(['message' => 'Write access denied for this module.'], 403);
            }

            return $next($request);
        }

        if (! $state['visible']) {
            return response()->json(['message' => 'Module not available for this user.'], 403);
        }

        return $next($request);
    }
}
