<?php

namespace App\Support;

use App\Models\Company;

/**
 * Empresa contenedora de artículos de sistema (catálogo global de plataforma).
 */
class PlatformCatalogCompany
{
    public const NAME = 'Catálogo de plataforma Pyro';

    public function id(): int
    {
        $configured = config('phoenix.platform_catalog_company_id');
        if ($configured !== null && $configured !== '') {
            return (int) $configured;
        }

        $id = Company::query()->where('name', self::NAME)->value('id');
        if ($id === null) {
            throw new \RuntimeException('No está configurada la empresa de catálogo de plataforma.');
        }

        return (int) $id;
    }

    public function company(): Company
    {
        return Company::query()->where('name', self::NAME)->firstOrFail();
    }
}
