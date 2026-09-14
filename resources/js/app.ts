import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

const appName = import.meta.env.VITE_APP_NAME || 'PlayerPath';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#22E06B',
    },
});

/*
 * De service worker. Alleen op https (of localhost), want anders staat hij de
 * browser niet toe - en achter een tunnel of op productie is dat altijd zo.
 *
 * De registratie staat bewust ná het opstarten van de app: mislukt hij, dan is
 * dat vervelend maar mag het de app niet tegenhouden.
 */
if ('serviceWorker' in navigator && (window.isSecureContext || location.hostname === 'localhost')) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            // Geen ramp: dan werkt de app gewoon zonder installeerbaarheid.
        });
    });
}

/*
 * Het menu laadt pagina's vooraf zodra je een link aanwijst of aantikt
 * (prefetch, vijf seconden bewaard). Dat maakt klikken direct. Maar wie iets
 * opslaat en daarna via het menu verdergaat, hoort de nieuwe stand te zien en
 * niet de pagina van vlak ervoor: bij elke wijziging gaat de voorraad weg.
 */
router.on('before', (event) => {
    if (event.detail.visit.method !== 'get') {
        router.flushAll();
    }
});
