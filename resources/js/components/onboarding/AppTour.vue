<script setup lang="ts">
import { type SharedData } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, Check, Compass, X } from 'lucide-vue-next';
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
 * Vijf regels:
 *
 * 1. **Overslaan kan altijd**, in elke stap, en het is geen kleine grijze link.
 * 2. **Hoe ver je bent wordt onthouden** (`tour_step` op de school). Wie
 *    halverwege zijn telefoon wegstopt komt terug bij stap acht, niet bij één.
 *    Sta je op een ander scherm dan de stap, dan staat er een klein kaartje
 *    "Rondleiding hervatten" in plaats van dat je ongevraagd wordt weggestuurd.
 * 3. **Het anker wordt alleen omcirkeld als het zichtbaar is.** Op een telefoon
 *    zit het menu in een uitklap; een ring om iets onzichtbaars is een ring om
 *    niets. De kaart vertelt het verhaal ook zonder pijl.
 * 4. **Op een telefoon staat de kaart onderaan**, binnen duimbereik. Op een
 *    groot scherm staat hij onder het anker, en anders onderaan.
 * 5. **De laatste stap is een deur**, niet een "klaar": hij brengt je naar het
 *    inrichten van je eigen school.
 */
interface TourStap {
    key: string;
    url: string;
    anchor: string | null;
    title: string;
    body: string;
}

const page = usePage<SharedData>();

const stappen = computed<TourStap[]>(() => (page.props.onboarding?.tourSteps ?? []) as TourStap[]);
const bezig = computed(() => page.props.onboarding?.tour === true && stappen.value.length > 0);

const index = ref(0);
const doel = ref<DOMRect | null>(null);
const smal = ref(false);

const huidige = computed(() => stappen.value[index.value] ?? null);
const laatste = computed(() => index.value >= stappen.value.length - 1);

const pad = computed(() => page.url.split('?')[0]);

/** Sta je op het scherm van deze stap? Zo niet: hervatten aanbieden. */
const opHetScherm = computed(() => huidige.value !== null && pad.value === huidige.value.url.split('?')[0]);

const zoek = (anchor: string | null) => (anchor ? document.querySelector<HTMLElement>('[data-tour="' + anchor + '"]') : null);
const zichtbaar = (el: HTMLElement | null) => el !== null && el.getClientRects().length > 0 && el.offsetParent !== null;

const meet = () => {
    const el = zoek(huidige.value?.anchor ?? null);
    doel.value = zichtbaar(el) ? el!.getBoundingClientRect() : null;
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
        // In één keer, niet vloeiend: een vloeiende scroll duurt op een groot
        // scherm langer dan je denkt, en een meting halverwege zet de kaart
        // naast iets wat er nog niet is. Een hoog blok (het hele dashboard)
        // begint bovenaan; de rest komt in het midden.
        const hoog = el!.getBoundingClientRect().height > window.innerHeight * 0.7;
        el!.scrollIntoView({ block: hoog ? 'start' : 'center', behavior: 'auto' });
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
    bewaar(stap);

    if (pad.value !== doelStap.url.split('?')[0]) {
        router.visit(doelStap.url);
    } else {
        richt();
    }
};

const volgende = () => (laatste.value ? afronden() : ga(index.value + 1));
const vorige = () => ga(Math.max(0, index.value - 1));

/** Overslaan telt als gezien; opnieuw starten kan altijd via het vraagteken. */
const stop = () => {
    vergeet();
    router.post('/onboarding/rondleiding/klaar', {}, { preserveScroll: true });
};

/** De laatste stap is een deur: naar het inrichten van je eigen school. */
const afronden = () => {
    vergeet();
    router.post('/onboarding/rondleiding/klaar', {}, { onSuccess: () => router.visit('/instellingen/inschrijven/stap/1') });
};

const hervat = () => ga(index.value);

const opToets = (event: KeyboardEvent) => {
    if (!bezig.value || !opHetScherm.value) {
        return;
    }

    if (event.key === 'Escape') {
        stop();
    } else if (event.key === 'ArrowRight') {
        volgende();
    } else if (event.key === 'ArrowLeft') {
        vorige();
    }
};

const meetBreedte = () => (smal.value = window.matchMedia('(max-width: 639px)').matches);

onMounted(() => {
    index.value = Math.min(onthouden() ?? page.props.onboarding?.tourStep ?? 0, Math.max(0, stappen.value.length - 1));
    meetBreedte();
    window.addEventListener('resize', meet);
    window.addEventListener('resize', meetBreedte);
    window.addEventListener('scroll', meet, true);
    document.addEventListener('keydown', opToets);

    // Even wachten tot het scherm er echt staat; anders vindt hij het anker niet.
    setTimeout(richt, 600);
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', meet);
    window.removeEventListener('resize', meetBreedte);
    window.removeEventListener('scroll', meet, true);
    document.removeEventListener('keydown', opToets);
});

// Na navigeren (Volgende naar een ander scherm) of opnieuw starten: opnieuw
// richten op het anker van de huidige stap.
watch(pad, () => {
    if (bezig.value) {
        setTimeout(richt, 500);
    }
});

