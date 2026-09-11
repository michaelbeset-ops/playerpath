<script setup lang="ts">
import { type SharedData } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, Check, ChevronDown, ChevronUp, Compass, Lightbulb, X } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

/**
 * De rondleiding: veertien schermen, en dan je eigen school inrichten.
 *
 * Hij loopt door de echte app. Elke stap heeft een adres en een anker op dat
 * scherm (`data-tour="…"`); "Volgende" navigeert mee, en de voorbeelddata zorgt
 * dat er iets te zien is. De stappen komen van de server (`OnboardingTour`),
 * want welke schermen er zijn hangt af van de functies van de school, en het
 * adres van "een gevulde spelerskaart" is dat van een echte voorbeeldspeler.
 *
 * Wat deze versie anders doet dan de eerste, en waarom:
 *
 * 1. **Het scherm blijft gewoon te gebruiken.** Er ligt geen donkere laag
 *    overheen: je kunt scrollen, klikken en rondkijken terwijl het kaartje
 *    staat. De eerste versie blokkeerde alles, en dan is een rondleiding een
 *    dia-show over een scherm dat je niet mag aanraken.
 * 2. **Het kaartje staat in een hoek en klapt in.** Rechtsonder op een groot
 *    scherm, onderaan op een telefoon, en met één tik wordt het een smal
 *    balkje. Zo kun je even zelf kijken en daarna verder.
 * 3. **Eén ring om het onderdeel waar het over gaat**, en verder niets. Is het
 *    anker zo groot als het scherm zelf (het hele dashboard), dan komt er geen
 *    ring: een ring om alles wijst nergens naar.
 * 4. **Elke stap heeft een tip om zelf iets te proberen.** Dat is het verschil
 *    tussen uitleg lezen en iets snappen.
 * 5. **Pijltjestoetsen werken, maar niet in een invulveld.** Het scherm is
 *    bruikbaar, dus iemand kan aan het typen zijn.
 * 6. **Hoe ver je bent wordt onthouden** (`tour_step` op de school). Sta je op
 *    een ander scherm dan de stap, dan staat er een klein balkje om terug te
 *    gaan, in plaats van dat je ongevraagd wordt weggestuurd.
 */
interface TourStap {
    key: string;
    url: string;
    anchor: string | null;
    title: string;
    body: string;
    tip: string | null;
}

const page = usePage<SharedData>();

const stappen = computed<TourStap[]>(() => (page.props.onboarding?.tourSteps ?? []) as TourStap[]);
const bezig = computed(() => page.props.onboarding?.tour === true && stappen.value.length > 0);

const index = ref(0);
const doel = ref<DOMRect | null>(null);
const ingeklapt = ref(false);

const huidige = computed(() => stappen.value[index.value] ?? null);
const laatste = computed(() => index.value >= stappen.value.length - 1);

const pad = computed(() => page.url.split('?')[0]);

/** Sta je op het scherm van deze stap? Zo niet: teruggaan aanbieden. */
const opHetScherm = computed(() => huidige.value !== null && pad.value === huidige.value.url.split('?')[0]);

const zoek = (anchor: string | null) => (anchor ? document.querySelector<HTMLElement>('[data-tour="' + anchor + '"]') : null);
const zichtbaar = (el: HTMLElement | null) => el !== null && el.getClientRects().length > 0 && el.offsetParent !== null;

/** Een anker dat bijna het hele scherm is krijgt geen ring: die wijst nergens naar. */
const teGroot = (rect: DOMRect) => rect.height > window.innerHeight * 0.7;

const meet = () => {
    const el = zoek(huidige.value?.anchor ?? null);

    if (!zichtbaar(el)) {
        doel.value = null;

        return;
    }

    const rect = el!.getBoundingClientRect();
    doel.value = teGroot(rect) ? null : rect;
};

