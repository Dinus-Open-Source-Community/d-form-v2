// Entrypoint SSR tetap JS agar sinkron dengan `resources/js/app.js`; `vite.config.js`
// mengunci path `resources/js/ssr.js` (AUDIT-P3-009 follow-up: migrasi .ts bersama).
import createServer from '@inertiajs/vue3/server';
import { createInertiaApp } from '@inertiajs/vue3';
import { renderToString } from 'vue/server-renderer';
import { createSSRApp, h } from 'vue';

const lazyPageModules = import.meta.glob('./pages/**/*.vue');

// `v-focus` dipakai di input inti; tanpa registrasi, resolveDirective bernilai undefined
// dan render SSR melempar error. Versi ini hanya menyediakan `getSSRProps` (tanpa DOM).
const ssrFocusDirective = { getSSRProps: () => ({}) };

/**
 * Render satu halaman Inertia menjadi HTML+head di server (dipakai `npm run build:ssr`).
 * Tanpa `resources/js/bootstrap.js` (menyentuh `window`) dan tanpa state browser apa pun.
 */
createServer((page) =>
    createInertiaApp({
        page,
        render: renderToString,
        resolve: (name) => {
            const pageModule = lazyPageModules[`./pages/${name}.vue`];
            if (!pageModule) throw new Error(`Halaman Inertia tidak ditemukan: ${name}`);
            return pageModule();
        },
        // Directive `focus` versi no-op (lihat `ssrFocusDirective`); hook toast global tidak didaftarkan karena butuh DOM.
        setup: ({ App, props, plugin }) =>
            createSSRApp({ render: () => h(App, props) })
                .use(plugin)
                .directive('focus', ssrFocusDirective),
    })
);
