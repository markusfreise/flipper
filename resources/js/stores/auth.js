import { defineStore } from 'pinia';
import { api, csrfCookie } from '../api/client';

export const useAuthStore = defineStore('auth', {
    state: () => ({ user: null, loaded: false }),
    actions: {
        async ensureLoaded() {
            if (this.loaded) return;
            try {
                const { data } = await api.get('/me');
                this.user = data.user;
            } catch {
                this.user = null;
            }
            this.loaded = true;
        },
        async login(email, password) {
            await csrfCookie();
            const { data } = await api.post('/login', { email, password });
            this.user = data.user;
            this.loaded = true;
        },
        async logout() {
            try {
                await api.post('/logout');
            } finally {
                this.user = null;
            }
        },
    },
});
