// import './bootstrap';
// import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
// import { getTheme, initTheme, setTheme } from './utils/theme';

// document.addEventListener('DOMContentLoaded', () => {
//     initTheme();
// });

// document.addEventListener('alpine:init', function () {
//     Alpine.store('themeController', {
//         init() {
//             this.active = getTheme();
//         },

//         active: '',

//         toggle() {
//             if (this.active === 'light') {
//                 setTheme('dark');
//                 this.active = 'dark';
//             } else if (this.active === 'dark') {
//                 setTheme('light');
//                 this.active = 'light';
//             }
//         },
//     });
// });

// Livewire.start();

import './bootstrap';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';

createInertiaApp({
    resolve: (name) => {
        const pages = import.meta.glob('./pages/**/*.vue', { eager: true });
        return pages[`./pages/${name}.vue`];
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
});
