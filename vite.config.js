import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/scss/site.scss', 'resources/js/main.js'],
            refresh: true,
        }),
        vue({
            template: { transformAssetUrls: { base: null, includeAbsolute: false } },
        }),
    ],
    css: {
        preprocessorOptions: {
            // batcave-ui liegt als Git-Submodul unter resources/batcave-ui
            scss: { loadPaths: ['resources'] },
        },
    },
    test: {
        environment: 'jsdom',
        include: ['resources/js/__tests__/**/*.test.js'],
    },
});
