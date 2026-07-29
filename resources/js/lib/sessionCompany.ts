import { getCompanyId } from '@/api/client';
import type { CompanyOption } from '@/stores/auth';

/** Usuario del portal B2B (no personal operativo del tenant). */
export function isPortalClientMembership(company: CompanyOption | null | undefined): boolean {
    return company?.role === 'client' && company.client_id != null;
}

/** Acceso a administración de la empresa (incluye asunción de plataforma). */
export function hasCompanyAdministratorAccess(role: string | undefined): boolean {
    return role === 'administrator' || role === 'platform_operator';
}

export function preferredCompany(companies: CompanyOption[]): CompanyOption | undefined {
    if (companies.length === 0) {
        return undefined;
    }

    const saved = getCompanyId();
    if (saved) {
        const match = companies.find((c) => String(c.id) === saved);
        if (match) {
            return match;
        }
    }

    const staff = companies.find((c) => !isPortalClientMembership(c));
    return staff ?? companies[0];
}

export function resolveActiveCompany(companies: CompanyOption[]): CompanyOption | undefined {
    return preferredCompany(companies);
}

export function postLoginRoute(companies: CompanyOption[]): { name: string } {
    const company = preferredCompany(companies);
    if (company && isPortalClientMembership(company)) {
        return { name: 'portal-invoices' };
    }

    return { name: 'dashboard' };
}
