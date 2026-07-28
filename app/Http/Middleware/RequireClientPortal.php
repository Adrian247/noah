<?php

namespace App\Http\Middleware;

use App\Enums\MembershipRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireClientPortal
{
    public function handle(Request $request, Closure $next): Response
    {
        $membership = $request->attributes->get('membership');
        if ($membership === null) {
            abort(403, 'Company context required.');
        }

        $role = $membership->role;
        $roleValue = $role instanceof MembershipRole ? $role->value : (string) $role;

        if ($roleValue !== MembershipRole::Client->value) {
            abort(403, 'Client portal access only.');
        }

        if ($membership->client_id === null) {
            abort(403, 'Client profile not linked.');
        }

        return $next($request);
    }
}
