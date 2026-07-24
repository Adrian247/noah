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
