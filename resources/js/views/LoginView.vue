<script setup>
import { ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { errorMessage } from '../api/client';

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();
const email = ref('');
const password = ref('');
const error = ref('');
const busy = ref(false);

async function submit() {
    error.value = '';
    busy.value = true;
    try {
        await auth.login(email.value.trim(), password.value);
        router.replace(typeof route.query.next === 'string' ? route.query.next : '/');
    } catch (e) {
        error.value = errorMessage(e, 'Anmelden hat nicht geklappt.');
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <div class="login">
        <div class="login__brand" aria-hidden="true">
            <svg viewBox="0 0 32 32" width="48" height="48"><rect width="32" height="32" rx="7" fill="currentColor" /><path d="M9 13h14l-1.5 11h-11z" fill="#fff" /><path d="M12 13a4 4 0 0 1 8 0" stroke="#fff" stroke-width="2" fill="none" /></svg>
        </div>
        <section class="card login__card">
            <div class="card__header">
                <div>
                    <h1 class="card__title heading-2">Flohmarkt</h1>
                    <p class="muted small">Aus Handyfotos ein fertiges Inserat.</p>
                </div>
            </div>
            <form class="card__body form" novalidate @submit.prevent="submit">
                <div class="form__group">
                    <label class="form__label" for="email">E-Mail</label>
                    <input id="email" v-model="email" class="form__input" type="email" autocomplete="username" inputmode="email" required>
                </div>
                <div class="form__group">
                    <label class="form__label" for="password">Passwort</label>
                    <input id="password" v-model="password" class="form__input" type="password" autocomplete="current-password" required>
                </div>
                <p v-if="error" class="form__alert" role="alert">{{ error }}</p>
                <button class="btn btn--primary btn--lg btn--block" type="submit" :disabled="busy || !email || !password">
                    <span v-if="busy" class="spinner" aria-hidden="true" />
                    Anmelden
                </button>
            </form>
        </section>
    </div>
</template>
