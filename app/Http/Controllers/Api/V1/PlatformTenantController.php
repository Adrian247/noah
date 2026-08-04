<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Platform\PlatformTenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Support\PlatformAdmin;
use Illuminate\Support\Facades\Storage;

class PlatformTenantController extends Controller
{
    public function __construct(
        private readonly PlatformTenantService $tenants,
        private readonly AuditLogger $audit,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->tenants->listTenants()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'currency' => ['nullable', 'string', 'size:3'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255'],
        ]);

        $tenant = $this->tenants->createTenant($data);

        return response()->json(['data' => $tenant], 201);
    }

    public function update(Request $request, Company $company): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'admin_name' => ['sometimes', 'string', 'max:255'],
            'admin_email' => ['sometimes', 'email', 'max:255'],
        ]);

        $tenant = $this->tenants->updateTenant($company, $data);

        $this->audit->record(
            $company->id,
            $request->user()?->id,
            'platform.tenant_updated',
            Company::class,
            $company->id,
            [
                'name' => $tenant['name'] ?? $company->name,
                'access_channel' => \App\Support\AccessChannel::WEB,
            ],
            $request->ip(),
        );

        return response()->json(['data' => $tenant]);
    }

    public function updateLogo(Request $request, Company $company): JsonResponse
    {
        $request->validate([
            'logo' => ['required', 'image', 'max:2048', 'mimes:jpg,jpeg,png,webp'],
        ]);

        $path = $request->file('logo')->store('companies/'.$company->id, 'public');
        $tenant = $this->tenants->updateLogo($company, $path);

        $this->audit->record(
            $company->id,
            $request->user()?->id,
            'platform.tenant_logo_updated',
            Company::class,
            $company->id,
            ['access_channel' => \App\Support\AccessChannel::WEB],
            $request->ip(),
        );

        return response()->json(['data' => $tenant]);
    }

    public function storeMembership(Request $request, Company $company): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'string', 'in:administrator,supervisor,technician,billing,auditor'],
        ]);

        $membership = $this->tenants->addMembership($company, $data);

        return response()->json(['data' => $membership], 201);
    }

    public function updateUserAvatar(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'max:2048', 'mimes:jpg,jpeg,png,webp'],
        ]);

        if ($user->avatar_path && Storage::disk('public')->exists($user->avatar_path)) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $path = $request->file('avatar')->store('avatars/'.$user->id, 'public');
        $user->update(['avatar_path' => $path]);

        $this->audit->fromRequest($request, 'user.avatar_updated', User::class, $user->id, [
            'platform_managed' => true,
        ]);

        return response()->json([
            'data' => [
                'user_id' => $user->id,
                'avatar_url' => ProfileController::avatarUrl($user->fresh()),
            ],
        ]);
    }

    public function assume(Request $request, Company $company): JsonResponse
    {
        $user = $request->user();
        if (! PlatformAdmin::isPlatformAdmin($user)) {
            abort(403);
        }

        $this->audit->record(
            $company->id,
            $user->id,
            'platform.tenant_assumed',
            Company::class,
            $company->id,
            [
                'company_id' => $company->id,
                'company_name' => $company->name,
                'assumed_by' => $user->id,
                'access_channel' => \App\Support\AccessChannel::WEB,
            ],
            $request->ip(),
        );

        return response()->json([
            'data' => [
                'company_id' => $company->id,
                'company_name' => $company->name,
                'assumed' => true,
            ],
        ]);
    }
}
