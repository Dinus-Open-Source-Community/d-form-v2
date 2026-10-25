import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import eslint from 'vite-plugin-eslint2';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import path from 'path';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    // Untuk akses dari perangkat lain di LAN/HP, isi VITE_DEV_HOST di .env (mis. 192.168.0.37).
    // Kosong = perilaku lama (localhost).
    const devHost = env.VITE_DEV_HOST || 'localhost';

    return {
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
    // Bundle SSR self-contained: image prod tidak menyimpan node_modules saat runtime.
    ssr: {
        noExternal: true,
    },
    build: {
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
        // Halaman di :3000 memuat aset dari :5173 (origin berbeda) — izinkan saat dev.
        cors: true,
        origin: `http://${devHost}:5173`,
        hmr: {
            host: devHost,
        },
        watch: {
            usePolling: true,
            // Artefak stress test (stream.ndjson puluhan MB) tidak boleh memicu HMR:
            // polling file itu menghabiskan CPU dan mencemari hasil benchmark.
            ignored: ['**/scripts/stress/**', '**/storage/**', '**/vendor/**', '**/node_modules/**'],
        },
    },
    };
});
