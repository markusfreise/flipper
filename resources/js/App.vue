<script setup>
import { useRouter } from 'vue-router';
import { setUnauthorizedHandler } from './api/client';
import { useAuthStore } from './stores/auth';
import AppHeader from './components/AppHeader.vue';

const router = useRouter();
const auth = useAuthStore();

setUnauthorizedHandler(() => {
    auth.user = null;
    if (router.currentRoute.value.name !== 'login') router.push({ name: 'login' });
});
</script>

<template>
    <div class="app-root">
        <AppHeader v-if="auth.user" />
        <main id="main" class="page site-main">
            <RouterView />
        </main>
    </div>
</template>
