<script setup lang="ts">
import { type SharedData } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { ArrowRight, Check, X } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

/**
 * De rondleiding: vijf plekken, dan ben je klaar.
 *
 * Vijf en niet vijftien. Een tour die langer duurt dan het uitproberen zelf
 * wordt weggeklikt, en dan heeft hij alleen maar in de weg gestaan. Hij wijst
 * de plekken aan die je anders pas na een week vindt, en verder niets.
 *
 * Vier regels:
 *
 * 1. **Overslaan kan altijd**, in elke stap, en het is geen kleine grijze link
 *    in een hoek.
 * 2. **Hij komt niet terug** zodra je hem hebt gezien of overgeslagen; opnieuw
 *    starten doe je zelf met de vraagtekenknop in de balk.
 * 3. **Hij wijst echte elementen aan** via `data-tour` — maar alleen als ze
 *    ook echt zichtbaar zijn. Op een telefoon zit het menu in een uitklap, dus
 *    staan die elementen wel in de pagina maar zie je ze niet; een ring om iets
 *    onzichtbaars is een ring om niets. De vijf stappen blijven dan gewoon
 *    staan: de tour vertelt waar de plekken zijn, en dat werkt ook zonder pijl.
 * 4. **Op een telefoon is het een kaart onderaan**, binnen duimbereik. Een
 *    tekstballon naast een element van 40 pixels past daar niet.
 */
interface TourStap {
    anchor: string;
    title: string;
    body: string;
}

const STAPPEN: TourStap[] = [
    {
        anchor: 'reports',
        title: 'Rapport invullen',
        body: 'Het hart van PlayerPath. Een trainer beoordeelt een speler in een halve minuut, en de spelerskaart verandert meteen mee.',
    },
    {
        anchor: 'calendar',
        title: 'Je agenda',
        body: 'Trainingen plannen, aanwezigheid afvinken en zien wie er wanneer voor de groep staat.',
    },
    {
        anchor: 'clients',
        title: 'Je klanten',
        body: 'Spelers met hun ouders eronder. Hier koppel je een ouder aan een kind en nodig je hem uit.',
    },
    {
        anchor: 'enrollments',
        title: 'Inschrijvingen',
        body: 'Wat er binnenkomt via je eigen inschrijfpagina. Je keurt goed, en de ouder krijgt een betaalverzoek.',
    },
    {
        anchor: 'settings',
        title: 'Je instellingen',
        body: 'Inschrijven en betalen, je huisstijl, je locaties en je personeel. Alles onder Mijn bedrijf.',
    },
];

const page = usePage<SharedData>();

const actief = ref(false);
const index = ref(0);
const doel = ref<DOMRect | null>(null);

/** De stappen waarvan het element op dit scherm bestaat. */
const beschikbaar = ref<TourStap[]>([]);

const huidige = computed(() => beschikbaar.value[index.value] ?? null);

const zoek = (anchor: string) => document.querySelector<HTMLElement>('[data-tour="' + anchor + '"]');

/** In beeld, en niet alleen aanwezig: een uitgeklapt menu telt niet mee. */
const zichtbaar = (el: HTMLElement | null) => el !== null && el.getClientRects().length > 0 && el.offsetParent !== null;

const meet = () => {
    const stap = huidige.value;

    if (!stap) {
        return;
    }

    const el = zoek(stap.anchor);

    // Geen ring als er niets te omcirkelen is; de kaart vertelt het verhaal.
    doel.value = zichtbaar(el) ? el!.getBoundingClientRect() : null;
};

const start = async () => {
    // Alle vijf de stappen, ook als het element hier niet zichtbaar is: de tour
    // vertelt waar de plekken zijn, en dat klopt ook op een telefoon.
    beschikbaar.value = STAPPEN;

    index.value = 0;
    actief.value = true;

    await nextTick();
    meet();
};

const stop = (afgerond: boolean) => {
    actief.value = false;

    // Overslaan telt net zo goed als gezien: hem morgen weer laten verschijnen
    // is precies waarom mensen tours haten. Opnieuw starten kan altijd zelf.
    void afgerond;
    router.post('/onboarding/rondleiding/klaar', {}, { preserveScroll: true, preserveState: true });
};