// Naar het anker scrollen als het buiten beeld staat, dan meten.
const richt = async (poging = 0) => {
    await nextTick();
    const el = zoek(huidige.value?.anchor ?? null);

    // Na een navigatie staat het nieuwe scherm er soms nog niet; even
    // wachten en opnieuw kijken, in plaats van een stap zonder ring.
    if (!zichtbaar(el) && huidige.value?.anchor && poging < 6) {
        setTimeout(() => richt(poging + 1), 300);
    }

    if (zichtbaar(el)) {
        // Bovenaan in beeld, met ruimte voor de balk bovenin. Niet in het
        // midden: op een telefoon staat het kaartje onderaan, en "midden"
        // schuift het onderdeel er dan half onder. In één keer, niet
        // vloeiend: een meting halverwege een vloeiende scroll zet de ring
        // naast iets wat er nog niet is.
        el!.style.scrollMarginTop = '5rem';
        el!.scrollIntoView({ block: 'start', behavior: 'auto' });
        requestAnimationFrame(meet);
    }

    meet();
};

/**
 * Waar je bent: op de server (voor een ander apparaat) én in deze browser.
 *
 * De server-versie loopt achter zodra je "Volgende" klikt en meteen naar een
 * ander scherm gaat: dat scherm draagt nog de vorige stap mee. Zou de tour
 * daarop vertrouwen, dan springt hij na elke navigatie een stap terug. De
 * browser onthoudt daarom zelf waar hij is, en de server is de terugval.
 */
const SLEUTEL = 'pp.tour.index';

const bewaar = (stap: number) => {
    try {
        sessionStorage.setItem(SLEUTEL, String(stap));
    } catch {
        // Geen opslag: dan valt hij terug op de server, en dat is ook goed.
    }

    router.post('/onboarding/rondleiding/stap', { step: stap }, { preserveScroll: true, preserveState: true, only: [] });
};

const onthouden = (): number | null => {
    try {
        const waarde = sessionStorage.getItem(SLEUTEL);

        return waarde === null ? null : Number(waarde);
    } catch {
        return null;
    }
};

const vergeet = () => {
    try {
        sessionStorage.removeItem(SLEUTEL);
    } catch {
        // niets te vergeten
    }
};

const ga = (stap: number) => {
    const doelStap = stappen.value[stap];

    if (!doelStap) {
        return;
    }

    index.value = stap;
    ingeklapt.value = false;
    bewaar(stap);

    if (pad.value !== doelStap.url.split('?')[0]) {
        router.visit(doelStap.url);
    } else {
        richt();
    }
};

const volgende = () => (laatste.value ? afronden() : ga(index.value + 1));
const vorige = () => ga(Math.max(0, index.value - 1));

/** Stoppen telt als gezien; opnieuw starten kan altijd via het vraagteken. */
const stop = () => {
    vergeet();
    router.post('/onboarding/rondleiding/klaar', {}, { preserveScroll: true });
};

/** De laatste stap is een deur: naar het inrichten van je eigen school. */
const afronden = () => {
    vergeet();
    router.post('/onboarding/rondleiding/klaar', {}, { onSuccess: () => router.visit('/instellingen/inschrijven/stap/1') });
};

const terug = () => ga(index.value);

/** Typt iemand ergens? Dan zijn de pijltjes van hem, niet van ons. */
const aanHetTypen = (event: KeyboardEvent) => {
    const el = event.target as HTMLElement | null;

    return el !== null && (el.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(el.tagName));
};

const opToets = (event: KeyboardEvent) => {
    if (!bezig.value || !opHetScherm.value || ingeklapt.value || aanHetTypen(event)) {
        return;
    }

    if (event.key === 'ArrowRight') {
        volgende();
    } else if (event.key === 'ArrowLeft') {
        vorige();
    }
};

