import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { getToken } from '@/api/client';
import { applyLoginTheme, applyStoredThemeForApp } from '@/lib/theme';
import {
    hasCompanyAdministratorAccess,
    isPortalClientMembership,
    postLoginRoute,
    resolveActiveCompany,
} from '@/lib/sessionCompany';
import AppShell from '@/layouts/AppShell.vue';

const router = createRouter({
    history: createWebHistory(),
    routes: [
        {
            path: '/login',
            name: 'login',
            component: () => import('@/pages/LoginPage.vue'),
            meta: { guest: true },
        },
        {
            path: '/',
            redirect: '/app/dashboard',
        },
        {
            path: '/portal',
            component: () => import('@/layouts/ClientPortalShell.vue'),
            meta: { requiresAuth: true, clientPortal: true },
            children: [
                { path: '', redirect: '/portal/invoices' },
                {
                    path: 'invoices',
                    name: 'portal-invoices',
                    component: () => import('@/pages/portal/PortalInvoicesPage.vue'),
                },
                {
                    path: 'routines',
                    name: 'portal-routines',
                    component: () => import('@/pages/portal/PortalRoutinesPage.vue'),
                },
                {
                    path: 'routines/:id',
                    name: 'portal-routine-detail',
                    component: () => import('@/pages/portal/PortalRoutineDetailPage.vue'),
                },
            ],
        },
        {
            path: '/app',
            component: AppShell,
            meta: { requiresAuth: true },
            children: [
                {
                    path: 'dashboard',
                    name: 'dashboard',
                    component: () => import('@/pages/DashboardPage.vue'),
                    meta: { title: 'Dashboard' },
                },
                {
                    path: 'routines',
                    name: 'routines',
                    component: () => import('@/pages/RoutinesPage.vue'),
                    meta: { title: 'Rutinas', moduleId: 'routines' },
                },
                {
                    path: 'validation',
                    name: 'validation-queue',
                    component: () => import('@/pages/ValidationQueuePage.vue'),
                    meta: {
                        title: 'Cola de validación',
                        moduleId: 'routines',
                        requiresPermission: 'routines.validate',
                    },
                },
                {
                    path: 'routines/types',
                    name: 'routine-types',
                    component: () => import('@/pages/RoutineTypesPage.vue'),
                    meta: { title: 'Tipos de rutina', moduleId: 'design_routine_types' },
                },
                {
                    path: 'routines/:id',
                    name: 'routine-detail',
                    component: () => import('@/pages/RoutineDetailPage.vue'),
                    meta: { title: 'Detalle rutina', moduleId: 'routines' },
                },
                {
                    path: 'billing',
                    name: 'billing',
                    component: () => import('@/pages/InvoicesPage.vue'),
                    meta: { title: 'Facturación', moduleId: 'billing' },
                },
                {
                    path: 'billing/settings',
                    redirect: { name: 'settings', hash: '#facturacion' },
                },
                {
                    path: 'billing/:id',
                    name: 'billing-detail',
                    component: () => import('@/pages/InvoiceDetailPage.vue'),
                    meta: { title: 'Detalle factura', moduleId: 'billing' },
                },
                {
                    path: 'catalog/items',
                    name: 'catalog-items',
                    component: () => import('@/pages/CatalogItemsPage.vue'),
                    meta: { title: 'Catálogo de equipos', moduleId: 'catalog_items' },
                },
                {
                    path: 'catalog/items/types',
                    name: 'catalog-equipment-types',
                    component: () => import('@/pages/EquipmentTypesPage.vue'),
                    meta: { title: 'Tipos de equipo', moduleId: 'catalog_items' },
                },
                {
                    path: 'catalog/equipment-types',
                    redirect: { name: 'catalog-equipment-types' },
                },
                {
                    path: 'inventory',
                    name: 'inventory',
                    component: () => import('@/pages/SuppliesPage.vue'),
                    meta: { title: 'Inventario', moduleId: 'inventory' },
                },
                {
                    path: 'inventory/types',
                    name: 'inventory-supply-types',
                    component: () => import('@/pages/SupplyTypesPage.vue'),
                    meta: { title: 'Tipos de insumo', moduleId: 'inventory' },
                },
                {
                    path: 'catalog/supplies',
                    redirect: { name: 'inventory' },
                },
                {
                    path: 'catalog/supplies/types',
                    redirect: { name: 'inventory-supply-types' },
                },
                {
                    path: 'catalog/suppliers',
                    name: 'catalog-suppliers',
                    component: () => import('@/pages/SuppliersPage.vue'),
                    meta: { title: 'Proveedores', moduleId: 'catalog_suppliers' },
                },
                {
                    path: 'catalog/clients',
                    name: 'catalog-clients',
                    component: () => import('@/pages/ClientsPage.vue'),
                    meta: { title: 'Clientes', moduleId: 'clients' },
                },
                {
                    path: 'clients',
                    redirect: { name: 'catalog-clients' },
                },
                {
                    path: 'catalog/supply-types',
                    redirect: { name: 'inventory-supply-types' },
                },
                {
                    path: 'sites',
                    name: 'sites',
                    component: () => import('@/pages/SitesPage.vue'),
                    meta: { title: 'Sitios', moduleId: 'sites' },
                },
                {
                    path: 'assets',
                    name: 'assets',
                    component: () => import('@/pages/AssetsPage.vue'),
                    meta: { title: 'Activos', moduleId: 'assets' },
                },
                {
                    path: 'design/routine-types',
                    redirect: { name: 'routine-types' },
                },
                {
                    path: 'design/forms',
                    name: 'forms-list',
                    component: () => import('@/pages/FormsListPage.vue'),
                    meta: { title: 'Formularios', moduleId: 'design_forms' },
                },
                {
                    path: 'design/forms/settings',
                    name: 'form-field-config',
                    component: () => import('@/pages/FormFieldConfigPage.vue'),
                    meta: { title: 'Configuración de campos', moduleId: 'design_forms' },
                },
                {
                    path: 'design/forms/:id',
                    name: 'form-designer',
                    component: () => import('@/pages/FormDesignerPage.vue'),
                    meta: { title: 'Diseñador de formulario', moduleId: 'design_forms' },
                },
                {
                    path: 'design/reports',
                    name: 'reports-list',
                    component: () => import('@/pages/ReportsListPage.vue'),
                    meta: { title: 'Reportes', moduleId: 'design_reports' },
                },
                {
                    path: 'design/reports/settings',
                    name: 'report-section-config',
                    component: () => import('@/pages/ReportSectionConfigPage.vue'),
                    meta: { title: 'Configuración de reportes', moduleId: 'design_reports' },
                },
                {
                    path: 'design/reports/:id',
                    name: 'report-designer',
                    component: () => import('@/pages/ReportDesignerPage.vue'),
                    meta: { title: 'Diseñador de reporte', moduleId: 'design_reports' },
                },
                {
                    path: 'design/workflows',
                    name: 'workflows-list',
                    component: () => import('@/pages/WorkflowsListPage.vue'),
                    meta: { title: 'Workflows', moduleId: 'design_workflows' },
                },
                {
                    path: 'design/workflows/:id',
                    name: 'workflow-designer',
                    component: () => import('@/pages/WorkflowDesignerPage.vue'),
                    meta: { title: 'Diseñador workflow', moduleId: 'design_workflows' },
                },
                {
                    path: 'audit',
                    name: 'audit',
                    component: () => import('@/pages/AuditPage.vue'),
                    meta: { title: 'Auditoría', moduleId: 'audit' },
                },
                {
                    path: 'integrations',
                    name: 'integrations',
                    component: () => import('@/pages/IntegrationsPage.vue'),
                    meta: { title: 'Integraciones', moduleId: 'integrations' },
                },
                {
                    path: 'insights',
                    redirect: { name: 'dashboard' },
                },
                {
                    path: 'admin/users',
                    name: 'company-users',
                    component: () => import('@/pages/CompanyUsersPage.vue'),
                    meta: { title: 'Usuarios', requiresRole: 'administrator' },
                },
                {
                    path: 'platform/tenants',
                    name: 'platform-tenants',
                    component: () => import('@/pages/PlatformTenantsPage.vue'),
                    meta: { title: 'Clientes de plataforma', requiresPlatformAdmin: true },
                },
                {
                    path: 'platform/role-permissions',
                    name: 'platform-role-permissions',
                    component: () => import('@/pages/PlatformRolePermissionsPage.vue'),
                    meta: { title: 'Roles de plataforma', requiresPlatformAdmin: true },
                },
                {
                    path: 'platform/ai-settings',
                    redirect: { name: 'settings', hash: '#ia' },
                },
                {
                    path: 'admin/portal',
                    name: 'portal-settings',
                    component: () => import('@/pages/PortalSettingsPage.vue'),
                    meta: { title: 'Portal login', requiresRole: 'administrator' },
                },
                {
                    path: 'settings',
                    name: 'settings',
                    component: () => import('@/pages/SettingsPage.vue'),
                    meta: { title: 'Configuración' },
                },
            ],
        },
    ],
});

