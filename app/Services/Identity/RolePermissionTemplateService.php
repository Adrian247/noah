<?php

namespace App\Services\Identity;

use App\Enums\MembershipRole;
use App\Enums\PhoenixPermission;
use App\Models\PlatformSetting;
use App\Support\TenantAdministratorPermissions;
use Illuminate\Validation\ValidationException;

class RolePermissionTemplateService
{
    public const SETTING_KEY = 'role_permission_map';

    /**
     * @return array<string, list<string>>
     */
    public function builtInMap(): array
    {
        return [
            MembershipRole::Administrator->value => TenantAdministratorPermissions::slugs(),
            MembershipRole::Supervisor->value => [
                PhoenixPermission::RoutinesAssign->value,
                PhoenixPermission::RoutinesValidate->value,
                PhoenixPermission::CostsView->value,
                PhoenixPermission::ClientsView->value,
                PhoenixPermission::CatalogSuppliersManage->value,
                PhoenixPermission::InventoryView->value,
                PhoenixPermission::InventoryManage->value,
                PhoenixPermission::AssetsView->value,
                PhoenixPermission::IntegrationsView->value,
                PhoenixPermission::InsightsUse->value,
            ],
            MembershipRole::Technician->value => [
                PhoenixPermission::RoutinesExecute->value,
                PhoenixPermission::InventoryView->value,
            ],
            MembershipRole::Billing->value => [
                PhoenixPermission::BillingDraft->value,
                PhoenixPermission::BillingDraftEdit->value,
                PhoenixPermission::BillingIssue->value,
                PhoenixPermission::BillingSettings->value,
                PhoenixPermission::CostsView->value,
                PhoenixPermission::ClientsView->value,
            ],
            MembershipRole::Auditor->value => [
                PhoenixPermission::AuditView->value,
            ],
            MembershipRole::Client->value => [
                PhoenixPermission::PortalInvoicesView->value,
                PhoenixPermission::PortalInvoicesDownload->value,
                PhoenixPermission::PortalRoutinesView->value,
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
        $catalog = PhoenixPermission::values();
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
        $catalog ??= PhoenixPermission::values();

        if ($roleName === MembershipRole::Administrator->value) {
            return TenantAdministratorPermissions::slugs();
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
