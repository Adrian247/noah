<?php

namespace App\Services\Platform;

use App\Enums\MembershipRole;
use App\Mail\TenantUserInvitationMail;
use App\Models\Company;
use App\Models\User;
use App\Services\Identity\CompanyAuthorizationService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class TenantUserProvisioner
{
    public function __construct(
        private readonly CompanyAuthorizationService $authorization,
    ) {}

    /**
     * @return array{user: User, invited: bool, plain_password: string|null}
     */
    public function provision(
        Company $company,
        string $email,
        string $name,
        MembershipRole $role,
        bool $sendInvitation = true,
    ): array {
        $email = strtolower(trim($email));
        $existing = User::query()->where('email', $email)->first();
        $plainPassword = null;
        $invited = false;

        if ($existing === null) {
            $plainPassword = Str::password(16);
            $user = User::query()->create([
                'email' => $email,
                'name' => $name,
                'password' => Hash::make($plainPassword),
            ]);
            $invited = true;
        } else {
            $user = $existing;
            if ($name !== '' && $user->name !== $name) {
                $user->update(['name' => $name]);
            }
        }

        if ($invited && $sendInvitation && $plainPassword !== null) {
            $labels = $this->authorization->roleLabels();
            Mail::to($user->email)->send(new TenantUserInvitationMail(
                $user->name,
                $user->email,
                $plainPassword,
                $company,
                $labels[$role->value] ?? $role->value,
            ));
        }

        return [
            'user' => $user->fresh(),
            'invited' => $invited,
            'plain_password' => $plainPassword,
        ];
    }
}
