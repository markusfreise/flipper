import axios from 'axios';

export const api = axios.create({
    baseURL: '/api/v1',
    withCredentials: true,
    withXSRFToken: true,
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
});

let onUnauthorized = () => {};
export function setUnauthorizedHandler(fn) {
    onUnauthorized = fn;
}

export function csrfCookie() {
    return axios.get('/sanctum/csrf-cookie', { withCredentials: true });
}

api.interceptors.response.use(
    (response) => response,
    async (error) => {
        const { response, config } = error;
        // 419: CSRF-Token abgelaufen → Cookie neu holen und einmal wiederholen
        if (response?.status === 419 && !config._retried) {
            config._retried = true;
            await csrfCookie();
            return api(config);
        }
        if (response?.status === 401 && !config.url?.endsWith('/me')) {
            onUnauthorized();
        }
        return Promise.reject(error);
    },
);

/** Verständliche deutsche Fehlermeldung aus einer Axios-Antwort. */
export function errorMessage(error, fallback = 'Das hat nicht geklappt. Bitte nochmal versuchen.') {
    const data = error?.response?.data;
    if (data?.errors) {
        const first = Object.values(data.errors)[0];
        if (Array.isArray(first) && first[0]) return first[0];
    }
    if (error?.response?.status === 413) return 'Die Fotos sind zusammen zu groß. Bitte weniger auf einmal hochladen.';
    if (data?.message && !/^Server Error$/i.test(data.message)) return data.message;
    if (!error?.response) return 'Keine Verbindung zum Server.';
    return fallback;
}
