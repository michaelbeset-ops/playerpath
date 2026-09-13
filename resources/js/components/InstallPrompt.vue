<script setup lang="ts">
import { useInstall } from '@/composables/useInstall';
import { usePage } from '@inertiajs/vue3';
import { Download, Share, SquarePlus, X } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';

/**
 * "Zet PlayerPath op je beginscherm."
 *
 * Iedereen hoort de app op zijn beginscherm te hebben: een ouder die hem
 * elke week opent, een trainer die na de training zijn rapporten invult,
 * een kind dat zijn kaart wil zien. Daarom komt de vraag vroeg en komt hij
 * terug, maar niet vervelend:
 *
 * 1. **Niet als het al een app is.** Wie hem al heeft ziet dit nooit
 *    (`useAppMode`).
 * 2. **Na een halve minuut, ook bij het eerste bezoek.** Wie net inlogt
 *    krijgt eerst zijn scherm; daarna komt de vraag.
 * 3. **Via de kind-link meteen**, en ook als het eerder is weggeklikt
 *    (`flash.kindWelkom`). De ouder heeft de link net naar dit apparaat
 *    gestuurd om precies dit te doen.
 * 4. **Wegklikken is "niet nu", en dat onthouden we een week.** Een balk
 *    die elke dag terugkomt leer je wegklikken; een balk die nooit meer
 *    komt haalt niemand over. Na "Nee, bedankt" op de echte knop blijft
 *    hij een maand weg.
 * 5. **Android krijgt de echte knop, iOS de stappen.** Chrome/Android geeft
 *    `beforeinstallprompt`; Safari op iOS heeft dat niet, daar staat de weg
 *    beschreven: Deel → Zet op beginscherm.
 */
const UITGESTELD = 'playerpath.install-uitgesteld';
const SECONDEN = 30;
const DAG = 24 * 60 * 60 * 1000;

const page = usePage();
const { isApp, isIos, kanKnop, installeer } = useInstall();

// Het welkom via de kind-link. Vastgehouden in een ref: de flash is bij de
// volgende klik weg, maar de vraag hoort te blijven staan tot hij beantwoord is.
const kind = ref(Boolean((page.props.flash as { kindWelkom?: boolean } | undefined)?.kindWelkom));

const rijp = ref(kind.value);
const uitgesteld = ref(false);

const zichtbaar = computed(() => !isApp.value && (kind.value || !uitgesteld.value) && rijp.value && (kanKnop.value || isIos.value || kind.value));

const titel = computed(() => (kind.value ? 'Zet je kaart op je beginscherm' : 'Zet PlayerPath op je beginscherm'));
const uitleg = computed(() =>
    kind.value
        ? 'Dan staat je kaart als app tussen je andere apps, en kun je hem altijd met één tik bekijken.'
        : 'Dan opent hij als een app, zonder adresbalk, met één tik. Zo gebruik je hem het makkelijkst.',
);

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
        // Privémodus of geblokkeerde opslag: dan vragen we het gewoon nog eens.
    }
};

const stelUit = (dagen: number) => {
    kind.value = false;
    uitgesteld.value = true;
    schrijf(UITGESTELD, String(Date.now() + dagen * DAG));
};

const klik = async () => {
    const uitkomst = await installeer();

    if (uitkomst === 'dismissed') {
        stelUit(30);
    } else {
        kind.value = false;
        uitgesteld.value = true;
    }
};

let timer: ReturnType<typeof setTimeout> | null = null;

onMounted(() => {
    const tot = Number(lees(UITGESTELD) ?? 0);
    uitgesteld.value = tot > Date.now();

    if (!rijp.value) {
        timer = setTimeout(() => (rijp.value = true), SECONDEN * 1000);
    }
});

onUnmounted(() => {
    if (timer) {
        clearTimeout(timer);
    }
});
</script>

<template>
    <div
        v-if="zichtbaar"
        class="fixed inset-x-3 bottom-[calc(var(--pp-tabbar)+0.75rem)] z-50 rounded-xl border bg-card p-3 shadow-lg sm:left-auto sm:w-96"
        :class="kind ? 'border-primary/50' : 'border-border'"
        role="dialog"
        :aria-label="titel"
    >
        <div class="flex items-start gap-3">
            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                <Download class="size-4" />
            </span>

            <div class="min-w-0 flex-1">
                <p class="text-sm font-medium">{{ titel }}</p>
                <p class="text-xs text-muted-foreground">{{ uitleg }}</p>

                <!-- Geen echte knop (iOS, of een browser die hem niet geeft): de stappen -->
                <ol v-if="!kanKnop" class="mt-2 space-y-1 text-xs">
                    <template v-if="isIos">
                        <li class="flex items-center gap-2">
                            <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-secondary text-[10px] font-semibold">1</span>
                            Tik onderin op <Share class="inline size-3.5" aria-label="Deel" /> <span class="font-medium">Deel</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-secondary text-[10px] font-semibold">2</span>
                            Kies <SquarePlus class="inline size-3.5" aria-hidden="true" /> <span class="font-medium">Zet op beginscherm</span>
                        </li>
                    </template>
                    <template v-else>
                        <li class="flex items-start gap-2">
                            <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-secondary text-[10px] font-semibold">1</span>
                            <span>Tik op de drie puntjes van je browser</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-secondary text-[10px] font-semibold">2</span>
                            <span>Kies <span class="font-medium">App installeren</span> of <span class="font-medium">Toevoegen aan startscherm</span></span>
                        </li>
                    </template>
                </ol>
            </div>

            <button
                type="button"
                class="-m-1 flex size-9 shrink-0 items-center justify-center rounded-lg text-muted-foreground hover:text-foreground"
                aria-label="Niet nu"
                @click="stelUit(7)"
            >
                <X class="size-4" />
            </button>
        </div>

        <div v-if="kanKnop" class="mt-3 flex gap-2">
            <button type="button" class="h-11 flex-1 rounded-lg bg-primary px-3 text-sm font-semibold text-primary-foreground" @click="klik">
                Op beginscherm zetten
            </button>
            <button type="button" class="h-11 rounded-lg border border-border px-3 text-sm font-medium" @click="stelUit(7)">Niet nu</button>
        </div>
        <div v-else class="mt-3">
            <button type="button" class="h-11 w-full rounded-lg border border-border px-3 text-sm font-medium" @click="stelUit(7)">Begrepen</button>
        </div>
    </div>
</template>