// Scrollt of draait iemand het scherm, dan verhuist de ring mee.
onMounted(() => {
    window.addEventListener('scroll', meet, { passive: true });
    window.addEventListener('resize', meet);
});
onBeforeUnmount(() => {
    window.removeEventListener('scroll', meet);
    window.removeEventListener('resize', meet);
});

// Opnieuw gestart via het vraagteken: dan begint hij écht vooraan.
watch(bezig, (nu, eerst) => {
    if (nu && !eerst) {
        vergeet();
        index.value = 0;
        setTimeout(richt, 500);
    }
});

/** Waar de kaart staat op een groot scherm: onder het anker als dat kan. */
const kaartStijl = computed(() => {
    if (smal.value || !doel.value) {
        return undefined;
    }

    const breedte = 416;
    const links = Math.min(Math.max(16, doel.value.left), window.innerWidth - breedte - 16);
    const onder = doel.value.bottom + 16;

    // Past hij eronder? Anders erboven, en anders onderaan het scherm.
    if (onder + 260 < window.innerHeight) {
        return { left: links + 'px', top: onder + 'px' };
    }

    // Erboven kan alleen als het anker zelf in beeld is; anders hangt de
    // kaart aan iets wat je niet ziet.
    if (doel.value.top - 276 > 0 && doel.value.top < window.innerHeight) {
        return { left: links + 'px', top: doel.value.top - 276 + 'px' };
    }

    return undefined;
});
</script>

<template>
    <Teleport to="body">
        <!-- ===== Actief op het juiste scherm ===== -->
        <div v-if="bezig && huidige && opHetScherm" class="fixed inset-0 z-[60]" role="dialog" aria-label="Rondleiding">
            <div class="absolute inset-0 bg-foreground/50" @click="stop"></div>

            <div
                v-if="doel"
                class="pointer-events-none absolute rounded-xl ring-4 ring-primary ring-offset-2 ring-offset-transparent transition-all"
                :style="{
                    left: doel.left - 4 + 'px',
                    top: doel.top - 4 + 'px',
                    width: doel.width + 8 + 'px',
                    height: doel.height + 8 + 'px',
                }"
            ></div>

            <div
                class="absolute inset-x-0 bottom-0 p-3 sm:w-[26rem] sm:p-0"
                :class="kaartStijl ? '' : 'sm:inset-x-auto sm:bottom-8 sm:left-1/2 sm:-translate-x-1/2'"
                :style="kaartStijl"
            >
                <div class="rounded-2xl border border-border bg-card p-4 shadow-2xl sm:p-5">
                    <div class="flex items-start justify-between gap-3">
                        <p class="tabular text-xs font-medium text-muted-foreground">Stap {{ index + 1 }} van {{ stappen.length }}</p>
                        <button
                            type="button"
                            class="-my-2.5 flex size-11 shrink-0 items-center justify-center rounded-lg text-muted-foreground transition hover:bg-secondary"
                            aria-label="Rondleiding overslaan"
                            @click="stop"
                        >
                            <X class="size-4" />
                        </button>
                    </div>

                    <p class="mt-1 text-lg font-semibold">{{ huidige.title }}</p>
                    <p class="mt-1 text-sm text-muted-foreground">{{ huidige.body }}</p>

                    <div class="mt-3 h-1 overflow-hidden rounded-full bg-secondary">
                        <div
                            class="h-full rounded-full bg-primary transition-all"
                            :style="{ width: ((index + 1) / stappen.length) * 100 + '%' }"
                        ></div>
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

                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="inline-flex min-h-11 items-center rounded-xl px-3 text-sm text-muted-foreground transition hover:text-foreground"
                                @click="stop"
                            >
                                Overslaan
                            </button>
                            <button
                                type="button"
                                class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
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
        </div>

        <!-- ===== Actief, maar op een ander scherm: hervatten aanbieden ===== -->
        <div
            v-else-if="bezig && huidige"
            class="fixed inset-x-3 bottom-[calc(var(--pp-tabbar)+0.75rem)] z-50 flex items-center gap-3 rounded-xl border border-border bg-card p-3 shadow-lg sm:left-auto sm:w-96"
        >
            <Compass class="size-5 shrink-0 text-primary" />
            <span class="min-w-0 flex-1 text-sm">
                <span class="block font-medium">Rondleiding hervatten</span>
                <span class="tabular block text-xs text-muted-foreground">Stap {{ index + 1 }} van {{ stappen.length }} · {{ huidige.title }}</span>
            </span>
            <button
                type="button"
                class="inline-flex min-h-11 shrink-0 items-center rounded-lg bg-primary px-3 text-sm font-semibold text-primary-foreground"
                @click="hervat"
            >
                Verder
            </button>
            <button
                type="button"
                class="flex size-11 shrink-0 items-center justify-center rounded-lg text-muted-foreground"
                aria-label="Rondleiding overslaan"
                @click="stop"
            >
                <X class="size-4" />
            </button>
        </div>
    </Teleport>
</template>
