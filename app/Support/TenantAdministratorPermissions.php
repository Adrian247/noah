<?php

namespace App\Support;

use App\Enums\PhoenixPermission;

/**
 * Permisos del rol Administrador dentro de una empresa cliente (tenant).
 * Excluye diseño de workflows y capacidades de plataforma root.
 */
class TenantAdministratorPermissions
{
    /**
     * @return list<string>
     */
    public static function slugs(): array
    {
        $exclude = [
            PhoenixPermission::DesignWorkflows->value,
            PhoenixPermission::DesignWorkflowsView->value,
        ];

        return array_values(array_filter(
            PhoenixPermission::values(),
            static fn (string $slug): bool => ! in_array($slug, $exclude, true),
        ));
    }
}
