<?php

namespace App\Services\Identity;

use App\Enums\MembershipRole;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\User;
use App\Support\CurrentCompany;
use App\Support\DemoAccounts;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Garantiza que cada cliente de catálogo tenga cuenta de portal (rol client + client_id).
 * Fase actual: contraseña fija compartida (demo / pruebas).
 */
class ClientPortalAccountService
{
    public function __construct(
        private readonly CompanyAuthorizationService $authorization,
    ) {}

    public static function portalPassword(): string
    {
        return DemoAccounts::tenantPassword();
    }

    /**
     * @return array{user: User, membership: CompanyMembership, created: bool}
     */
    public function syncForClient(Client $client): array
    {
        $email = strtolower(trim((string) $client->billing_email));
        if ($email === '') {
            throw ValidationException::withMessages([
                'billing_email' => ['El correo de facturación es obligatorio para acceso al portal del cliente.'],
            ]);
        }

        $company = Company::query()->findOrFail($client->company_id);
        $name = trim((string) ($client->trade_name ?: $client->legal_name)) ?: Str::before($email, '@');
        $password = self::portalPassword();

        $user = User::query()->where('email', $email)->first();
        $created = false;

        if ($user === null) {
            $user = User::query()->create([
                'email' => $email,
                'name' => $name,
                'password' => $password,
            ]);
            $created = true;
        } else {
            $user->forceFill([
                'name' => $name !== '' ? $name : $user->name,
                'password' => $password,
            ])->save();
        }

        $membership = CompanyMembership::query()
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->first();

        if ($membership !== null) {
            $roleValue = $membership->role instanceof MembershipRole
                ? $membership->role->value
                : (string) $membership->role;

            if ($roleValue !== MembershipRole::Client->value) {
                throw ValidationException::withMessages([
                    'billing_email' => [
                        'Este correo ya pertenece a un usuario interno de la empresa. Usa otro email para el portal del cliente.',
                    ],
                ]);
            }

            $membership->update([
                'role' => MembershipRole::Client,
                'client_id' => $client->id,
                'is_active' => $client->is_active !== false,
            ]);
        } else {
            $membership = CompanyMembership::query()->create([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'role' => MembershipRole::Client,
                'client_id' => $client->id,
                'is_active' => $client->is_active !== false,
            ]);
        }

        $this->authorization->syncMembershipRole($membership->fresh(['user', 'company']));
        $this->deactivateOtherPortalMembershipsForClient($client, $membership);

        return [
            'user' => $user->fresh(),
            'membership' => $membership->fresh(['user']),
            'created' => $created,
        ];
    }

    public function deactivateForClient(Client $client): void
    {
        CompanyMembership::query()
            ->where('company_id', $client->company_id)
            ->where('client_id', $client->id)
            ->where('role', MembershipRole::Client)
            ->update(['is_active' => false]);
    }

    public function syncAllForCurrentCompany(): int
    {
        $companyId = app(CurrentCompany::class)->id();
        if ($companyId === null) {
            return 0;
        }

        return $this->syncAllForCompanyId($companyId);
    }

    public function syncAllForCompanyId(int $companyId): int
    {
        $count = 0;
        Client::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereNotNull('billing_email')
            ->where('billing_email', '!=', '')
            ->orderBy('id')
            ->each(function (Client $client) use (&$count): void {
                $this->syncForClient($client);
                $count++;
            });

        return $count;
    }

    public function syncAllCompanies(): int
    {
        $total = 0;
        Company::query()->where('is_active', true)->orderBy('id')->each(function (Company $company) use (&$total): void {
            $total += $this->syncAllForCompanyId($company->id);
        });

        return $total;
    }

    private function deactivateOtherPortalMembershipsForClient(Client $client, CompanyMembership $keep): void
    {
        CompanyMembership::query()
            ->where('company_id', $client->company_id)
            ->where('client_id', $client->id)
            ->where('role', MembershipRole::Client)
            ->where('id', '!=', $keep->id)
            ->update(['is_active' => false, 'client_id' => null]);
    }
}
