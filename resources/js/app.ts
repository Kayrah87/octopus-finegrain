import '../css/app.css';
import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';

createInertiaApp({
    title: (title) => (title ? `${title} · Finegrain` : 'Finegrain'),
    layout: (name) => (name.startsWith('auth/') ? null : AppLayout),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el!);
    },
    progress: { color: '#2a78d6' },
});
