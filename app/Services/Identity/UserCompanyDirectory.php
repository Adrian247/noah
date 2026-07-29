<?php

namespace App\Services\Identity;

use App\Enums\MembershipRole;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\User;
use App\Support\PlatformAdmin;
use App\Support\PlatformCompanyAccess;
use Illuminate\Support\Collection;

class UserCompanyDirectory
{
    public function __construct(
        private readonly CompanyAuthorizationService $authorization,
    ) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function companiesForUser(User $user): Collection
    {
        if (! PlatformAdmin::isPlatformAdmin($user)) {
            return $user->memberships()
                ->where('is_active', true)
                ->with('company')
                ->get()
                ->filter(fn (CompanyMembership $m) => $m->company !== null && $m->company->is_active)
                ->map(fn (CompanyMembership $m) => $this->formatMembershipCompany($user, $m));
        }

        $memberships = $user->memberships()
            ->where('is_active', true)
            ->with('company')
            ->get()
            ->keyBy('company_id');

        return Company::query()
            ->orderBy('name')
            ->get()
            ->map(function (Company $company) use ($user, $memberships) {
                $membership = $memberships->get($company->id);
                if ($membership !== null) {
                    return $this->formatMembershipCompany($user, $membership);
                }

                return $this->formatAssumedCompany($user, $company);
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function formatMembershipCompany(User $user, CompanyMembership $membership): array
    {
        $company = $membership->company;

        return [
            'id' => $company->id,
            'name' => $company->name,
            'role' => $membership->role->value,
            'client_id' => $membership->client_id,
            'assumed' => false,
            'company_is_active' => $company->is_active,
            'billing_contact_email' => $this->billingContactEmailForCompany($company->id),
            'permissions' => $this->authorization->permissionsForUser($user, $company->id),
            'modules' => $this->authorization->modulesForMembership($membership),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatAssumedCompany(User $user, Company $company): array
    {
        $synthetic = PlatformCompanyAccess::syntheticMembership($user, $company);

        return [
            'id' => $company->id,
            'name' => $company->name,
            'role' => 'platform_operator',
            'client_id' => null,
            'assumed' => true,
            'company_is_active' => $company->is_active,
            'billing_contact_email' => $this->billingContactEmailForCompany($company->id),
            'permissions' => $this->authorization->permissionsForPlatformAssumption(),
            'modules' => $this->authorization->modulesForPlatformAssumption($user),
        ];
    }

    private function billingContactEmailForCompany(int $companyId): ?string
    {
        $membership = CompanyMembership::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where('role', MembershipRole::Billing)
            ->with('user:id,email')
            ->orderBy('id')
            ->first();

        return $membership?->user?->email;
    }
}
