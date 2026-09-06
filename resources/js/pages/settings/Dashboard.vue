<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { TransitionRoot } from '@headlessui/vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Optie {
    key: string;
    label: string;
    description: string;
    enabled: boolean;
}

const props = defineProps<{
    tiles: Optie[];
    blocks: Optie[];
}>();

const breadcrumbItems: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/settings/dashboard' }];

const form = useForm({
    tiles: Object.fromEntries(props.tiles.map((t) => [t.key, t.enabled])) as Record<string, boolean>,
    blocks: Object.fromEntries(props.blocks.map((b) => [b.key, b.enabled])) as Record<string, boolean>,
});

const aantalTegels = computed(() => Object.values(form.tiles).filter(Boolean).length);

const opslaan = () => form.patch('/settings/dashboard', { preserveScroll: true });
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbItems">
        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall title="Dashboard" description="Kies welke cijfers en blokken je op je dashboard ziet." />

                <form class="space-y-8" @submit.prevent="opslaan">
                    <div class="space-y-3">
                        <div class="flex items-baseline justify-between gap-3">
                            <p class="text-sm font-medium">Kerncijfers</p>
                            <p class="tabular text-xs text-muted-foreground">{{ aantalTegels }} gekozen</p>
                        </div>

                        <label
                            v-for="tegel in tiles"
                            :key="tegel.key"
                            class="flex cursor-pointer items-start gap-3 rounded-lg border border-border p-3 transition hover:border-primary/40"
                        >
                            <input
                                v-model="form.tiles[tegel.key]"
                                type="checkbox"
                                class="mt-0.5 size-4 shrink-0 rounded border-input accent-primary"
                            />
                            <span class="min-w-0">
                                <span class="block text-sm font-medium">{{ tegel.label }}</span>
                                <span class="block text-xs text-muted-foreground">{{ tegel.description }}</span>
                            </span>
                        </label>

                        <p v-if="aantalTegels > 6" class="rounded-lg bg-secondary px-3 py-2 text-xs text-muted-foreground">
                            Meer dan zes cijfers naast elkaar leest als een muur. Zet er liever een paar uit dan alles aan.
                        </p>
                    </div>

                    <div class="space-y-3">
                        <p class="text-sm font-medium">Blokken</p>

                        <label
                            v-for="blok in blocks"
                            :key="blok.key"
                            class="flex cursor-pointer items-start gap-3 rounded-lg border border-border p-3 transition hover:border-primary/40"
                        >
                            <input
                                v-model="form.blocks[blok.key]"
                                type="checkbox"
                                class="mt-0.5 size-4 shrink-0 rounded border-input accent-primary"
                            />
                            <span class="min-w-0">
                                <span class="block text-sm font-medium">{{ blok.label }}</span>
                                <span class="block text-xs text-muted-foreground">{{ blok.description }}</span>
                            </span>
                        </label>
                    </div>

                    <p class="rounded-lg bg-secondary px-3 py-2 text-xs text-muted-foreground">
                        Dit geldt alleen voor jou. Een trainer kijkt naar zijn rapporten en jij naar je omzet; die twee op één instelling zetten
                        betekent dat er altijd één van de twee ontevreden is.
                    </p>

                    <div class="flex items-center gap-4">
                        <Button type="submit" :disabled="form.processing">Opslaan</Button>

                        <TransitionRoot
                            :show="form.recentlySuccessful"
                            enter="transition ease-in-out"
                            enter-from="opacity-0"
                            leave="transition ease-in-out"
                            leave-to="opacity-0"
                        >
                            <p class="text-sm text-muted-foreground">Opgeslagen.</p>
                        </TransitionRoot>
                    </div>
                </form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
