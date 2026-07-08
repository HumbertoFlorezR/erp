// import './bootstrap';
//import '../css/app.css';

//import { createApp, h } from 'vue';
//import { createInertiaApp } from '@inertiajs/vue3';
// 💡 LA IMPORTACIÓN QUE FALTA PARA ELIMINAR EL ERROR:
//import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

//createInertiaApp({
//    title: (title) => `${title} - ERP Global`,
//    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
//    setup({ el, App, props, plugin }) {
//        return createApp({ render: () => h(App, props) })
//            .use(plugin)
//            .mount(el);
//    },
//});

// import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

createInertiaApp({
    title: (title) => `${title} - ERP Global`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            // Agregamos un mixin global para que TODOS tus componentes reconozcan 'route' en el HTML automáticamente
            .mixin({ methods: { route: window.route } })
            .mount(el);
    },
});
