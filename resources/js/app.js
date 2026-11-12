// Entrypoint tetap JS: `vite.config.js` mengunci path `resources/js/app.js` sebagai input,
// jadi rename ke `app.ts` menunggu perubahan config build (AUDIT-P3-009 follow-up).
import './bootstrap';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { useGlobalHttpStatusToast } from '@/hooks/useGlobalHttpStatusToast';
import { useAuthHistoryGuard } from '@/hooks/useAuthHistoryGuard';
import { focusDirective } from '@/directives/focus';

useGlobalHttpStatusToast();
useAuthHistoryGuard();

const lazyPageModules = import.meta.glob('./pages/**/*.vue');

createInertiaApp({
    progress: {
        delay: 180,
        color: '#FFD84D',
        includeCSS: true,
        showSpinner: false,
    },
    resolve: (name) => {
        const pageModule = lazyPageModules[`./pages/${name}.vue`];
        if (!pageModule) throw new Error(`Halaman Inertia tidak ditemukan: ${name}`);
        return pageModule();
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .directive('focus', focusDirective)
            .mount(el);
    },
});