onMounted(() => {
    index.value = Math.min(onthouden() ?? page.props.onboarding?.tourStep ?? 0, Math.max(0, stappen.value.length - 1));
    window.addEventListener('resize', meet);
    window.addEventListener('scroll', meet, { capture: true, passive: true });
    document.addEventListener('keydown', opToets);

    // Even wachten tot het scherm er echt staat; anders vindt hij het anker niet.
    setTimeout(richt, 600);
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', meet);
    window.removeEventListener('scroll', meet, { capture: true });
    document.removeEventListener('keydown', opToets);
});

// Na navigeren (Volgende naar een ander scherm) of opnieuw starten: opnieuw
// richten op het anker van de huidige stap.
watch(pad, () => {
    if (bezig.value) {
        setTimeout(richt, 500);
    }
});

// Opnieuw gestart via het vraagteken: dan begint hij écht vooraan.
watch(bezig, (nu, eerst) => {
    if (nu && !eerst) {
        vergeet();
        index.value = 0;
        ingeklapt.value = false;
        setTimeout(richt, 500);
    }
});
</script>

<template>
    <Teleport to="body">
        <template v-if="bezig && huidige">
            <!-- De ring om het onderdeel waar deze stap over gaat. Laat klikken
                 door: het scherm eronder blijft van de gebruiker. -->
            <div
                v-if="opHetScherm && !ingeklapt && doel"
                class="pp-tour-ring pointer-events-none fixed z-40 rounded-xl ring-4 ring-primary ring-offset-2 ring-offset-background"
                :style="{
                    left: doel.left - 6 + 'px',
                    top: doel.top - 6 + 'px',
                    width: doel.width + 12 + 'px',
                    height: doel.height + 12 + 'px',
                }"
            ></div>

            <!-- ===== Op het scherm van de stap, ingeklapt: een smal balkje ===== -->
            <div
                v-if="opHetScherm && ingeklapt"
                class="fixed inset-x-3 bottom-[calc(var(--pp-tabbar)+0.75rem)] z-50 flex items-center gap-3 rounded-xl border border-border bg-card p-2 pl-4 shadow-lg sm:inset-x-auto sm:right-6 sm:bottom-6 sm:w-96"
            >
                <Compass class="size-5 shrink-0 text-primary" />
                <span class="min-w-0 flex-1 text-sm">
                    <span class="tabular block text-xs text-muted-foreground">Rondleiding · stap {{ index + 1 }} van {{ stappen.length }}</span>
                    <span class="block truncate font-medium">{{ huidige.title }}</span>
                </span>
                <button
                    type="button"
                    class="inline-flex min-h-11 shrink-0 items-center gap-1.5 rounded-lg bg-primary px-3 text-sm font-semibold text-primary-foreground"
                    @click="ingeklapt = false"
                >
                    <ChevronUp class="size-4" />
                    Verder
                </button>
            </div>

            <!-- ===== Op het scherm van de stap: het kaartje ===== -->
            <div
                v-else-if="opHetScherm"
                class="fixed inset-x-3 bottom-[calc(var(--pp-tabbar)+0.75rem)] z-50 sm:inset-x-auto sm:right-6 sm:bottom-6 sm:w-[24rem]"
                role="complementary"
                aria-label="Rondleiding"
            >
                <div class="max-h-[60vh] overflow-y-auto rounded-2xl border border-border bg-card shadow-2xl sm:max-h-[calc(100vh-6rem)]">
                    <div class="p-4 sm:p-5">
                        <div class="flex items-center justify-between gap-2">
                            <p class="tabular text-xs font-medium text-muted-foreground">Stap {{ index + 1 }} van {{ stappen.length }}</p>
                            <div class="-my-2 -mr-2 flex items-center">
                                <button
                                    type="button"
                                    class="flex size-11 items-center justify-center rounded-lg text-muted-foreground transition hover:bg-secondary hover:text-foreground"
                                    aria-label="Kaartje even wegklappen"
                                    title="Even zelf kijken"
                                    @click="ingeklapt = true"
                                >
                                    <ChevronDown class="size-5" />
                                </button>
                                <button
                                    type="button"
                                    class="flex size-11 items-center justify-center rounded-lg text-muted-foreground transition hover:bg-secondary hover:text-foreground"
                                    aria-label="Rondleiding stoppen"
                                    title="Rondleiding stoppen"
                                    @click="stop"
                                >
                                    <X class="size-4" />
                                </button>
                            </div>
                        </div>

                        <div class="mt-2 h-1 overflow-hidden rounded-full bg-secondary">
                            <div
                                class="h-full rounded-full bg-primary transition-all"
                                :style="{ width: ((index + 1) / stappen.length) * 100 + '%' }"
                            ></div>
                        </div>

                        <p class="mt-4 text-lg font-semibold leading-snug">{{ huidige.title }}</p>
                        <p class="mt-2 text-[15px] leading-relaxed text-foreground/85">{{ huidige.body }}</p>

                        <div v-if="huidige.tip" class="mt-3 flex gap-2.5 rounded-xl bg-primary/10 p-3 text-sm leading-relaxed">
                            <Lightbulb class="mt-0.5 size-4 shrink-0 text-primary" />
                            <p>{{ huidige.tip }}</p>
                        </div>

                        <div class="mt-4 flex items-center justify-between gap-2">
                            <button
                                type="button"
                                class="inline-flex min-h-11 items-center gap-1.5 rounded-xl px-3 text-sm text-muted-foreground transition enabled:hover:text-foreground disabled:opacity-30"
                                :disabled="index === 0"
                                @click="vorige"
                            >
                                <ArrowLeft class="size-4" />
                                Vorige
                            </button>

                            <button
                                type="button"
                                class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-primary px-5 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                                @click="volgende"
                            >
                                <template v-if="laatste">
                                    <Check class="size-4" />
                                    Mijn school inrichten
                                </template>
                                <template v-else>
                                    Volgende
                                    <ArrowRight class="size-4" />
                                </template>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===== Op een ander scherm dan de stap: terug aanbieden ===== -->
            <div
                v-else
                class="fixed inset-x-3 bottom-[calc(var(--pp-tabbar)+0.75rem)] z-50 flex items-center gap-3 rounded-xl border border-border bg-card p-2 pl-4 shadow-lg sm:inset-x-auto sm:right-6 sm:bottom-6 sm:w-96"
            >
                <Compass class="size-5 shrink-0 text-primary" />
                <span class="min-w-0 flex-1 text-sm">
                    <span class="tabular block text-xs text-muted-foreground">Rondleiding · stap {{ index + 1 }} van {{ stappen.length }}</span>
                    <span class="block truncate font-medium">{{ huidige.title }}</span>
                </span>
                <button
                    type="button"
                    class="inline-flex min-h-11 shrink-0 items-center rounded-lg bg-primary px-3 text-sm font-semibold text-primary-foreground"
                    @click="terug"
                >
                    Terug
                </button>
                <button
                    type="button"
                    class="flex size-11 shrink-0 items-center justify-center rounded-lg text-muted-foreground"
                    aria-label="Rondleiding stoppen"
                    @click="stop"
                >
                    <X class="size-4" />
                </button>
            </div>
        </template>
    </Teleport>
</template>

<style scoped>
/* Een zachte ademhaling, zodat het oog de ring vindt zonder dat hij schreeuwt.
   Uit voor wie liever geen beweging heeft. */
@keyframes pp-tour-adem {
    0%,
    100% {
        box-shadow: 0 0 0 0 hsl(var(--primary) / 0.35);
    }
    50% {
        box-shadow: 0 0 0 10px hsl(var(--primary) / 0);
    }
}

.pp-tour-ring {
    animation: pp-tour-adem 2s ease-in-out infinite;
}

@media (prefers-reduced-motion: reduce) {
    .pp-tour-ring {
        animation: none;
    }
}
</style>
