<script setup lang="ts">
import PlayerCardVisual, { type Kaart } from '@/components/PlayerCardVisual.vue';
import { Check, Sparkles } from 'lucide-vue-next';

/**
 * De keuze tussen de twee spelerskaarten, met beide kaarten naast elkaar.
 *
 * Gebruikt in de wizard en op Mijn bedrijf → Spelerskaart. De voorbeelden
 * zijn de echte kaartcomponent met verzonnen gegevens: een plaatje zou
 * verouderen zodra de kaart verandert, dit niet.
 */
// Leeg is mogelijk: in de onboarding staat er niets voorgekozen.
const model = defineModel<'prestatie' | 'inzet' | null>({ required: true });

const basis = {
    first_name: 'Sem',
    last_name: 'de Vries',
    name: 'Sem de Vries',
    photo: null,
    position: 'Keeper',
    position_key: 'keeper' as const,
    shirt_number: 1,
    card_number: '#0007',
    age_category: { key: 'O12', label: 'Onder 12' },
    moved_up: false,
    levels: [
        { key: 'brons', label: 'Brons', xp: 0 },
        { key: 'zilver', label: 'Zilver', xp: 251 },
        { key: 'goud', label: 'Goud', xp: 501 },
        { key: 'elite', label: 'Special', xp: 751 },
    ],
    season: '2026/27',
    season_label: 'Najaar 2026',
    school: null,
};

const prestatie: Kaart = {
    ...basis,
    card_mode: 'prestatie',
    grading: 'cijfers',
    overall: 74,
    categories: [
        { category: 'reflexen', label: 'Reflexen', rating: 81, delta: 3 },
        { category: 'uitkomen', label: 'Uitkomen', rating: 68, delta: null },
        { category: 'voetenwerk', label: 'Voetenwerk', rating: 72, delta: 2 },
        { category: 'een_op_een', label: '1-op-1', rating: 77, delta: null },
        { category: 'hoge_ballen', label: 'Hoge ballen', rating: 70, delta: null },
        { category: 'communicatie', label: 'Communicatie', rating: 76, delta: 1 },
    ],
    report_count: 6,
    level: { key: 'zilver', label: 'Zilver', xp: 320, next: { key: 'goud', label: 'Goud', xp: 501, remaining: 181 }, progress: 28 },
    badges: [
        { key: 'eerste_rapport', label: 'Op de kaart', description: 'Je eerste rapport is binnen' },
        { key: 'groei', label: 'In de lift', description: 'Vijf punten gegroeid' },
    ],
    recent_reports: null,
};

const inzet: Kaart = {
    ...basis,
    card_mode: 'inzet',
    overall: null,
    categories: [],
    report_count: 0,
    effort: { points: 461, trainings: 11, standouts: 4, standout_label: 'Uitblinker' },
    level: { key: 'zilver', label: 'Zilver', xp: 461, next: { key: 'goud', label: 'Goud', xp: 501, remaining: 40 }, progress: 84 },
    badges: [
        { key: 'aanwezig_vijf', label: 'Altijd op tijd', description: 'Vijf trainingen aanwezig' },
        { key: 'doorzetter', label: 'Doorzetter', description: 'Vijf keer hard gewerkt' },
    ],
    recent_reports: null,
};

const opties = [
    {
        value: 'inzet' as const,
        title: 'Inzetkaart',
        recommended: true,
        text: 'Spelers verdienen punten met aanwezigheid en inzet, niet met talent. Iedereen start gelijk en punten kunnen alleen stijgen. Het kind dat het hardst werkt, krijgt de mooiste kaart. Voorkomt vergelijken en motiveert elk kind op zijn eigen niveau.',
        card: inzet,
    },
    {
        value: 'prestatie' as const,
        title: 'Prestatiekaart met ratings',
        recommended: false,
        text: 'Spelers krijgen een cijfer per categorie en een overall rating, zoals in een voetbalgame. Duidelijk en herkenbaar. Let op: kinderen kunnen zich hierdoor met elkaar gaan vergelijken.',
        card: prestatie,
    },
];
</script>

<template>
    <div class="grid grid-cols-2 gap-3" role="radiogroup" aria-label="Kies een spelerskaart">
        <button
            v-for="optie in opties"
            :key="optie.value"
            type="button"
            role="radio"
            :aria-checked="model === optie.value"
            class="relative flex min-w-0 flex-col rounded-2xl border-2 bg-card p-2.5 text-left shadow-sm transition sm:p-4"
            :class="model === optie.value ? 'border-primary ring-2 ring-primary/20' : 'border-border hover:border-primary/50'"
            @click="model = optie.value"
        >
            <span
                v-if="model === optie.value"
                class="absolute right-2 top-2 z-10 flex size-6 items-center justify-center rounded-full bg-primary text-primary-foreground"
            >
                <Check class="size-4" />
            </span>

            <!-- Het voorbeeld: de echte kaart, verkleind en niet aanklikbaar. -->
            <span class="theme-donker pointer-events-none block overflow-hidden rounded-xl bg-background px-1 py-2" aria-hidden="true">
                <span class="cv-voorbeeld block">
                    <PlayerCardVisual :card="optie.card" :shareable="false" />
                </span>
            </span>

            <span class="mt-3 flex flex-wrap items-center gap-1.5">
                <span class="text-sm font-semibold leading-tight sm:text-base">{{ optie.title }}</span>
                <span
                    v-if="optie.recommended"
                    class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2 py-0.5 text-[11px] font-semibold text-primary"
                >
                    <Sparkles class="size-3" />
                    Aanbevolen
                </span>
            </span>
            <span class="mt-1 text-xs leading-relaxed text-muted-foreground sm:text-sm">{{ optie.text }}</span>
        </button>
    </div>
</template>

<style scoped>
/* Twee kaarten naast elkaar op 375 pixels: de kaart is 22,5rem breed, dus
   verkleinen met zoom (dat verkleint ook de ruimte die hij inneemt). */
.cv-voorbeeld {
    zoom: 0.42;
}

@media (min-width: 640px) {
    .cv-voorbeeld {
        zoom: 0.62;
    }
}

/* De knoppen onder de kaart horen niet bij een voorbeeld. */
.cv-voorbeeld :deep(.pp-acties) {
    display: none;
}
</style>