const volgende = async () => {
    if (index.value >= beschikbaar.value.length - 1) {
        stop(true);

        return;
    }

    index.value += 1;
    await nextTick();
    meet();
};

const opToets = (event: KeyboardEvent) => {
    if (!actief.value) {
        return;
    }

    if (event.key === 'Escape') {
        stop(false);
    } else if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        volgende();
    }
};

onMounted(() => {
    window.addEventListener('resize', meet);
    window.addEventListener('scroll', meet, true);
    document.addEventListener('keydown', opToets);

    // De server zegt of deze school hem nog moet zien.
    if (page.props.onboarding?.tour) {
        // Even wachten tot de balk er echt staat; anders vindt hij niets en
        // slaat hij zichzelf helemaal over.
        setTimeout(start, 700);
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', meet);
    window.removeEventListener('scroll', meet, true);
    document.removeEventListener('keydown', opToets);
});

// De vraagtekenknop in de balk start hem opnieuw.
watch(
    () => page.props.onboarding?.tour,
    (nieuw, oud) => {
        if (nieuw && !oud) {
            setTimeout(start, 300);
        }
    },
);

defineExpose({ start });
</script>

<template>
    <Teleport to="body">
        <div v-if="actief && huidige" class="fixed inset-0 z-[60]" role="dialog" aria-label="Rondleiding">
            <!-- De donkere laag. Klikken erop slaat over: dat is wat mensen
                 verwachten, en een tour die je niet weg krijgt is een val. -->
            <div class="absolute inset-0 bg-foreground/50" @click="stop(false)"></div>

            <!-- Het uitgelichte element. Een rand eromheen, geen uitsnede: een
                 echte cutout met vier vlakken verspringt bij elke scroll. -->
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

            <!-- De kaart. Onderaan op een telefoon, gecentreerd op een groot
                 scherm: naast een menu-item van 40 pixels past geen ballon. -->
            <div class="absolute inset-x-0 bottom-0 p-3 sm:inset-x-auto sm:bottom-8 sm:left-1/2 sm:w-[26rem] sm:-translate-x-1/2 sm:p-0">
                <div class="rounded-2xl border border-border bg-card p-4 shadow-2xl sm:p-5">
                    <div class="flex items-start justify-between gap-3">
                        <p class="tabular text-xs font-medium text-muted-foreground">Stap {{ index + 1 }} van {{ beschikbaar.length }}</p>
                        <button
                            type="button"
                            class="-my-2.5 flex size-11 shrink-0 items-center justify-center rounded-lg text-muted-foreground transition hover:bg-secondary"
                            aria-label="Rondleiding sluiten"
                            @click="stop(false)"
                        >
                            <X class="size-4" />
                        </button>
                    </div>

                    <p class="mt-1 text-lg font-semibold">{{ huidige.title }}</p>
                    <p class="mt-1 text-sm text-muted-foreground">{{ huidige.body }}</p>

                    <!-- Voortgang als bolletjes: je ziet dat het er vijf zijn en
                         niet vijftig. Dat is de helft van waarom je doorklikt. -->
                    <div class="mt-4 flex items-center justify-between gap-3">
                        <div class="flex gap-1.5">
                            <span
                                v-for="(stap, i) in beschikbaar"
                                :key="stap.anchor"
                                class="size-1.5 rounded-full transition"
                                :class="i === index ? 'bg-primary' : i < index ? 'bg-primary/40' : 'bg-secondary'"
                            ></span>
                        </div>

                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="inline-flex min-h-11 items-center rounded-xl px-3 text-sm text-muted-foreground transition hover:text-foreground"
                                @click="stop(false)"
                            >
                                Overslaan
                            </button>
                            <button
                                type="button"
                                class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                                @click="volgende"
                            >
                                <template v-if="index === beschikbaar.length - 1">
                                    <Check class="size-4" />
                                    Klaar
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
    </Teleport>
</template>
