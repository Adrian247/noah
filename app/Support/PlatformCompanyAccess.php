<?php

namespace App\Support;

use App\Enums\MembershipRole;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\User;
use Illuminate\Http\Request;

class PlatformCompanyAccess
{
    public const REQUEST_FLAG = 'platform_tenant_assumption';

    public static function canAssumeTenant(User $user, Company $company): bool
    {
        return PlatformAdmin::isPlatformAdmin($user);
    }

    public static function isAssumption(Request $request): bool
    {
        return (bool) $request->attributes->get(self::REQUEST_FLAG, false);
    }

    public static function syntheticMembership(User $user, Company $company): CompanyMembership
    {
        $membership = new CompanyMembership([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => MembershipRole::Administrator,
            'is_active' => true,
            'client_id' => null,
        ]);
        $membership->setRelation('company', $company);
        $membership->setRelation('user', $user);

        return $membership;
    }
}
