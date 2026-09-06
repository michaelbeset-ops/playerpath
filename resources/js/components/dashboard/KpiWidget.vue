<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowDownRight, ArrowRight, ArrowUpRight } from 'lucide-vue-next';
import { computed, type Component } from 'vue';

/**
 * Eén kerncijfer met zijn trend.
 *
 * Een getal zonder vergelijking zegt weinig: "drie spelers" kan geweldig of
 * rampzalig zijn. Daarom staat er altijd bij wat het deed.
 *
 * De pijl is nooit het enige teken van richting — er staat een getal naast en
 * het woord "meer" of "minder" in de toelichting. Kleur alleen zou betekenen
 * dat wie kleuren slecht onderscheidt niets ziet.
 */
const props = defineProps<{
    label: string;
    value: number | string | null;
    change: number | null;
    unit: 'aantal' | 'punten' | 'procent';
    hint?: string | null;
    icon: Component;
    href?: string;
    /**
     * Is stijgen goed? Bij openstaande rekeningen niet, en dan zou groen
     * het tegendeel zeggen van wat er staat.
     */
    higherIsBetter?: boolean;
}>();

const heeftWaarde = computed(() => props.value !== null && props.value !== undefined);

const richting = computed(() => {
    if (props.change === null || props.change === 0) {
        return 'gelijk';
    }

    return props.change > 0 ? 'omhoog' : 'omlaag';
});

const goed = computed(() => {
    if (richting.value === 'gelijk') {
        return null;
    }

    return (richting.value === 'omhoog') === (props.higherIsBetter ?? true);
});

const trendKleur = computed(() => {
    if (goed.value === null) {
        return 'text-muted-foreground';
    }

    return goed.value ? 'text-primary' : 'text-warning';
});

const pijl = computed(() => (richting.value === 'omhoog' ? ArrowUpRight : richting.value === 'omlaag' ? ArrowDownRight : ArrowRight));

const verschil = computed(() => {
    if (props.change === null) {
        return null;
    }

    const teken = props.change > 0 ? '+' : '';

    return props.unit === 'procent' ? `${teken}${props.change}%` : `${teken}${props.change}`;
});
</script>

<template>
    <component
        :is="href ? Link : 'div'"
        :href="href"
        class="group flex h-full flex-col justify-between overflow-hidden rounded-xl border border-border bg-card p-4 shadow-sm transition"
        :class="href ? 'hover:border-primary/40 hover:shadow' : ''"
    >
        <div class="flex items-start justify-between gap-2">
            <p class="text-xs font-medium leading-snug text-muted-foreground sm:text-sm">{{ label }}</p>
            <span
                class="flex size-8 shrink-0 items-center justify-center rounded-lg"
                :class="heeftWaarde ? 'bg-primary/10 text-primary' : 'bg-secondary text-muted-foreground/70'"
            >
                <component :is="icon" class="size-4" />
            </span>
        </div>

        <div class="mt-2">
            <p class="tabular text-2xl font-bold leading-none sm:text-3xl" :class="heeftWaarde ? 'text-foreground' : 'text-muted-foreground/60'">
                {{ heeftWaarde ? value : '—' }}
            </p>

            <p v-if="verschil !== null" class="tabular mt-1.5 flex items-center gap-1 text-xs font-medium" :class="trendKleur">
                <component :is="pijl" class="size-3.5 shrink-0" />
                {{ verschil }}
            </p>
        </div>

        <p v-if="hint" class="mt-2 text-[11px] leading-snug text-muted-foreground">{{ hint }}</p>
    </component>
</template>
