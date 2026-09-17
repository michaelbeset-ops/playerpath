import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

// Eigen omgevingsvariabelen; import.meta.env en glob komen uit vite/client.
declare global {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }
}

const appName = import.meta.env.VITE_APP_NAME || 'PlayerPath';

// In het tabblad staat de naam van de school als die een eigen huisstijl heeft;
// PlayerPath alleen als terugval (en op de openbare kaart, die geen huisstijl krijgt).
type MetMerk = { branding?: { name?: string | null } | null };
let merkNaam: string | null = null;
const zetMerk = (props: unknown) => {
    merkNaam = (props as MetMerk | undefined)?.branding?.name || null;
};
router.on('navigate', (event) => zetMerk(event.detail.page.props));

createInertiaApp({
    title: (title) => `${title} - ${merkNaam ?? appName}`,
    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        zetMerk(props.initialPage.props);
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
