<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * Eén-serie lijngrafiek voor "cijfer over de tijd".
 *
 * Bewust één serie: zes categorieën in één grafiek wordt spaghetti. De
 * voortgangspagina zet er daarom zes kleine naast elkaar (small multiples),
 * elk met hun eigen titel - dan hoeft er geen legenda bij en is kleur nooit
 * de enige drager van betekenis.
 *
 * De lijnkleur komt uit --chart-1, die per thema is gecontroleerd op
 * contrast met het vlak eronder.
 */
const props = withDefaults(
    defineProps<{
        /** Cijfers 0-100; null betekent "niet gescoord in dat rapport". */
        series: (number | null)[];
        /** Labels per punt, voor de tooltip. */
        labels: string[];
        height?: number;
        /** Terugvalbreedte tot de echte breedte gemeten is. */
        width?: number;
        /** Toont het laatste cijfer als label aan het eind van de lijn. */
        showEndLabel?: boolean;
    }>(),
    { height: 120, width: 300, showEndLabel: true },
);

/*
 * De viewBox is even breed als het vak waarin de grafiek staat, gemeten met
 * een ResizeObserver. Daardoor is één viewBox-eenheid altijd één beeldpunt en
 * blijft de tekst op elk scherm even groot.
 *
 * Met een vaste breedte ging dat mis: de bovenste grafiek had een viewBox van
 * 640 in een vak van 301 breed, dus alles schaalde met 0,47 mee en werden de
 * aslabels vier pixels hoog - op precies het scherm waarop een ouder kijkt.
 */
const vak = ref<HTMLElement | null>(null);
const gemeten = ref<number | null>(null);
let waarnemer: ResizeObserver | null = null;

onMounted(() => {
    if (vak.value === null || typeof ResizeObserver === 'undefined') {
        return;
    }

    waarnemer = new ResizeObserver(([entry]) => {
        const breed = Math.round(entry.contentRect.width);

        if (breed > 0) {
            gemeten.value = breed;
        }
    });

    waarnemer.observe(vak.value);
});

onBeforeUnmount(() => waarnemer?.disconnect());

const breedte = computed(() => gemeten.value ?? props.width);
// Rechts is ruimte voor het eindlabel. Staat dat uit, dan is veertig pixels
// wit aan de rechterkant van een grafiekje van 300 breed zonde.
const padding = computed(() => ({ top: 14, right: props.showEndLabel ? 40 : 12, bottom: 18, left: 28 }));

const punten = computed(() =>
    props.series.map((waarde, index) => ({ waarde, index })).filter((p): p is { waarde: number; index: number } => p.waarde !== null),
);

// Vaste schaal 0-100: cijfers zijn altijd op die schaal, en een meebewegende
// as zou kleine schommelingen als grote sprongen laten lezen.
const minY = 0;
const maxY = 100;

const x = (index: number) => {
    const bruikbaar = breedte.value - padding.value.left - padding.value.right;

    if (props.series.length <= 1) {
        return padding.value.left + bruikbaar / 2;
    }

    return padding.value.left + (index / (props.series.length - 1)) * bruikbaar;
};

const y = (waarde: number) => {
    const bruikbaar = props.height - padding.value.top - padding.value.bottom;

    return padding.value.top + (1 - (waarde - minY) / (maxY - minY)) * bruikbaar;
};

const pad = computed(() => punten.value.map((p, i) => `${i === 0 ? 'M' : 'L'} ${x(p.index)} ${y(p.waarde)}`).join(' '));

const vlak = computed(() => {
    if (punten.value.length < 2) {
        return '';
    }

    const eerste = punten.value[0];
    const laatste = punten.value[punten.value.length - 1];
    const onder = props.height - padding.value.bottom;

    return `${pad.value} L ${x(laatste.index)} ${onder} L ${x(eerste.index)} ${onder} Z`;
});

const laatste = computed(() => punten.value[punten.value.length - 1] ?? null);

const actief = ref<number | null>(null);
</script>

<template>
    <div ref="vak" class="relative">
        <svg :viewBox="`0 0 ${breedte} ${height}`" class="w-full" role="img" :aria-label="'Verloop van ' + series.length + ' rapporten'">
            <!-- Hulplijnen: hairline, terughoudend -->
            <line
                v-for="waarde in [0, 50, 100]"
                :key="waarde"
                :x1="padding.left"
                :x2="breedte - padding.right"
                :y1="y(waarde)"
                :y2="y(waarde)"
                stroke="hsl(var(--border))"
                stroke-width="1"
            />
            <text
                v-for="waarde in [0, 50, 100]"
                :key="'label-' + waarde"
                :x="padding.left - 6"
                :y="y(waarde) + 3"
                text-anchor="end"
                class="fill-[hsl(var(--muted-foreground))] text-[10px]"
            >
                {{ waarde }}
            </text>

            <!-- Wash onder de lijn, ~10% -->
            <path v-if="vlak" :d="vlak" fill="hsl(var(--chart-1))" fill-opacity="0.1" />

            <!-- De lijn: 2px, ronde hoeken -->
            <path :d="pad" fill="none" stroke="hsl(var(--chart-1))" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />

            <!-- Punten met een ring in de vlakkleur, zodat ze leesbaar blijven -->
            <g v-for="p in punten" :key="'punt-' + p.index">
                <circle :cx="x(p.index)" :cy="y(p.waarde)" r="4" fill="hsl(var(--chart-1))" stroke="hsl(var(--card))" stroke-width="2" />
                <!-- Ruim treffervlak voor de tooltip -->
                <circle
                    :cx="x(p.index)"
                    :cy="y(p.waarde)"
                    r="12"
                    fill="transparent"
                    class="cursor-pointer"
                    @mouseenter="actief = p.index"
                    @mouseleave="actief = null"
                />
            </g>

            <!-- Alleen het eindpunt krijgt een cijfer; een label bij elk punt leest niemand -->
            <text
                v-if="showEndLabel && laatste"
                :x="x(laatste.index) + 8"
                :y="y(laatste.waarde) + 4"
                class="fill-[hsl(var(--foreground))] text-[11px] font-bold"
            >
                {{ laatste.waarde }}
            </text>
        </svg>

        <div
            v-if="actief !== null && series[actief] !== null"
            class="pointer-events-none absolute -top-1 left-1/2 -translate-x-1/2 rounded-lg border border-border bg-popover px-2 py-1 text-xs shadow-sm"
        >
            <span class="font-semibold">{{ series[actief] }}</span>
            <span class="text-muted-foreground"> &middot; {{ labels[actief] }}</span>
        </div>
    </div>
</template>
