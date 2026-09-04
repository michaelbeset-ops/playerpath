<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Categorie {
    category: string;
    label: string;
    hint: string;
    rating: number | null;
}

const props = defineProps<{
    player: {
        id: number;
        name: string;
        position: string;
        age: number | null;
        overall_rating: number | null;
        rated_at: string | null;
    };
    categories: Categorie[];
    reportCount: number;
    lastReport: { reported_on: string; trainer: string | null; note: string | null } | null;
    canReport: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Rapporten', href: '/reports' },
    { title: props.player.name, href: '/players/' + props.player.id + '/card' },
];

// De initialen op de kaart, zoals een clubembleem.
const initialen = computed(() =>
    props.player.name
        .split(' ')
        .filter(Boolean)
        .map((deel) => deel[0])
        .slice(0, 2)
        .join('')
        .toUpperCase(),
);

const balkBreedte = (rating: number | null) => (rating === null ? '0%' : rating + '%');
</script>

<template>
    <Head :title="'Spelerskaart - ' + player.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-3xl p-4">
            <!--
                De kaart is bewust donker: dit is de speler/ouder-kant van het
                merk. Zie CLAUDE.md hoofdstuk 4.
            -->
            <div class="theme-donker overflow-hidden rounded-2xl border border-border bg-background text-foreground">
                <div class="flex items-center gap-5 border-b border-border p-6">
                    <div class="flex size-16 shrink-0 items-center justify-center rounded-2xl bg-gold/15 text-xl font-bold text-gold">
                        {{ initialen }}
                    </div>

                    <div class="min-w-0 flex-1">
                        <p class="text-xs uppercase tracking-widest text-gold">{{ player.position }}</p>
                        <h1 class="truncate text-2xl font-bold tracking-tight">{{ player.name }}</h1>
                        <p class="mt-0.5 text-sm text-muted-foreground">
                            <span v-if="player.age">{{ player.age }} jaar &middot; </span>
                            <span class="tabular">{{ reportCount }}</span>
                            {{ reportCount === 1 ? 'rapport' : 'rapporten' }}
                        </p>
                    </div>

                    <div class="shrink-0 text-right">
                        <p class="text-[10px] uppercase tracking-widest text-muted-foreground">Overall</p>
                        <p class="tabular text-5xl font-extrabold leading-none" :class="player.overall_rating ? 'text-primary' : 'text-muted-foreground'">
                            {{ player.overall_rating ?? '—' }}
                        </p>
                    </div>
                </div>

                <div v-if="player.overall_rating" class="grid gap-x-8 gap-y-4 p-6 sm:grid-cols-2">
                    <div v-for="categorie in categories" :key="categorie.category">
                        <div class="flex items-baseline justify-between gap-3">
                            <p class="text-sm font-medium">{{ categorie.label }}</p>
                            <p class="tabular text-lg font-bold leading-none" :class="categorie.rating === null ? 'text-muted-foreground' : ''">
                                {{ categorie.rating ?? '—' }}
                            </p>
                        </div>
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-secondary">
                            <div class="h-full rounded-full bg-primary transition-all duration-500" :style="{ width: balkBreedte(categorie.rating) }"></div>
                        </div>
                    </div>
                </div>

                <div v-else class="p-10 text-center">
                    <p class="font-medium">Nog geen cijfers</p>
                    <p class="mt-1 text-sm text-muted-foreground">Zodra een trainer het eerste rapport invult, komt deze kaart tot leven.</p>
                </div>

                <p v-if="player.rated_at" class="border-t border-border px-6 py-3 text-xs text-muted-foreground">
                    Bijgewerkt op {{ player.rated_at }} &middot; gemiddelde van de laatste 3 rapporten
                </p>
            </div>

            <!-- Toelichting en actie staan buiten de kaart, in de lichte admin-omgeving -->
            <div v-if="lastReport" class="mt-4 rounded-xl border border-border bg-card p-4 shadow-sm">
                <p class="text-sm font-medium">Laatste rapport</p>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ lastReport.reported_on }}<span v-if="lastReport.trainer"> door {{ lastReport.trainer }}</span>
                </p>
                <p v-if="lastReport.note" class="mt-3 text-sm">{{ lastReport.note }}</p>
            </div>

            <div v-if="canReport" class="mt-4">
                <Link
                    :href="'/players/' + player.id + '/reports/create'"
                    class="inline-flex items-center rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                >
                    Nieuw rapport invullen
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
