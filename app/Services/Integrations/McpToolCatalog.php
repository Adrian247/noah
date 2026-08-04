<?php

namespace App\Services\Integrations;

use App\Models\User;
use App\Services\AI\AiToolAuthorizer;
use App\Services\AI\Contracts\AiTool;
use App\Services\AI\Tools\AiToolRegistry;

/**
 * Catálogo de tools MCP (solo lectura) con dominio y disponibilidad según rol/usuario.
 */
final class McpToolCatalog
{
    /** @var array<string, array{domain: string, domain_label: string}> */
    private const META = [
        'list_recent_routines' => ['domain' => 'services', 'domain_label' => 'Servicios'],
        'get_routine' => ['domain' => 'services', 'domain_label' => 'Servicios'],
        'list_supply_items' => ['domain' => 'inventory', 'domain_label' => 'Inventario'],
        'list_catalog_items' => ['domain' => 'articles', 'domain_label' => 'Artículos'],
        'search_assets' => ['domain' => 'articles', 'domain_label' => 'Artículos'],
        'list_clients' => ['domain' => 'clients', 'domain_label' => 'Clientes'],
        'get_client_detail' => ['domain' => 'clients', 'domain_label' => 'Clientes'],
        'list_sites' => ['domain' => 'clients', 'domain_label' => 'Clientes'],
        'list_invoices' => ['domain' => 'billing', 'domain_label' => 'Facturas'],
        'list_audit_entries' => ['domain' => 'audit', 'domain_label' => 'Auditoría'],
        'predict_equipment_failures' => ['domain' => 'predictive', 'domain_label' => 'Predicción'],
        'predict_client_demand' => ['domain' => 'predictive', 'domain_label' => 'Predicción'],
        'get_equipment_health' => ['domain' => 'predictive', 'domain_label' => 'Predicción'],
        'list_failure_modes' => ['domain' => 'predictive', 'domain_label' => 'Predicción'],
        'get_operational_kpis' => ['domain' => 'services', 'domain_label' => 'Servicios'],
    ];

    public function __construct(
        private readonly AiToolRegistry $registry,
        private readonly AiToolAuthorizer $authorizer,
    ) {}

    /**
     * @return list<array{
     *   name: string,
     *   description: string,
     *   domain: string,
     *   domain_label: string,
     *   required_permissions: list<string>,
     *   available: bool,
     *   parameters: array<string, mixed>,
     *   mode: 'read'
     * }>
     */
    public function describeForUser(User $user, int $companyId): array
    {
        $rows = [];
        foreach ($this->registry->all() as $tool) {
            $rows[] = $this->describeTool($tool, $user, $companyId);
        }

        usort($rows, function (array $a, array $b): int {
            $domainCmp = strcmp($a['domain_label'], $b['domain_label']);
            if ($domainCmp !== 0) {
                return $domainCmp;
            }

            return strcmp($a['name'], $b['name']);
        });

        return $rows;
    }

    /**
     * @return array{
     *   name: string,
     *   description: string,
     *   domain: string,
     *   domain_label: string,
     *   required_permissions: list<string>,
     *   available: bool,
     *   parameters: array<string, mixed>,
     *   mode: 'read'
     * }
     */
    public function describeTool(AiTool $tool, User $user, int $companyId): array
    {
        $meta = self::META[$tool->name()] ?? [
            'domain' => 'general',
            'domain_label' => 'General',
        ];

        return [
            'name' => $tool->name(),
            'description' => $tool->description(),
            'domain' => $meta['domain'],
            'domain_label' => $meta['domain_label'],
            'required_permissions' => $tool->requiredPermissions(),
            'available' => $this->authorizer->canUseTool($user, $companyId, $tool),
            'parameters' => $tool->parametersSchema(),
            'mode' => 'read',
        ];
    }
}
