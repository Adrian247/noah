import { createRouter, createWebHistory } from 'vue-router';
import { getToken } from '@/api/client';
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
                    meta: { title: 'Rutinas' },
                },
                {
                    path: 'routines/:id',
                    name: 'routine-detail',
                    component: () => import('@/pages/RoutineDetailPage.vue'),
                    meta: { title: 'Detalle rutina' },
                },
                {
                    path: 'billing',
                    name: 'billing',
                    component: () => import('@/pages/InvoicesPage.vue'),
                    meta: { title: 'Facturación' },
                },
                {
                    path: 'catalog/items',
                    name: 'catalog-items',
                    component: () => import('@/pages/CatalogItemsPage.vue'),
                    meta: { title: 'Catálogo de equipos' },
                },
                {
                    path: 'catalog/supplies',
                    name: 'catalog-supplies',
                    component: () => import('@/pages/SuppliesPage.vue'),
                    meta: { title: 'Insumos' },
                },
                {
                    path: 'assets',
                    name: 'assets',
                    component: () => import('@/pages/AssetsPage.vue'),
                    meta: { title: 'Activos' },
                },
                {
                    path: 'design/routine-types',
                    name: 'routine-types',
                    component: () => import('@/pages/RoutineTypesPage.vue'),
                    meta: { title: 'Tipos de rutina' },
                },
                {
                    path: 'design/forms',
                    name: 'forms-list',
                    component: () => import('@/pages/FormsListPage.vue'),
                    meta: { title: 'Formularios' },
                },
                {
                    path: 'design/forms/:id',
                    name: 'form-designer',
                    component: () => import('@/pages/FormDesignerPage.vue'),
                    meta: { title: 'Diseñador de formulario' },
                },
                {
                    path: 'design/reports',
                    name: 'reports-list',
                    component: () => import('@/pages/ReportsListPage.vue'),
                    meta: { title: 'Reportes' },
                },
                {
                    path: 'design/reports/:id',
                    name: 'report-designer',
                    component: () => import('@/pages/ReportDesignerPage.vue'),
                    meta: { title: 'Diseñador de reporte' },
                },
                {
                    path: 'audit',
                    name: 'audit',
                    component: () => import('@/pages/AuditPage.vue'),
                    meta: { title: 'Auditoría' },
                },
            ],
        },
    ],
});

router.beforeEach((to) => {
    const authed = Boolean(getToken());
    if (to.meta.requiresAuth && !authed) {
        return { name: 'login' };
    }
    if (to.meta.guest && authed) {
        return { name: 'dashboard' };
    }
});

export default router;
