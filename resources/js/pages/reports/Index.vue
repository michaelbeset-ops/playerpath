<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface SpelerRij {
    id: number;
    name: string;
    position: string;
    age: number | null;
    overall_rating: number | null;
    last_report_on: string | null;
}

const props = defineProps<{ players: SpelerRij[]; group: string | null }>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Rapporten', href: '/reports' }];

const zoek = ref('');

const gefilterd = computed(() => {
    const term = zoek.value.trim().toLowerCase();

    return term === '' ? props.players : props.players.filter((speler) => speler.name.toLowerCase().includes(term));
});
</script>

<template>
    <Head title="Rapporten" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-3xl p-4">
            <h1 class="text-2xl font-semibold tracking-tight">Rapporten</h1>
            <p class="mt-1 text-sm text-muted-foreground">Kies een speler om te beoordelen.</p>

            <!-- Kom je hier vanuit een training, dan gaat het om die groep. -->
            <div
                v-if="group"
                class="mt-3 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-border bg-secondary/50 px-3 py-2 text-sm"
            >
                <span
                    >Alleen de spelers van <strong>{{ group }}</strong></span
                >
                <Link href="/reports" class="font-medium text-primary hover:underline">Toon alle spelers</Link>
            </div>

            <input
                v-model="zoek"
                type="search"
                placeholder="Zoek een speler..."
                class="mt-4 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm outline-none focus:border-primary"
            />

            <div v-if="gefilterd.length" class="mt-4 space-y-2">
                <Link
                    v-for="speler in gefilterd"
                    :key="speler.id"
                    :href="'/players/' + speler.id + '/reports/create'"
                    class="flex items-center gap-4 rounded-xl border border-border bg-card p-4 shadow-sm transition hover:border-primary"
                >
                    <div
                        class="tabular flex size-12 shrink-0 items-center justify-center rounded-lg text-lg font-bold"
                        :class="speler.overall_rating ? 'bg-primary/10 text-primary' : 'bg-secondary text-muted-foreground'"
                    >
                        {{ speler.overall_rating ?? '—' }}
                    </div>

                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium">{{ speler.name }}</p>
                        <p class="truncate text-xs text-muted-foreground">
                            {{ speler.position }}<span v-if="speler.age"> &middot; {{ speler.age }} jaar</span>
                            <span v-if="speler.last_report_on"> &middot; laatst beoordeeld {{ speler.last_report_on }}</span>
                            <span v-else> &middot; nog geen rapport</span>
                        </p>
                    </div>

                    <ChevronRight class="size-5 shrink-0 text-muted-foreground" />
                </Link>
            </div>

            <div v-else class="mt-4 rounded-2xl border border-dashed border-border bg-card/50 p-10 text-center">
                <p class="font-medium">Geen spelers gevonden</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ players.length ? 'Pas je zoekopdracht aan.' : 'Deze school heeft nog geen spelers. Die voeg je toe in fase 3.' }}
                </p>
            </div>
        </div>
    </AppLayout>
</template>
