<script setup lang="ts">
import AppWordmark from '@/components/AppWordmark.vue';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps<{
    title?: string;
    description?: string;
}>();

// Op het eigen adres van een school ziet een ouder meteen haar logo en naam,
// nog voordat hij inlogt. Zonder school blijft het PlayerPath.
const page = usePage<SharedData>();
const logo = computed(() => page.props.branding?.logo ?? null);
const naam = computed(() => page.props.branding?.name ?? null);
</script>

<template>
    <div class="theme-donker flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 text-foreground md:p-10">
        <div class="w-full max-w-sm">
            <div class="flex flex-col gap-8">
                <div class="flex flex-col items-center gap-4">
                    <Link :href="route('home')" class="flex flex-col items-center gap-2 font-medium">
                        <div class="mb-1 flex h-12 items-center justify-center rounded-md">
                            <img v-if="logo" :src="logo" :alt="naam ?? ''" class="max-h-12 max-w-[180px] object-contain" />
                            <AppWordmark v-else donker size="md" />
                        </div>
                        <span v-if="naam" class="text-sm text-muted-foreground">{{ naam }}</span>
                        <span class="sr-only">{{ title }}</span>
                    </Link>
                    <div class="space-y-2 text-center">
                        <h1 class="text-xl font-medium">{{ title }}</h1>
                        <p class="text-center text-sm text-muted-foreground">{{ description }}</p>
                    </div>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
