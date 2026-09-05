<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Props {
    class?: string;
}

defineProps<Props>();

const page = usePage<SharedData>();

// Heeft de school een eigen logo, dan staat dat er; anders het merkteken van
// PlayerPath in de merkkleur. De naam volgt hetzelfde principe.
const logo = computed(() => page.props.branding?.logo ?? null);
const naam = computed(() => page.props.branding?.name ?? 'PlayerPath');
</script>

<template>
    <div v-if="logo" class="flex aspect-square size-8 items-center justify-center overflow-hidden rounded-lg bg-card">
        <img :src="logo" :alt="naam" class="size-full object-contain" />
    </div>
    <div v-else class="flex aspect-square size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground">
        <AppLogoIcon class="size-5" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate font-semibold leading-none">{{ naam }}</span>
    </div>
</template>