router.beforeEach(async (to) => {
    if (to.meta.guest) {
        applyLoginTheme();
    } else {
        applyStoredThemeForApp();
    }

    const auth = useAuthStore();
    const needsAuth = to.matched.some((record) => record.meta.requiresAuth);

    if (to.meta.guest) {
        if (getToken()) {
            const ok = await auth.ensureSession();
            if (ok) {
                const company = resolveActiveCompany(auth.companies);
                if (company && isPortalClientMembership(company)) {
                    return { name: 'portal-invoices' };
                }
                return { name: 'dashboard' };
            }
        }
        return;
    }

    if (needsAuth) {
        const ok = await auth.ensureSession();
        if (!ok) {
            return { name: 'login' };
        }
    }

    if (to.path.startsWith('/app')) {
        const company = resolveActiveCompany(auth.companies);
        if (company && isPortalClientMembership(company)) {
            return { name: 'portal-invoices' };
        }
    }

    if (to.matched.some((record) => record.meta.clientPortal)) {
        const company = resolveActiveCompany(auth.companies);
        if (company && !isPortalClientMembership(company)) {
            return { name: 'dashboard' };
        }
    }

    const requiredModule = to.meta.moduleId as string | undefined;
    if (requiredModule) {
        const company = resolveActiveCompany(auth.companies);
        const modules = company?.modules ?? {};
        const access = modules[requiredModule];
        if (!access?.visible) {
            return { name: 'dashboard' };
        }
    }

    const requiredRole = to.meta.requiresRole as string | undefined;
    if (requiredRole) {
        const company = resolveActiveCompany(auth.companies);
        const role = company?.role;
        const allowed =
            requiredRole === 'administrator'
                ? hasCompanyAdministratorAccess(role)
                : role === requiredRole;
        if (!allowed) {
            return { name: 'dashboard' };
        }
    }

    if (to.meta.requiresPlatformAdmin && !auth.user?.is_platform_admin) {
        return { name: 'dashboard' };
    }

    const requiredPermission = to.meta.requiresPermission as string | undefined;
    if (requiredPermission) {
        const company = resolveActiveCompany(auth.companies);
        const permissions = company?.permissions ?? [];
        const elevated =
            requiredPermission === 'clients.view' && permissions.includes('clients.manage');
        if (!permissions.includes(requiredPermission) && !elevated) {
            return { name: 'dashboard' };
        }
    }
});

export default router;
