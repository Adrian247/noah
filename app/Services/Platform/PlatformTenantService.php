<?php

namespace App\Services\Platform;

use App\Enums\MembershipRole;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\User;
use App\Services\Identity\CompanyAuthorizationService;
use App\Services\Workflow\WorkflowRuntime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PlatformTenantService
{
    public function __construct(
        private readonly CompanyAuthorizationService $authorization,
        private readonly TenantUserProvisioner $provisioner,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listTenants(): array
    {
        return Company::query()
            ->with([
                'memberships' => fn ($q) => $q
                    ->where('is_active', true)
                    ->where('role', MembershipRole::Administrator)
                    ->orderBy('id')
                    ->limit(1)
                    ->with('user'),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (Company $company) => $this->tenantPayload($company))
            ->all();
    }

    /**
     * @param  array{
     *     name: string,
     *     legal_name?: string|null,
     *     currency?: string,
     *     admin_name: string,
     *     admin_email: string,
     *     send_invitation?: bool,
     * }  $data
     */
    public function createTenant(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $company = Company::query()->create([
                'name' => $data['name'],
                'legal_name' => $data['legal_name'] ?? $data['name'],
                'currency' => $data['currency'] ?? 'MXN',
                'timezone' => 'America/Mexico_City',
                'is_active' => true,
            ]);

            $this->authorization->ensureCompanyRoles($company);

            app(WorkflowRuntime::class)->seedDefinitionForCompany($company->id);

            $sendInvitation = $data['send_invitation'] ?? true;
            $provisioned = $this->provisioner->provision(
                $company,
                $data['admin_email'],
                $data['admin_name'],
                MembershipRole::Administrator,
                $sendInvitation,
            );

            $membership = $this->upsertMembership($company, $provisioned['user'], MembershipRole::Administrator);

            $payload = $this->tenantPayload($company->fresh());
            $payload['admin_invited'] = $provisioned['invited'];
            $payload['admin_user_id'] = $provisioned['user']->id;

            return $payload;
        });
    }

    /**
     * @param  array{
     *     name?: string,
     *     legal_name?: string|null,
     *     is_active?: bool,
     *     admin_name?: string,
     *     admin_email?: string,
     * }  $data
     */
    public function updateTenant(Company $company, array $data): array
    {
        $updates = [];
        if (array_key_exists('name', $data) && $data['name'] !== null) {
            $updates['name'] = $data['name'];
        }
        if (array_key_exists('legal_name', $data)) {
            $updates['legal_name'] = $data['legal_name'];
        }
        if (array_key_exists('is_active', $data)) {
            $updates['is_active'] = $data['is_active'];
        }

        if ($updates !== []) {
            $company->update($updates);
        }

        $admin = $this->primaryAdministrator($company);
        if ($admin !== null) {
            $userUpdates = [];
            if (array_key_exists('admin_name', $data) && is_string($data['admin_name'])) {
                $userUpdates['name'] = trim($data['admin_name']);
            }
            if (array_key_exists('admin_email', $data) && is_string($data['admin_email'])) {
                $email = strtolower(trim($data['admin_email']));
                if ($email !== strtolower($admin->email)) {
                    if (User::query()->where('email', $email)->whereKeyNot($admin->id)->exists()) {
                        throw ValidationException::withMessages([
                            'admin_email' => ['Ya existe un usuario con ese correo.'],
                        ]);
                    }
                    $userUpdates['email'] = $email;
                }
            }
            if ($userUpdates !== []) {
                $admin->update($userUpdates);
            }
        }

        return $this->tenantPayload($company->fresh());
    }

    public function updateLogo(Company $company, string $storedPath): array
    {
        if ($company->logo_path && Storage::disk('public')->exists($company->logo_path)) {
            Storage::disk('public')->delete($company->logo_path);
        }

        $company->update(['logo_path' => $storedPath]);

        return $this->tenantPayload($company->fresh());
    }

    /**
     * @param  array{email: string, name: string, role: string, send_invitation?: bool}  $data
     */
    public function addMembership(Company $company, array $data): array
    {
        $role = MembershipRole::tryFrom($data['role']);
        if ($role === null || $role === MembershipRole::Client) {
            throw ValidationException::withMessages([
                'role' => ['Rol no válido para alta desde plataforma.'],
            ]);
        }

        $sendInvitation = $data['send_invitation'] ?? true;
        $provisioned = $this->provisioner->provision(
            $company,
            $data['email'],
            $data['name'],
            $role,
            $sendInvitation,
        );

        $membership = $this->upsertMembership($company, $provisioned['user'], $role);

        return [
            'membership_id' => $membership->id,
            'user_id' => $provisioned['user']->id,
            'email' => $provisioned['user']->email,
            'name' => $provisioned['user']->name,
            'role' => $role->value,
            'is_active' => $membership->is_active,
            'invited' => $provisioned['invited'],
        ];
    }

    private function upsertMembership(Company $company, \App\Models\User $user, MembershipRole $role): CompanyMembership
    {
        $membership = CompanyMembership::query()->updateOrCreate(
            ['company_id' => $company->id, 'user_id' => $user->id],
            ['role' => $role, 'is_active' => true, 'client_id' => null],
        );

        $this->authorization->syncMembershipRole($membership->fresh(['user', 'company']));

        return $membership;
    }

    /**
     * @return array<string, mixed>
     */
    private function tenantPayload(Company $company): array
    {
        if (! $company->relationLoaded('memberships')) {
            $company->load([
                'memberships' => fn ($q) => $q
                    ->where('is_active', true)
                    ->where('role', MembershipRole::Administrator)
                    ->orderBy('id')
                    ->limit(1)
                    ->with('user'),
            ]);
        }

        $company->loadCount([
            'memberships as active_memberships_count' => fn ($q) => $q->where('is_active', true),
        ]);

        $admin = $this->primaryAdministrator($company);

        return [
            'id' => $company->id,
            'name' => $company->name,
            'legal_name' => $company->legal_name,
            'currency' => $company->currency,
            'is_active' => $company->is_active,
            'logo_url' => self::logoUrl($company),
            'admin_user_id' => $admin?->id,
            'admin_name' => $admin?->name,
            'admin_email' => $admin?->email,
            'admin_avatar_url' => $admin !== null ? ProfileController::avatarUrl($admin) : null,
            'active_memberships_count' => $company->active_memberships_count ?? 0,
            'created_at' => $company->created_at?->toIso8601String(),
        ];
    }

    public static function logoUrl(?Company $company): ?string
    {
        if ($company === null) {
            return null;
        }

        $path = $company->logo_path;
        if ($path === null || $path === '') {
            return null;
        }

        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    private function primaryAdministrator(Company $company): ?User
    {
        $membership = $company->memberships
            ->first(fn (CompanyMembership $m) => $m->role === MembershipRole::Administrator && $m->is_active);

        if ($membership === null) {
            $membership = CompanyMembership::query()
                ->where('company_id', $company->id)
                ->where('is_active', true)
                ->where('role', MembershipRole::Administrator)
                ->orderBy('id')
                ->with('user')
                ->first();
        }

        return $membership?->user;
    }
}
