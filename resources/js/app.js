import './bootstrap';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { useGlobalHttpStatusToast } from '@/hooks/useGlobalHttpStatusToast';
import { useAuthHistoryGuard } from '@/hooks/useAuthHistoryGuard';

useGlobalHttpStatusToast();
useAuthHistoryGuard();

const pages = import.meta.glob('./pages/**/*.vue');

createInertiaApp({
    progress: {
        delay: 180,
        color: '#FFD84D',
        includeCSS: true,
        showSpinner: false,
    },
    resolve: (name) => {
        const page = pages[`./pages/${name}.vue`];
        if (!page) throw new Error(`Halaman Inertia tidak ditemukan: ${name}`);
        return page();
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .directive('focus', {
                mounted: (el, binding) => {
                    if (binding.value) el.focus();
                },
            })
            .mount(el);
    },
});
