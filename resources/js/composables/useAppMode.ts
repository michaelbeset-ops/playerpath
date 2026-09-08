import { onBeforeUnmount, onMounted, readonly, ref } from 'vue';

/**
 * Draait dit als geïnstalleerde app, of in een browsertabblad?
 *
 * Dat verschil bepaalt waar het menu staat: in een tabblad heb je de knoppen
 * van de browser onderin en hoort het menu bovenaan; als app is de onderkant
 * van het scherm juist het enige wat je met een duim comfortabel raakt.
 *
 * Drie dingen die deze detectie moeten kloppen:
 *
 * 1. **`display-mode` dekt Android, desktop en iOS vanaf 16.4.** Oudere iOS
 *    kent die media-query niet en heeft alleen `navigator.standalone`; die
 *    staat er daarom naast. Zonder dat valt precies de groep af die de app het
 *    vaakst op het beginscherm zet.
 * 2. **`minimal-ui` en `fullscreen` tellen mee.** Dat is nog steeds "als app
 *    geopend", alleen met een andere schil eromheen.
 * 3. **De klasse `pp-app` staat al vóór het eerste beeld op `<html>`** (zie
 *    app.blade.php). Deze composable leest hem alleen terug en luistert of hij
 *    verandert. Zou dit hier pas bepaald worden, dan zie je bij het openen van
 *    de app een fractie van een seconde de verkeerde balk.
 */
const APP_KLASSE = 'pp-app';

export function useAppMode() {
    const isApp = ref(false);

    let media: MediaQueryList | null = null;

    const meet = () => {
        if (typeof window === 'undefined') {
            return;
        }

        const standalone =
            window.matchMedia('(display-mode: standalone)').matches ||
            window.matchMedia('(display-mode: minimal-ui)').matches ||
            window.matchMedia('(display-mode: fullscreen)').matches ||
            // Oud-iOS: geen display-mode, wel deze vlag.
            (window.navigator as Navigator & { standalone?: boolean }).standalone === true;

        isApp.value = standalone;
        document.documentElement.classList.toggle(APP_KLASSE, standalone);
    };

    onMounted(() => {
        // De klasse is al gezet vóór het eerste beeld; dit houdt hem bij als
        // iemand de app vanuit de browser opent zonder de pagina te herladen.
        isApp.value = document.documentElement.classList.contains(APP_KLASSE);

        media = window.matchMedia('(display-mode: standalone)');
        media.addEventListener('change', meet);

        meet();
    });

    onBeforeUnmount(() => media?.removeEventListener('change', meet));

    return { isApp: readonly(isApp) };
}
