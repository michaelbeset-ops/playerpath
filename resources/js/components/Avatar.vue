<script setup lang="ts">
import { computed } from 'vue';

/**
 * Een pasfoto, of de initialen als die er niet is.
 *
 * Eén component voor spelers, ouders en trainers, zodat een lijst er niet
 * ineens anders uitziet zodra iemand wel een foto heeft en zijn buurman niet.
 *
 * De foto is bij het uploaden al vierkant gemaakt (zie ProfilePhoto), dus
 * `object-cover` snijdt hier niets scheef af.
 */
const props = withDefaults(
    defineProps<{
        name: string;
        photo?: string | null;
        /** Tailwind-maat, bijvoorbeeld 'size-11'. */
        size?: string;
        /** Rond (mensen) of afgerond vierkant (past bij de rest van de lijst). */
        round?: boolean;
    }>(),
    { photo: null, size: 'size-11', round: true },
);

// Twee letters: meer wordt onleesbaar klein in een medaillon van 40 pixels.
const initialen = computed(() =>
    props.name
        .split(' ')
        .filter(Boolean)
        .map((deel) => deel[0])
        .slice(0, 2)
        .join('')
        .toUpperCase(),
);
</script>

<template>
    <span
        class="flex shrink-0 items-center justify-center overflow-hidden bg-secondary text-xs font-bold text-muted-foreground"
        :class="[size, round ? 'rounded-full' : 'rounded-lg']"
    >
        <img v-if="photo" :src="photo" :alt="name" class="size-full object-cover" />
        <template v-else>{{ initialen }}</template>
    </span>
</template>
