<script setup>
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
const router = useRouter();

async function logout() {
    await auth.logout();
    router.push({ name: 'login' });
}
</script>

<template>
    <header class="site-header">
        <div class="site-header__inner">
            <RouterLink to="/" class="site-header__brand">
                <span class="site-header__logo" aria-hidden="true">
                    <svg viewBox="0 0 32 32" width="28" height="28"><rect width="32" height="32" rx="7" fill="currentColor" /><path d="M9 13h14l-1.5 11h-11z" fill="#fff" /><path d="M12 13a4 4 0 0 1 8 0" stroke="#fff" stroke-width="2" fill="none" /></svg>
                </span>
                Flohmarkt
            </RouterLink>
            <div class="site-header__user">
                <span class="site-header__name">{{ auth.user.name }}</span>
                <button type="button" class="btn btn--ghost btn--sm" @click="logout">Abmelden</button>
            </div>
        </div>
    </header>
</template>
