<script setup lang="ts">
import CardFaq from '@/components/CardFaq.vue';
import type { Kaart } from '@/components/PlayerCardVisual.vue';
import { Info } from 'lucide-vue-next';
import { computed, ref } from 'vue';

/**
 * De balk onder de kaart: waar je staat tussen brons en goud, en hoeveel
 * punten er nog nodig zijn voor de volgende kaart. "Nog 35 XP tot de Gouden
 * Kaart!" is de zin die een kind onthoudt. Met een (i) voor "Hoe werkt dit?".
 */
const props = defineProps<{ card: Kaart }>();

const faqOpen = ref(false);

const kleur: Record<string, string> = {
    brons: 'bg-amber-700',
    zilver: 'bg-slate-400',
    goud: 'bg-gold',
    elite: 'bg-primary',
};

const bijvoeglijk: Record<string, string> = {
    brons: 'Bronzen',
    zilver: 'Zilveren',
    goud: 'Gouden',
    elite: 'Special',
};

const levels = computed(() => props.card.levels);
const huidigIndex = computed(() => Math.max(0, levels.value.findIndex((l) => l.key === props.card.level.key)));

/** Positie op de hele balk (alle levels), niet alleen binnen het huidige level. */
const positie = computed(() => {
    const xp = props.card.level.xp;
    const top = levels.value[levels.value.length - 1]?.xp ?? 1;

    if (top <= 0) {
        return 100;
    }

    return Math.min(100, Math.round((xp / top) * 100));
});

const volgende = computed(() => props.card.level.next);

const zin = computed(() => {
    if (props.card.overall === null && props.card.level.xp === 0) {
        return 'Je eerste training levert je eerste punten op.';
    }

    if (!volgende.value) {
        return 'Je hebt de hoogste kaart. Respect.';
    }

    return `Nog ${volgende.value.remaining} XP tot de ${bijvoeglijk[volgende.value.key] ?? volgende.value.label} Kaart!`;
});
</script>

<template>
    <div class="theme-donker rounded-2xl bg-background p-4 text-foreground">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-sm font-semibold leading-snug">{{ zin }}</p>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    <span class="tabular">{{ card.level.xp }} XP</span> · {{ card.level.label }}
                    <template v-if="card.season_label"> · {{ card.season_label }}</template>
                    <template v-if="card.season_week"> · {{ card.season_week }}</template>
                </p>
            </div>
            <button
                type="button"
                class="flex size-11 shrink-0 items-center justify-center rounded-full border border-border text-muted-foreground transition hover:border-primary hover:text-foreground"
                aria-label="Hoe werkt dit?"
                title="Hoe werkt dit?"
                @click="faqOpen = true"
            >
                <Info class="size-4" />
            </button>
        </div>

        <!-- De balk over alle levels, met de grenzen erop -->
        <div class="relative mt-4 h-2.5 rounded-full bg-secondary">
            <div class="h-full rounded-full transition-all" :class="kleur[card.level.key] ?? 'bg-primary'" :style="{ width: positie + '%' }"></div>
            <span
                v-for="(l, i) in levels"
                :key="l.key"
                class="absolute top-1/2 size-3 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-background"
                :class="i <= huidigIndex ? (kleur[l.key] ?? 'bg-primary') : 'bg-border'"
                :style="{ left: (levels[levels.length - 1]?.xp ? (l.xp / levels[levels.length - 1].xp) * 100 : 0) + '%' }"
                :title="l.label + ' vanaf ' + l.xp + ' XP'"
            ></span>
        </div>

        <div class="mt-2 flex justify-between text-[11px] text-muted-foreground">
            <span v-for="(l, i) in levels" :key="l.key" :class="i === huidigIndex ? 'font-semibold text-foreground' : ''">
                <span>{{ l.label }}</span>
                <span v-if="l.xp > 0" class="tabular ml-1 opacity-70">{{ l.xp }}</span>
            </span>
        </div>

        <CardFaq v-model:open="faqOpen" :card="card" />
    </div>
</template>
