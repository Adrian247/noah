<?php

namespace App\Services\Identity;

use App\Enums\MembershipRole;
use App\Enums\NoahPermission;
use App\Models\PlatformSetting;
use Illuminate\Validation\ValidationException;

class RolePermissionTemplateService
{
    public const SETTING_KEY = 'role_permission_map';

    /**
     * @return array<string, list<string>>
     */
    public function builtInMap(): array
    {
        $all = NoahPermission::values();

        return [
            MembershipRole::Administrator->value => $all,
            MembershipRole::Supervisor->value => [
                NoahPermission::RoutinesAssign->value,
                NoahPermission::RoutinesValidate->value,
                NoahPermission::CostsView->value,
                NoahPermission::CatalogSuppliersManage->value,
            ],
            MembershipRole::Technician->value => [
                NoahPermission::RoutinesExecute->value,
            ],
            MembershipRole::Billing->value => [
                NoahPermission::BillingDraft->value,
                NoahPermission::BillingDraftEdit->value,
                NoahPermission::BillingIssue->value,
                NoahPermission::BillingSettings->value,
                NoahPermission::CostsView->value,
                NoahPermission::ClientsView->value,
            ],
            MembershipRole::Auditor->value => [
                NoahPermission::AuditView->value,
            ],
            MembershipRole::Client->value => [
                NoahPermission::PortalInvoicesView->value,
                NoahPermission::PortalInvoicesDownload->value,
                NoahPermission::PortalRoutinesView->value,
            ],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public function defaultMap(): array
    {
        return $this->builtInMap();
    }

    /**
     * @return array<string, list<string>>
     */
    public function map(): array
    {
        $stored = PlatformSetting::query()
            ->where('key', self::SETTING_KEY)
            ->value('value');

        if (! is_array($stored) || $stored === []) {
            return $this->defaultMap();
        }

        $defaults = $this->defaultMap();
        $merged = [];

        foreach ($defaults as $roleName => $defaultPermissions) {
            $raw = $stored[$roleName] ?? $defaultPermissions;
            $merged[$roleName] = $this->normalizePermissionList($raw, $roleName);
        }

        return $merged;
    }

    /**
     * @param  array<string, list<string>>  $roles
     * @return array<string, list<string>>
     */
    public function saveMap(array $roles): array
    {
        $catalog = NoahPermission::values();
        $defaults = $this->defaultMap();
        $normalized = [];

        foreach ($defaults as $roleName => $_) {
            if (! array_key_exists($roleName, $roles)) {
                throw ValidationException::withMessages([
                    'roles' => ["Falta el rol: {$roleName}"],
                ]);
            }
            $normalized[$roleName] = $this->normalizePermissionList($roles[$roleName], $roleName, $catalog);
        }

        foreach (array_keys($roles) as $roleName) {
            if (! array_key_exists($roleName, $defaults)) {
                throw ValidationException::withMessages([
                    'roles' => ["Rol desconocido: {$roleName}"],
                ]);
            }
        }

        PlatformSetting::query()->updateOrCreate(
            ['key' => self::SETTING_KEY],
            ['value' => $normalized],
        );

        return $this->map();
    }

    /**
     * @param  list<string>|mixed  $raw
     * @param  list<string>|null  $catalog
     * @return list<string>
     */
    private function normalizePermissionList(mixed $raw, string $roleName, ?array $catalog = null): array
    {
        $catalog ??= NoahPermission::values();

        if ($roleName === MembershipRole::Administrator->value) {
            return $catalog;
        }

        if (! is_array($raw)) {
            throw ValidationException::withMessages([
                'roles' => ["Permisos inválidos para el rol {$roleName}."],
            ]);
        }

        $out = [];
        foreach ($raw as $slug) {
            if (! is_string($slug) || ! in_array($slug, $catalog, true)) {
                throw ValidationException::withMessages([
                    'roles' => ["Permiso no válido en {$roleName}: ".(string) $slug],
                ]);
            }
            $out[] = $slug;
        }

        return array_values(array_unique($out));
    }
}
