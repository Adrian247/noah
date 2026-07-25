import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { getToken, getCompanyId } from '@/api/client';
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
                    name: 'billing-settings',
                    component: () => import('@/pages/BillingSettingsPage.vue'),
                    meta: { title: 'Configuración facturación', moduleId: 'billing' },
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
                    path: 'catalog/supplies',
                    name: 'catalog-supplies',
                    component: () => import('@/pages/SuppliesPage.vue'),
                    meta: { title: 'Insumos', moduleId: 'catalog_supplies' },
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
                    name: 'routine-types',
                    component: () => import('@/pages/RoutineTypesPage.vue'),
                    meta: { title: 'Tipos de rutina', moduleId: 'design_routine_types' },
                },
                {
                    path: 'design/forms',
                    name: 'forms-list',
                    component: () => import('@/pages/FormsListPage.vue'),
                    meta: { title: 'Formularios', moduleId: 'design_forms' },
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
                    path: 'admin/users',
                    name: 'company-users',
                    component: () => import('@/pages/CompanyUsersPage.vue'),
                    meta: { title: 'Usuarios', requiresRole: 'administrator' },
                },
            ],
        },
    ],
});

router.beforeEach(async (to) => {
    const authed = Boolean(getToken());
    if (to.meta.requiresAuth && !authed) {
        return { name: 'login' };
    }
    if (to.meta.guest && authed) {
        return { name: 'dashboard' };
    }

    const requiredModule = to.meta.moduleId as string | undefined;
    if (requiredModule && authed) {
        const auth = useAuthStore();
        if (!auth.user) {
            try {
                await auth.fetchMe();
            } catch {
                return { name: 'login' };
            }
        }
        const companyId = getCompanyId();
        const company = auth.companies.find((c) => String(c.id) === companyId) ?? auth.companies[0];
        const modules = company?.modules ?? {};
        const access = modules[requiredModule];
        if (!access?.visible) {
            return { name: 'dashboard' };
        }
    }

    const requiredRole = to.meta.requiresRole as string | undefined;
    if (requiredRole && authed) {
        const auth = useAuthStore();
        if (!auth.user) {
            try {
                await auth.fetchMe();
            } catch {
                return { name: 'login' };
            }
        }
        const companyId = getCompanyId();
        const company = auth.companies.find((c) => String(c.id) === companyId) ?? auth.companies[0];
        if (company?.role !== requiredRole) {
            return { name: 'dashboard' };
        }
    }

    const requiredPermission = to.meta.requiresPermission as string | undefined;
    if (requiredPermission && authed) {
        const auth = useAuthStore();
        if (!auth.user) {
            try {
                await auth.fetchMe();
            } catch {
                return { name: 'login' };
            }
        }
        const companyId = getCompanyId();
        const company = auth.companies.find((c) => String(c.id) === companyId) ?? auth.companies[0];
        const permissions = company?.permissions ?? [];
        const elevated =
            requiredPermission === 'clients.view' && permissions.includes('clients.manage');
        if (!permissions.includes(requiredPermission) && !elevated) {
            return { name: 'dashboard' };
        }
    }
});

export default router;
