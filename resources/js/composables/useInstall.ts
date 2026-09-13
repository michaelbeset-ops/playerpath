import { useAppMode } from '@/composables/useAppMode';
import { computed, onMounted, onUnmounted, ref } from 'vue';

/**
 * "Zet op je beginscherm", op één plek.
 *
 * Android en desktop-Chrome geven `beforeinstallprompt`; die vangen we op en
 * tonen dan een echte knop. Apple geeft websites geen enkele manier om die
 * vraag zelf te stellen: geen knop, geen systeemmelding. Op iPhone en iPad
 * zijn wij dus de enige die het kan zeggen, en dan moet het precies kloppen
 * voor het toestel en de browser:
 *
 * - **iPhone in Safari**: de deelknop staat onderin.
 * - **iPad**: de deelknop staat bovenin. iPadOS doet zich voor als een Mac,
 *   dus die herken je aan het aanraakscherm, niet aan de naam.
 * - **Chrome of Firefox op iOS**: de deelknop staat rechtsboven in de
 *   adresbalk.
 * - **Een ingebouwde browser** (WhatsApp, Instagram, Facebook, Snapchat):
 *   daar kan het niet. Eerst openen in Safari, en dat moet er dan staan.
 *   Dat is precies waar een kind-link terechtkomt als een ouder hem via
 *   WhatsApp stuurt.
 *
 * Als het al een app is, valt er niets te doen.
 *
 * Gedeeld door de balk onderin (InstallPrompt), de hulppagina en de
 * kind-link, zodat die dezelfde knop en dezelfde stappen tonen.
 */
export type InstallWeg = 'knop' | 'iphone' | 'ipad' | 'ios-andere-browser' | 'ingebouwd' | 'overig';

export function useInstall() {
    const { isApp } = useAppMode();
    const gebeurtenis = ref<any>(null);

    const ua = typeof navigator === 'undefined' ? '' : navigator.userAgent;
    const ipadOs = typeof navigator !== 'undefined' && navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1;

    const isIpad = /iPad/.test(ua) || ipadOs;
    const isIos = /iPhone|iPod/.test(ua) || isIpad;
    const ingebouwd = /FBAN|FBAV|FB_IAB|Instagram|Snapchat|TikTok|musical_ly|Line\/|WhatsApp|GSA\//i.test(ua) || (/Android/.test(ua) && /; wv\)/.test(ua));
    const iosAndereBrowser = isIos && /CriOS|FxiOS|EdgiOS|OPiOS/.test(ua);

    /** Welke uitleg hoort bij dit toestel. De echte knop wint altijd. */
    const weg = computed<InstallWeg>(() => {
        if (gebeurtenis.value !== null) {
            return 'knop';
        }
        if (ingebouwd) {
            return 'ingebouwd';
        }
        if (iosAndereBrowser) {
            return 'ios-andere-browser';
        }
        if (isIpad) {
            return 'ipad';
        }
        if (isIos) {
            return 'iphone';
        }
        return 'overig';
    });

    /** Er valt iets zinnigs te tonen: een knop, of stappen die op dit toestel echt werken. */
    const kan = computed(() => !isApp.value && weg.value !== 'overig');

    /** De echte knop (Android, desktop). */
    const kanKnop = computed(() => !isApp.value && gebeurtenis.value !== null);

    const onBeforeInstall = (event: Event) => {
        event.preventDefault();
        gebeurtenis.value = event;
    };

    /** Geeft 'accepted', 'dismissed' of null (geen knop beschikbaar). */
    const installeer = async (): Promise<string | null> => {
        const prompt = gebeurtenis.value;

        if (!prompt) {
            return null;
        }

        gebeurtenis.value = null;
        prompt.prompt();

        const keuze = await prompt.userChoice;

        return keuze?.outcome ?? null;
    };

    onMounted(() => window.addEventListener('beforeinstallprompt', onBeforeInstall));
    onUnmounted(() => window.removeEventListener('beforeinstallprompt', onBeforeInstall));

    return { isApp, isIos, isIpad, ingebouwd, weg, kan, kanKnop, installeer };
}
