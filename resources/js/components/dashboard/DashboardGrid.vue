<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { GridItem, GridLayout } from 'grid-layout-plus';
import { Check, GripVertical, LayoutGrid, Plus, RotateCcw, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

/**
 * Het raster met de widgets, en de bewerkmodus eromheen.
 *
 * Het patroon is dat van een beginscherm op een telefoon: je houdt iets
 * ingedrukt, alles gaat wiebelen, je sleept en zet kruisjes weg, en dan druk je
 * op Klaar. Bekend gedrag, dus niemand hoeft het uit te leggen.
 *
 * Drie dingen die je niet moet omdraaien:
 *
 * 1. **Opslaan gaat via de server.** Een indeling die alleen in de browser
 *    bestaat staat op je telefoon anders dan op je laptop, en dat is niet wat
 *    "mijn indeling" hoort te betekenen.
 * 2. **Annuleren zet echt terug.** De uitgangssituatie wordt bij het openen
 *    gekopieerd, dus wie halverwege van gedachten verandert raakt niets kwijt.
 * 3. **Op een telefoon is het één kolom.** Een raster van twaalf kolommen op
 *    375 pixels is geen raster; daar gaat het alleen over volgorde en over wat
 *    er wel en niet staat. Wat je op een telefoon versleept verandert dus
 *    alleen de volgorde — de breedtes van je grote scherm blijven staan.
 */
export interface Plek {
    key: string;
    size: number;
    height: number;
    x: number;
    y: number;
}

export interface Beschikbaar {
    key: string;
    label: string;
    description: string;
    icon: string;
    sizes: number[];
    height: number;
}

const props = defineProps<{
    layout: Plek[];
    available: Beschikbaar[];
}>();

const KOLOMMEN = 12;

const bewerken = ref(false);
const toonKiezer = ref(false);
const bezig = ref(false);

/** De plekken waar we mee werken; in bewerkmodus wijkt dit af van de server. */
const plekken = ref<Plek[]>([]);
/** Waar we vandaan kwamen, zodat Annuleren echt terugzet. */
let uitgangspunt: Plek[] = [];

const kopieer = (rijen: Plek[]) => rijen.map((rij) => ({ ...rij }));

watch(
    () => props.layout,
    (nieuw) => {
        if (!bewerken.value) {
            plekken.value = kopieer(nieuw);
        }
    },
    { immediate: true, deep: true },
);

// Eén kolom onder 1024px. Boven die breedte past een raster van twaalf.
const smal = ref(false);
let media: MediaQueryList | null = null;
const meet = (event: MediaQueryList | MediaQueryListEvent) => (smal.value = !event.matches);

onMounted(() => {
    media = window.matchMedia('(min-width: 1024px)');
    meet(media);
    media.addEventListener('change', meet);
});

onBeforeUnmount(() => media?.removeEventListener('change', meet));

const kolommen = computed(() => (smal.value ? 1 : KOLOMMEN));

/** De volgorde van boven naar beneden, dan van links naar rechts. */
const opVolgorde = computed(() => [...plekken.value].sort((a, b) => a.y - b.y || a.x - b.x));

/** Wat de library nodig heeft. Op smal scherm: één kolom, alles even breed. */
const rooster = computed(() =>
    smal.value
        ? opVolgorde.value.map((plek, index) => ({
              i: plek.key,
              x: 0,
              y: index,
              w: 1,
              h: plek.height,
          }))
        : plekken.value.map((plek) => ({
              i: plek.key,
              x: plek.x,
              y: plek.y,
              w: plek.size,
              h: plek.height,
          })),
);

const werkRooster = ref<{ i: string; x: number; y: number; w: number; h: number }[]>([]);

watch(rooster, (nieuw) => (werkRooster.value = nieuw.map((r) => ({ ...r }))), { immediate: true });

const gebruikt = computed(() => new Set(plekken.value.map((p) => p.key)));

const teVoegen = computed(() => props.available.filter((widget) => !gebruikt.value.has(widget.key)));

const start = () => {
    uitgangspunt = kopieer(plekken.value);
    bewerken.value = true;
};

const annuleer = () => {
    plekken.value = kopieer(uitgangspunt);
    bewerken.value = false;
    toonKiezer.value = false;
};

/**
 * Bewerkmodus sluiten en het scherm gelijkzetten met wat de server nu weet.
 *
 * De watch op de props slaat wijzigingen over zolang je aan het bewerken bent —
 * anders springt je indeling onder je handen weg. Bij het sluiten moet hij dus
 * één keer expliciet worden bijgetrokken, anders zie je na "Standaard
 * herstellen" nog steeds je oude indeling staan.
 */
const sluitAf = () => {
    bewerken.value = false;
    toonKiezer.value = false;
    plekken.value = kopieer(props.layout);
};

/**
 * Opslaan.
 *
 * Op een smal scherm nemen we alleen de volgorde over: daar is geen tweede
 * kolom, dus x en w van het grote scherm blijven staan. Zouden we die
 * overschrijven, dan is je laptopindeling weg zodra je hem op je telefoon
 * aanraakt.
 */
const bewaar = () => {
    const rijen = smal.value
        ? werkRooster.value
              .slice()
              .sort((a, b) => a.y - b.y)
              .map((rij, index) => {
                  const bestaand = plekken.value.find((p) => p.key === rij.i)!;

                  return { key: rij.i, x: bestaand.x, y: index, w: bestaand.size };
              })
        : werkRooster.value.map((rij) => ({ key: rij.i, x: rij.x, y: rij.y, w: rij.w }));

    bezig.value = true;

    router.patch(
        '/dashboard/indeling',
        { widgets: rijen },
        {
            preserveScroll: true,
            onSuccess: sluitAf,
            onFinish: () => (bezig.value = false),
        },
    );
};

const herstel = () => {
    if (!confirm('De standaardindeling terugzetten? Je eigen indeling gaat dan verloren.')) {
        return;
    }

    bezig.value = true;
    router.delete('/dashboard/indeling', {
        preserveScroll: true,
        onSuccess: sluitAf,
        onFinish: () => (bezig.value = false),
    });
};

const verwijder = (key: string) => {
    plekken.value = plekken.value.filter((plek) => plek.key !== key);
};

const voegToe = (widget: Beschikbaar) => {
    // Onderaan erbij: bovenin invoegen zou de rest verschuiven, en dan ben je
    // je eigen indeling kwijt door één klik.
    const onderkant = plekken.value.reduce((laag, plek) => Math.max(laag, plek.y + plek.height), 0);

    plekken.value = [...plekken.value, { key: widget.key, size: widget.sizes[0], height: widget.height, x: 0, y: onderkant }];

    toonKiezer.value = false;
};

/** Naar het volgende toegestane formaat. Alleen op een breed scherm zinvol. */
const wisselFormaat = (key: string) => {
    const widget = props.available.find((w) => w.key === key);

    if (!widget || widget.sizes.length < 2) {
        return;
    }

    plekken.value = plekken.value.map((plek) => {
        if (plek.key !== key) {
            return plek;
        }

        const volgende = widget.sizes[(widget.sizes.indexOf(plek.size) + 1) % widget.sizes.length];

        return { ...plek, size: volgende };
    });
};

const labelVan = (key: string) => props.available.find((w) => w.key === key)?.label ?? key;

const kanWisselen = (key: string) => (props.available.find((w) => w.key === key)?.sizes.length ?? 0) > 1;

// Lang indrukken opent de bewerkmodus, zoals op een beginscherm. Bewegen of
// loslaten breekt af, anders start hij bij elke scroll.
let timer: ReturnType<typeof setTimeout> | undefined;

const houdVast = () => {
    if (bewerken.value) {
        return;
    }

    timer = setTimeout(start, 600);
};

const laatLos = () => clearTimeout(timer);
</script>

<template>
    <div>
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-xs text-muted-foreground">
                <template v-if="bewerken">
                    <template v-if="smal">Sleep om de volgorde te veranderen.</template>
                    <template v-else>Sleep de widgets naar hun plek. Klik op de maat om hem breder of smaller te maken.</template>
                </template>
            </p>

            <div class="flex flex-wrap items-center gap-2">
                <template v-if="bewerken">
                    <button
                        v-if="teVoegen.length"
                        type="button"
                        class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-border bg-card px-3 text-sm font-medium transition hover:border-primary"
                        @click="toonKiezer = !toonKiezer"
                    >
                        <Plus class="size-4" />
                        Widget toevoegen
                    </button>

                    <button
                        type="button"
                        class="inline-flex min-h-11 items-center gap-2 rounded-lg px-2 text-sm text-muted-foreground transition hover:text-foreground"
                        @click="herstel"
                    >
                        <RotateCcw class="size-4" />
                        Standaard
                    </button>

                    <button
                        type="button"
                        class="inline-flex min-h-11 items-center rounded-lg px-3 text-sm text-muted-foreground transition hover:text-foreground"
                        @click="annuleer"
                    >
                        Annuleren
                    </button>

                    <button
                        type="button"
                        class="inline-flex min-h-11 items-center gap-2 rounded-lg bg-primary px-4 text-sm font-semibold text-primary-foreground transition hover:opacity-90 disabled:opacity-60"
                        :disabled="bezig"
                        @click="bewaar"
                    >
                        <Check class="size-4" />
                        Klaar
                    </button>
                </template>

                <button
                    v-else
                    type="button"
                    class="inline-flex min-h-11 items-center gap-2 rounded-lg px-2 text-sm text-muted-foreground transition hover:text-foreground"
                    @click="start"
                >
                    <LayoutGrid class="size-4" />
                    Indeling aanpassen
                </button>
            </div>
        </div>

        <!-- Wat je erbij kunt zetten. Alleen wat er nog niet staat. -->
        <div v-if="bewerken && toonKiezer" class="mt-3 rounded-xl border border-border bg-card p-4 shadow-sm">
            <p class="text-sm font-medium">Widget toevoegen</p>

            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                <button
                    v-for="widget in teVoegen"
                    :key="widget.key"
                    type="button"
                    class="flex items-start gap-3 rounded-lg border border-border p-3 text-left transition hover:border-primary"
                    @click="voegToe(widget)"
                >
                    <Plus class="mt-0.5 size-4 shrink-0 text-primary" />
                    <span class="min-w-0">
                        <span class="block text-sm font-medium">{{ widget.label }}</span>
                        <span class="block text-xs text-muted-foreground">{{ widget.description }}</span>
                    </span>
                </button>
            </div>
        </div>

        <GridLayout
            v-model:layout="werkRooster"
            class="pp-raster mt-2"
            :class="bewerken ? 'pp-bewerken' : ''"
            :col-num="kolommen"
            :row-height="40"
            :margin="[16, 16]"
            :is-draggable="bewerken"
            :is-resizable="false"
            :vertical-compact="true"
            :use-css-transforms="true"
            drag-allow-from=".pp-grip"
        >
            <GridItem v-for="item in werkRooster" :key="item.i" :i="item.i" :x="item.x" :y="item.y" :w="item.w" :h="item.h">
                <div class="relative h-full" @pointerdown="houdVast" @pointerup="laatLos" @pointercancel="laatLos" @pointerleave="laatLos">
                    <slot :name="item.i" />

                    <!--
                        In bewerkmodus ligt er een dekkende laag overheen met
                        alleen de naam en de knoppen. Half doorzichtig was
                        onleesbaar: de knoppen kwamen bovenop de tekst van de
                        widget zelf te staan. Hier gaat het om waar iets staat,
                        niet om wat erin staat.
                    -->
                    <div
                        v-if="bewerken"
                        class="absolute inset-0 flex flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-primary/50 bg-card p-3 text-center"
                    >
                        <button
                            type="button"
                            class="pp-grip flex cursor-grab touch-none items-center gap-2 rounded-lg px-2 py-1 text-sm font-medium text-foreground active:cursor-grabbing"
                            :aria-label="labelVan(item.i) + ' verslepen'"
                        >
                            <GripVertical class="size-4 shrink-0 text-muted-foreground" />
                            <span class="truncate">{{ labelVan(item.i) }}</span>
                        </button>

                        <button
                            v-if="!smal && kanWisselen(item.i)"
                            type="button"
                            class="tabular inline-flex min-h-11 items-center rounded-lg border border-border px-2 text-xs font-medium transition hover:border-primary"
                            :aria-label="'Breedte van ' + labelVan(item.i) + ' wijzigen'"
                            @click="wisselFormaat(item.i)"
                        >
                            {{ item.w }}/12 breed
                        </button>

                        <button
                            type="button"
                            class="absolute right-2 top-2 flex size-11 items-center justify-center rounded-full border border-destructive/30 bg-card text-destructive shadow-sm transition hover:bg-destructive hover:text-background"
                            :aria-label="labelVan(item.i) + ' weghalen'"
                            @click="verwijder(item.i)"
                        >
                            <X class="size-4" />
                        </button>
                    </div>
                </div>
            </GridItem>
        </GridLayout>

        <p
            v-if="bewerken && !plekken.length"
            class="rounded-xl border border-dashed border-border bg-card/50 p-8 text-center text-sm text-muted-foreground"
        >
            Je dashboard is leeg. Voeg een widget toe of zet de standaardindeling terug.
        </p>
    </div>
</template>

<style scoped>
/* De library plaatst zelf absoluut; dit houdt de kaarten netjes op maat. */
.pp-raster :deep(.vgl-item) {
    touch-action: pan-y;
}

.pp-raster :deep(.vgl-item--placeholder) {
    background: hsl(var(--primary) / 0.15);
    border-radius: 0.75rem;
    opacity: 1;
}

/* Wiebelen, zoals op een beginscherm. Uit bij wie daar last van heeft. */
@keyframes pp-wiebel {
    0% {
        transform: rotate(-0.4deg);
    }
    50% {
        transform: rotate(0.4deg);
    }
    100% {
        transform: rotate(-0.4deg);
    }
}

.pp-bewerken :deep(.vgl-item:not(.vgl-item--placeholder)) > * {
    animation: pp-wiebel 0.4s ease-in-out infinite;
}

@media (prefers-reduced-motion: reduce) {
    .pp-bewerken :deep(.vgl-item:not(.vgl-item--placeholder)) > * {
        animation: none;
    }
}
</style>
