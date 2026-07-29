<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MembershipRole;
use App\Http\Controllers\Controller;
use App\Models\CompanyMembership;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Identity\CompanyAuthorizationService;
use App\Services\Platform\TenantUserProvisioner;
use App\Support\CurrentCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CompanyUserController extends Controller
{
    public function __construct(
        private readonly CompanyAuthorizationService $authorization,
        private readonly AuditLogger $audit,
        private readonly TenantUserProvisioner $provisioner,
    ) {}

    public function index(): JsonResponse
    {
        $companyId = app(CurrentCompany::class)->id();

        $memberships = CompanyMembership::query()
            ->where('company_id', $companyId)
            ->with('user')
            ->orderBy('id')
            ->get();

        $labels = $this->authorization->roleLabels();

        $data = $memberships->map(fn (CompanyMembership $m) => $this->formatMembership($m, $labels, $companyId));

        return response()->json(['data' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = app(CurrentCompany::class)->id();

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'role' => ['required', Rule::enum(MembershipRole::class)],
            'extra_permissions' => ['sometimes', 'array'],
            'extra_permissions.*' => ['string', 'max:64'],
            'modules' => ['prohibited'],
            'send_invitation' => ['sometimes', 'boolean'],
        ]);

        $email = strtolower($validated['email']);
        $company = \App\Models\Company::query()->findOrFail($companyId);

        $provisioned = $this->provisioner->provision(
            $company,
            $email,
            $validated['name'] ?? Str::before($email, '@'),
            MembershipRole::from($validated['role']),
            (bool) ($validated['send_invitation'] ?? true),
        );
        $user = $provisioned['user'];

        $membership = CompanyMembership::query()->firstOrCreate(
            ['company_id' => $companyId, 'user_id' => $user->id],
            [
                'role' => $validated['role'],
                'is_active' => true,
                'client_id' => null,
            ],
        );

        if (! $membership->wasRecentlyCreated) {
            if ($membership->is_active && $membership->role->value === $validated['role']) {
                throw ValidationException::withMessages([
                    'email' => ['Este usuario ya pertenece a la empresa con ese rol.'],
                ]);
            }
            $membership = $this->authorization->assignMembershipRole(
                $membership,
                MembershipRole::from($validated['role']),
                true,
            );
        } else {
            $this->authorization->syncMembershipRole($membership);
        }

        $membership = $membership->fresh(['user']);
        $this->authorization->clearLegacyModuleAccess($membership);

        $extras = $validated['extra_permissions'] ?? [];
        $this->authorization->syncDirectPermissions($membership, $extras);

        $this->audit->fromRequest(
            $request,
            'membership.granted',
            CompanyMembership::class,
            $membership->id,
            ['user_id' => $user->id, 'role' => $validated['role'], 'extra_permissions' => $extras],
        );

        $labels = $this->authorization->roleLabels();

        return response()->json([
            'data' => $this->formatMembership($membership->fresh('user'), $labels, $companyId),
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $companyId = app(CurrentCompany::class)->id();
        $actor = $request->user();

        $membership = CompanyMembership::query()
            ->where('company_id', $companyId)
            ->where('user_id', $user->id)
            ->first();

        if ($membership === null) {
            return response()->json(['message' => 'User not in this company.'], 404);
        }

        $validated = $request->validate([
            'role' => ['sometimes', Rule::enum(MembershipRole::class)],
            'is_active' => ['sometimes', 'boolean'],
            'extra_permissions' => ['sometimes', 'array'],
            'extra_permissions.*' => ['string', 'max:64'],
            'modules' => ['prohibited'],
        ]);

        if (array_key_exists('is_active', $validated) && $validated['is_active'] === false) {
            if ($actor !== null && $actor->id === $user->id) {
                throw ValidationException::withMessages([
                    'is_active' => ['No puedes desactivar tu propia membresía.'],
                ]);
            }
        }

        $newRole = isset($validated['role'])
            ? MembershipRole::from($validated['role'])
            : ($membership->role instanceof MembershipRole ? $membership->role : MembershipRole::from((string) $membership->role));

        $isActive = $validated['is_active'] ?? $membership->is_active;

        $previousRole = $membership->role instanceof MembershipRole
            ? $membership->role->value
            : (string) $membership->role;

        $membership = $this->authorization->assignMembershipRole($membership, $newRole, $isActive);
        $this->authorization->clearLegacyModuleAccess($membership);

        if (array_key_exists('extra_permissions', $validated)) {
            $this->authorization->syncDirectPermissions($membership, $validated['extra_permissions']);
            $this->audit->fromRequest(
                $request,
                'membership.permissions_updated',
                CompanyMembership::class,
                $membership->id,
                [
                    'user_id' => $user->id,
                    'extra_permissions' => $validated['extra_permissions'],
                ],
            );
        }

        $this->audit->fromRequest(
            $request,
            'membership.updated',
            CompanyMembership::class,
            $membership->id,
            [
                'user_id' => $user->id,
                'previous_role' => $previousRole,
                'role' => $newRole->value,
                'is_active' => $isActive,
            ],
        );

        $labels = $this->authorization->roleLabels();

        return response()->json([
            'data' => $this->formatMembership($membership->fresh('user'), $labels, $companyId),
        ]);
    }

    public function updateAvatar(Request $request, User $user): JsonResponse
    {
        $companyId = app(CurrentCompany::class)->id();

        $membership = CompanyMembership::query()
            ->where('company_id', $companyId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $request->validate([
            'avatar' => ['required', 'image', 'max:2048', 'mimes:jpg,jpeg,png,webp'],
        ]);

        if ($user->avatar_path && Storage::disk('public')->exists($user->avatar_path)) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $path = $request->file('avatar')->store('avatars/'.$user->id, 'public');
        $user->update(['avatar_path' => $path]);

        $this->audit->fromRequest($request, 'user.avatar_updated', User::class, $user->id, [
            'membership_id' => $membership->id,
        ]);

        $labels = $this->authorization->roleLabels();

        return response()->json([
            'data' => $this->formatMembership($membership->fresh('user'), $labels, $companyId),
        ]);
    }

    /**
     * @param  array<string, string>  $labels
     * @return array<string, mixed>
     */
    private function formatMembership(CompanyMembership $m, array $labels, int $companyId): array
    {
        $roleValue = $m->role instanceof MembershipRole ? $m->role->value : (string) $m->role;
        $rolePermissions = $this->authorization->rolePermissionsForMembership($m);
        $extraPermissions = $this->authorization->directPermissionsForUser($m->user, $companyId);
        $effective = $this->authorization->permissionsForUser($m->user, $companyId);
        $modules = $this->authorization->modulesForMembership($m);

        return [
            'id' => $m->user->id,
            'membership_id' => $m->id,
            'name' => $m->user->name,
            'email' => $m->user->email,
            'avatar_url' => ProfileController::avatarUrl($m->user),
            'role' => $roleValue,
            'role_label' => $labels[$roleValue] ?? $roleValue,
            'is_active' => $m->is_active,
            'role_permissions' => $rolePermissions,
            'extra_permissions' => $extraPermissions,
            'effective_permissions' => $effective,
            'modules' => $modules,
        ];
    }
}
