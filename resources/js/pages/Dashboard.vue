<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

// Fase 0: bewust nog leeg. De kaarten hieronder zijn plaatshouders die in de
// volgende fases gevuld worden met echte cijfers.
const kaarten = [
    { titel: 'Spelers', hint: 'Komt in fase 3' },
    { titel: 'Trainingen deze week', hint: 'Komt in fase 4' },
    { titel: 'Nieuwe rapporten', hint: 'Komt in fase 2' },
];
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Dashboard</h1>
                <p class="mt-1 text-sm text-muted-foreground">Welkom bij PlayerPath.</p>
            </div>

            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <div v-for="kaart in kaarten" :key="kaart.titel" class="rounded-xl border border-border bg-card p-5 shadow-sm">
                    <p class="text-sm font-medium text-muted-foreground">{{ kaart.titel }}</p>
                    <p class="tabular mt-2 text-3xl font-bold leading-none text-foreground">—</p>
                    <p class="mt-2 text-xs text-muted-foreground">{{ kaart.hint }}</p>
                </div>
            </div>

            <div class="flex flex-1 items-center justify-center rounded-2xl border border-dashed border-border bg-card/50 p-10 text-center">
                <div>
                    <p class="font-medium">Nog niets te zien</p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Het fundament staat. Spelers, groepen, trainingen en rapporten volgen in de volgende fases.
                    </p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
