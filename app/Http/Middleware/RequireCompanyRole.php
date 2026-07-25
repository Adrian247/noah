<?php

namespace App\Http\Middleware;

use App\Enums\MembershipRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireCompanyRole
{
    /**
     * @param  string  ...$roles  Valores de MembershipRole (administrator, supervisor, …)
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $membership = $request->attributes->get('membership');
        if ($membership === null) {
            return response()->json(['message' => 'Company context required.'], 400);
        }

        $role = $membership->role;
        $roleValue = $role instanceof MembershipRole ? $role->value : (string) $role;

        $allowed = [];
        foreach ($roles as $r) {
            foreach (explode(',', $r) as $part) {
                $allowed[] = trim($part);
            }
        }

        if (! in_array($roleValue, $allowed, true)) {
            return response()->json(['message' => 'Insufficient role for this action.'], 403);
        }

        return $next($request);
    }
}
