import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import eslint from 'vite-plugin-eslint2';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import path from 'path';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    // Set VITE_DEV_HOST to access the dev server from another device.
    const devHost = env.VITE_DEV_HOST || 'localhost';

    return {
        // Keep `/file-types/*.svg` available from both HMR and PHP public/.
        publicDir: 'public',
        resolve: {
            alias: {
                '@': path.resolve(__dirname, 'resources/js'),
            },
        },
        plugins: [
            wayfinder(),
            laravel({
                input: ['resources/css/app.css', 'resources/js/app.js'],
                ssr: 'resources/js/ssr.js',
                refresh: true,
            }),
            tailwindcss(),
            vue(),
            eslint(),
        ],
        // Keep the production SSR bundle self-contained.
        ssr: {
            noExternal: true,
        },
        build: {
            // Deploy static assets from Laravel public/ alongside public/build.
            copyPublicDir: false,
            rollupOptions: {
                output: {
                    manualChunks: {
                        'vendor-vue': ['vue'],
                        'vendor-inertia': ['@inertiajs/vue3'],
                    },
                },
            },
        },
        server: {
            host: '0.0.0.0',
            port: 5173,
            strictPort: true,
            // The app and Vite dev server use different origins in development.
            cors: true,
            origin: `http://${devHost}:5173`,
            hmr: {
                host: devHost,
            },
            watch: {
                usePolling: true,
                // Stress artifacts are large and must not trigger HMR polling.
                ignored: ['**/scripts/stress/**', '**/storage/**', '**/vendor/**', '**/node_modules/**'],
            },
        },
    };
});
