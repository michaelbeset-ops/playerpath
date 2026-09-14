<script setup lang="ts">
import { kleurVan, useGrading } from '@/lib/grade';
import { computed } from 'vue';

/**
 * Een rating: als kleur met label, of - bij een school die in cijfers werkt -
 * gewoon het getal uit de slot.
 *
 * Zo verandert een scherm niet van opbouw: waar eerst `{{ rating }}` stond,
 * staat nu `<GradeChip :rating="rating">{{ rating }}</GradeChip>`. De school
 * bepaalt wat er te zien is (Support\Rating\Grade).
 */
const props = withDefaults(
    defineProps<{
        /** Kaartwaarde van 0 tot 100, of null zonder rapport. */
        rating: number | null | undefined;
        size?: 'sm' | 'md' | 'lg';
        /** Voor de publieke kaart: de modus uit de kaart zelf in plaats van de gedeelde prop. */
        mode?: string;
    }>(),
    { size: 'md', mode: undefined },
);

const { kleuren, niveauVoor } = useGrading(() => props.mode);

const niveau = computed(() => niveauVoor(props.rating));

const maat = computed(
    () =>
        ({
            sm: 'gap-1 px-1.5 py-0.5 text-[11px]',
            md: 'gap-1.5 px-2 py-0.5 text-xs',
            lg: 'gap-2 px-3 py-1 text-sm',
        })[props.size],
);
</script>

<template>
    <slot v-if="!kleuren" />
    <span
        v-else-if="niveau"
        class="inline-flex shrink-0 items-center whitespace-nowrap rounded-full font-semibold"
        :class="maat"
        :style="{ color: kleurVan(niveau.key), backgroundColor: kleurVan(niveau.key, 0.12) }"
        :title="niveau.label"
    >
        <span class="inline-block size-2 shrink-0 rounded-full" :style="{ backgroundColor: kleurVan(niveau.key) }" aria-hidden="true"></span>
        {{ niveau.label }}
    </span>
    <span v-else class="inline-flex shrink-0 items-center rounded-full bg-secondary font-medium text-muted-foreground" :class="maat">Nog geen</span>
</template>
