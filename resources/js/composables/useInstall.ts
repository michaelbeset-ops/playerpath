import { useAppMode } from '@/composables/useAppMode';
import { computed, onMounted, onUnmounted, ref } from 'vue';

/**
 * "Zet op je beginscherm", op één plek.
 *
 * Android en desktop-Chrome geven `beforeinstallprompt`; die vangen we op en
 * tonen dan een echte knop. iOS heeft dat niet: daar staan de twee stappen
 * (Deel → Zet op beginscherm). Als het al een app is, valt er niets te doen.
 *
 * Gedeeld door de balk onderin (InstallPrompt), de hulppagina en de
 * kind-link, zodat die drie dezelfde knop en dezelfde stappen tonen.
 */
export function useInstall() {
    const { isApp } = useAppMode();
    const gebeurtenis = ref<any>(null);

    const isIos = computed(
        () => typeof navigator !== 'undefined' && /iPad|iPhone|iPod/.test(navigator.userAgent) && !(window as Window & { MSStream?: unknown }).MSStream,
    );

    /** Er valt iets te tonen: een knop, of de stappen op iOS. */
    const kan = computed(() => !isApp.value && (gebeurtenis.value !== null || isIos.value));

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

    return { isApp, isIos, kan, kanKnop, installeer };
}
