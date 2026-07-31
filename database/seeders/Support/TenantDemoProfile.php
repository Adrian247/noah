<?php

namespace Database\Seeders\Support;

use App\Enums\MembershipRole;

/**
 * Perfil de datos demo para un tenant (empresa cliente de plataforma).
 */
final class TenantDemoProfile
{
    /**
     * @param  list<array{email: string, name: string, role: MembershipRole, portal_client?: bool}>  $staff
     */
    public function __construct(
        public readonly string $companyName,
        public readonly string $companyLegalName,
        public readonly string $siteName,
        public readonly string $clientCode,
        public readonly string $clientTradeName,
        public readonly string $clientLegalName,
        public readonly string $catalogCode,
        public readonly string $catalogName,
        public readonly string $assetTag,
        public readonly string $assetSerial,
        public readonly array $staff,
    ) {}

    public static function mein(): self
    {
        return new self(
            companyName: 'Mein Company',
            companyLegalName: 'Mein Company S.A. de C.V.',
            siteName: 'Centro de servicio Mein',
            clientCode: 'MEIN-CLI-001',
            clientTradeName: 'Cliente final Mein',
            clientLegalName: 'Servicios Automotrices Mein S.A.',
            catalogCode: 'MEIN-L200-2018',
            catalogName: 'Mitsubishi L200 2018 — Mein',
            assetTag: 'MEIN-L200-01',
            assetSerial: 'MEINBJNKB40JH000001',
            staff: [
                ['email' => 'emilio.sanchez@mein-company.com', 'name' => 'Emilio Sánchez', 'role' => MembershipRole::Administrator],
                ['email' => 'misael.palos@mein-company.com', 'name' => 'Misael Palos', 'role' => MembershipRole::Technician],
                ['email' => 'claudio.rodriguez@mein-company.com', 'name' => 'Claudio Rodríguez', 'role' => MembershipRole::Supervisor],
                ['email' => 'elena.sanchez@mein-company.com', 'name' => 'Elena Sánchez', 'role' => MembershipRole::Billing],
                ['email' => 'cliente.portal@mein-company.com', 'name' => 'Cliente portal Mein', 'role' => MembershipRole::Client, 'portal_client' => true],
            ],
        );
    }

    public function isDomG(): bool
    {
        return $this->companyName === 'Dom-G';
    }

    public static function domG(): self
    {
        return new self(
            companyName: 'Dom-G',
            companyLegalName: 'Dom-G Servicios Industriales S.A. de C.V.',
            siteName: 'Planta Dom-G',
            clientCode: 'DOMG-CLI-001',
            clientTradeName: 'Cliente final Dom-G',
            clientLegalName: 'Grupo Dom-G Clientes S.A.',
            catalogCode: 'DOMG-L200-2019',
            catalogName: 'Mitsubishi L200 2019 — Dom-G',
            assetTag: 'DOMG-L200-01',
            assetSerial: 'DOMGBJNKB40JH000002',
            staff: [
                ['email' => 'gilberto-dominguez@dom-g.com', 'name' => 'Gilberto Domínguez', 'role' => MembershipRole::Administrator],
                ['email' => 'technician@dom-g.com', 'name' => 'Técnico Dom-G', 'role' => MembershipRole::Technician],
                ['email' => 'gilberto-sanchez@dom-g.com', 'name' => 'Gilberto Sánchez', 'role' => MembershipRole::Supervisor],
                ['email' => 'luis-olvera@dom-g.com', 'name' => 'Luis Olvera', 'role' => MembershipRole::Billing],
                ['email' => 'cliente.portal@dom-g.com', 'name' => 'Cliente portal Dom-G', 'role' => MembershipRole::Client, 'portal_client' => true],
            ],
        );
    }
}
