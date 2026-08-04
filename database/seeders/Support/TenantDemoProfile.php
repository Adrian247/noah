<?php

namespace Database\Seeders\Support;

use App\Enums\MembershipRole;
use App\Support\DemoAccounts;

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
            siteName: '',
            clientCode: 'MEIN-INTERNO',
            clientTradeName: 'Interno',
            clientLegalName: 'Trabajos internos Mein Company',
            catalogCode: '',
            catalogName: '',
            assetTag: '',
            assetSerial: '',
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

    public function isSandbox(): bool
    {
        return $this->companyName === 'Sandbox';
    }

    public function isVirginTenant(): bool
    {
        return ! $this->isSandbox();
    }

    /**
     * Tenant demo con playground operativo (catálogos, rutina demo).
     */
    public static function sandbox(): self
    {
        return new self(
            companyName: 'Sandbox',
            companyLegalName: 'Empresa sandbox Phoenix S.A. de C.V.',
            siteName: 'Sitio demo Sandbox',
            clientCode: 'SANDBOX-CLI-001',
            clientTradeName: 'Cliente demo',
            clientLegalName: 'Cliente demo Sandbox S.A. de C.V.',
            catalogCode: 'SBX-L200-2018',
            catalogName: 'Mitsubishi L200 2018 — Sandbox',
            assetTag: 'SBX-L200-01',
            assetSerial: 'SBXBJNKB40JH000001',
            staff: [
                [
                    'email' => DemoAccounts::DEFAULT_LOGIN_EMAIL,
                    'name' => 'Administrador Sandbox',
                    'role' => MembershipRole::Administrator,
                ],
                [
                    'email' => 'technician@sandbox-demo.com',
                    'name' => 'Técnico Sandbox',
                    'role' => MembershipRole::Technician,
                ],
                [
                    'email' => 'supervisor@sandbox-demo.com',
                    'name' => 'Supervisor Sandbox',
                    'role' => MembershipRole::Supervisor,
                ],
                [
                    'email' => 'billing@sandbox-demo.com',
                    'name' => 'Facturación Sandbox',
                    'role' => MembershipRole::Billing,
                ],
                [
                    'email' => 'cliente.portal@sandbox-demo.com',
                    'name' => 'Cliente portal Sandbox',
                    'role' => MembershipRole::Client,
                    'portal_client' => true,
                ],
            ],
        );
    }

    public static function domG(): self
    {
        return new self(
            companyName: 'Dom-G',
            companyLegalName: 'Dom-G Servicios Industriales S.A. de C.V.',
            siteName: '',
            clientCode: 'DOMG-INTERNO',
            clientTradeName: 'Interno',
            clientLegalName: 'Trabajos internos Dom-G',
            catalogCode: '',
            catalogName: '',
            assetTag: '',
            assetSerial: '',
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
