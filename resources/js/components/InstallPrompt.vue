<script setup lang="ts">
import { useAppMode } from '@/composables/useAppMode';
import { Download, Share, SquarePlus, X } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';

/**
 * "Zet PlayerPath op je beginscherm."
 *
 * Vier regels die dit dragelijk houden:
 *
 * 1. **Niet als het al een app is.** Wie hem al op zijn beginscherm heeft
 *    hoort dit nooit te zien (`useAppMode`).
 * 2. **Niet bij het eerste bezoek.** Pas vanaf het tweede bezoek (een andere
 *    dag), of na een paar minuten in de app. Wie net voor het eerst inlogt
 *    heeft andere dingen aan zijn hoofd.
 * 3. **Wegklikken is nee**, en dat onthouden we op dit apparaat. Een balk die
 *    elke week terugkomt is precies waarom mensen apps wantrouwen.
 * 4. **Android krijgt de echte knop, iOS de stappen.** Chrome/Android geeft
 *    `beforeinstallprompt`; Safari op iOS heeft dat niet, daar staat de weg
 *    beschreven: Deel → Zet op beginscherm.
 */
const AFGEWEZEN = 'playerpath.install-afgewezen';
const BEZOEKEN = 'playerpath.install.bezoeken';
const MINUTEN = 3;

const { isApp } = useAppMode();

const gebeurtenis = ref<any>(null);
const rijp = ref(false);
const afgewezen = ref(false);

const isIos = computed(() => /iPad|iPhone|iPod/.test(navigator.userAgent) && !(window as Window & { MSStream?: unknown }).MSStream);

const zichtbaar = computed(() => !isApp.value && !afgewezen.value && rijp.value && (gebeurtenis.value !== null || isIos.value));

const lees = (sleutel: string): string | null => {
    try {
        return localStorage.getItem(sleutel);
    } catch {
        return null;
    }
};

const schrijf = (sleutel: string, waarde: string) => {
    try {
        localStorage.setItem(sleutel, waarde);
    } catch {
        // Privémodus of geblokkeerde opslag: dan vragen we hooguit nog een keer.
    }
};

/** Het tweede bezoek op een andere dag telt; vandaag nog eens openen niet. */
const telBezoek = (): number => {
    const vandaag = new Date().toISOString().slice(0, 10);
    const dagen = (lees(BEZOEKEN) ?? '').split(',').filter(Boolean);

    if (!dagen.includes(vandaag)) {
        dagen.push(vandaag);
        schrijf(BEZOEKEN, dagen.slice(-10).join(','));
    }

    return dagen.length;
};

const onBeforeInstall = (event: Event) => {
    event.preventDefault();
    gebeurtenis.value = event;
};

const installeer = async () => {
    if (!gebeurtenis.value) {
        return;
    }

    const prompt = gebeurtenis.value;
    gebeurtenis.value = null;
    prompt.prompt();

    const keuze = await prompt.userChoice;

    if (keuze?.outcome === 'dismissed') {
        sluit();
    }
};

const sluit = () => {
    afgewezen.value = true;
    schrijf(AFGEWEZEN, new Date().toISOString());
};

let timer: ReturnType<typeof setTimeout> | null = null;

onMounted(() => {
    afgewezen.value = lees(AFGEWEZEN) !== null;
    window.addEventListener('beforeinstallprompt', onBeforeInstall);

    if (telBezoek() >= 2) {
        rijp.value = true;
    } else {
        timer = setTimeout(() => (rijp.value = true), MINUTEN * 60 * 1000);
    }
});

onUnmounted(() => {
    window.removeEventListener('beforeinstallprompt', onBeforeInstall);

    if (timer) {
        clearTimeout(timer);
    }
});
</script>

<template>
    <div
        v-if="zichtbaar"
        class="fixed inset-x-3 bottom-[calc(var(--pp-tabbar)+0.75rem)] z-50 rounded-xl border border-border bg-card p-3 shadow-lg sm:left-auto sm:w-96"
        role="dialog"
        aria-label="Zet op je beginscherm"
    >
        <div class="flex items-start gap-3">
            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                <Download class="size-4" />
            </span>

            <div class="min-w-0 flex-1">
                <p class="text-sm font-medium">Zet PlayerPath op je beginscherm</p>
                <p class="text-xs text-muted-foreground">Dan opent hij als een app, zonder adresbalk, met één tik.</p>

                <!-- iOS: geen knop mogelijk, wel de twee stappen -->
                <ol v-if="!gebeurtenis && isIos" class="mt-2 space-y-1 text-xs">
                    <li class="flex items-center gap-2">
                        <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-secondary text-[10px] font-semibold">1</span>
                        Tik onderin op <Share class="inline size-3.5" aria-label="Deel" /> <span class="font-medium">Deel</span>
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-secondary text-[10px] font-semibold">2</span>
                        Kies <SquarePlus class="inline size-3.5" aria-hidden="true" /> <span class="font-medium">Zet op beginscherm</span>
                    </li>
                </ol>
            </div>

            <button
                type="button"
                class="-m-1 flex size-9 shrink-0 items-center justify-center rounded-lg text-muted-foreground hover:text-foreground"
                aria-label="Niet nu"
                @click="sluit"
            >
                <X class="size-4" />
            </button>
        </div>

        <div v-if="gebeurtenis" class="mt-3 flex gap-2">
            <button type="button" class="h-11 flex-1 rounded-lg bg-primary px-3 text-sm font-semibold text-primary-foreground" @click="installeer">
                Op beginscherm zetten
            </button>
            <button type="button" class="h-11 rounded-lg border border-border px-3 text-sm font-medium" @click="sluit">Nee, bedankt</button>
        </div>
        <div v-else class="mt-3">
            <button type="button" class="h-11 w-full rounded-lg border border-border px-3 text-sm font-medium" @click="sluit">Begrepen</button>
        </div>
    </div>
</template>
