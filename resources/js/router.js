import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from './stores/auth';

export const routes = [
    { path: '/login', name: 'login', component: () => import('./views/LoginView.vue'), meta: { guest: true } },
    { path: '/', name: 'listings', component: () => import('./views/ListingsView.vue') },
    { path: '/inserat/:id', name: 'wizard', component: () => import('./views/WizardView.vue'), props: true },
    { path: '/:pathMatch(.*)*', redirect: '/' },
];

export const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior: () => ({ top: 0 }),
});

router.beforeEach(async (to) => {
    const auth = useAuthStore();
    await auth.ensureLoaded();
    if (!to.meta.guest && !auth.user) {
        return { name: 'login', query: to.fullPath !== '/' ? { next: to.fullPath } : {} };
    }
    if (to.meta.guest && auth.user) {
        return { name: 'listings' };
    }
});
