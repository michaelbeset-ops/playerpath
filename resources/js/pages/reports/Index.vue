<script setup lang="ts">
import { kleurVan, useGrading } from '@/lib/grade';
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
    days_since_report: number | null;
}

const props = defineProps<{ players: SpelerRij[]; group: string | null; staleAfterDays: number; canCreatePlayer: boolean }>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Rapporten', href: '/reports' }];

const zoek = ref('');

/**
 * Wanneer is een speler voor het laatst beoordeeld?
 *
 * Groen als het recent is, oranje zodra het te lang geleden is. De grens komt
 * van de server (dezelfde 30 dagen als het aandacht-blok op het dashboard),
 * want "te lang geleden" hoort overal hetzelfde te betekenen. Een speler zonder
 * enig rapport is geen neutraal geval maar precies waar het product stilvalt:
 * een lege kaart is de reden dat een ouder afhaakt.
 */
const beoordeling = (speler: SpelerRij) => {
    if (speler.days_since_report === null) {
        return { tekst: 'nog geen rapport', oud: true };
    }

    const dagen = speler.days_since_report;

    return {
        tekst: dagen === 0 ? 'vandaag beoordeeld' : dagen === 1 ? 'gisteren beoordeeld' : `${dagen} dagen geleden`,
        oud: dagen > props.staleAfterDays,
    };
};

const gefilterd = computed(() => {
    const term = zoek.value.trim().toLowerCase();

    return term === '' ? props.players : props.players.filter((speler) => speler.name.toLowerCase().includes(term));
});

// In kleuren een gekleurde stip in plaats van het cijfer.
const { kleuren, niveauVoor } = useGrading();
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
                class="mt-4 min-h-11 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm outline-none focus:border-primary"
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
                        <template v-if="!kleuren">{{ speler.overall_rating ?? '-' }}</template><span v-else class="size-4 rounded-full" :style="{ backgroundColor: kleurVan(niveauVoor(speler.overall_rating)?.key, niveauVoor(speler.overall_rating) ? 1 : 0.25) }" :title="niveauVoor(speler.overall_rating)?.label ?? 'Nog geen rapport'"></span>
                    </div>

                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium">{{ speler.name }}</p>
                        <p class="truncate text-xs text-muted-foreground">
                            {{ speler.position }}<span v-if="speler.age"> &middot; {{ speler.age }} jaar</span>
                        </p>
                        <!-- Laatst beoordeeld, met een kleur: zo zie je in één
                             oogopslag waar het stilvalt. -->
                        <span
                            class="mt-1 inline-flex items-center gap-1.5 rounded-lg px-2 py-0.5 text-xs font-medium"
                            :class="beoordeling(speler).oud ? 'bg-warning/10 text-warning' : 'bg-success/10 text-success'"
                            :title="speler.last_report_on ? 'Laatst beoordeeld op ' + speler.last_report_on : 'Nog nooit beoordeeld'"
                        >
                            <span class="size-1.5 rounded-full" :class="beoordeling(speler).oud ? 'bg-warning' : 'bg-success'"></span>
                            {{ beoordeling(speler).tekst }}
                        </span>
                    </div>

                    <ChevronRight class="size-5 shrink-0 text-muted-foreground" />
                </Link>
            </div>

            <div v-else-if="players.length" class="mt-4 rounded-2xl border border-dashed border-border bg-card/50 p-10 text-center">
                <p class="font-medium">Geen spelers gevonden</p>
                <p class="mt-1 text-sm text-muted-foreground">Pas je zoekopdracht aan.</p>
            </div>

            <div v-else class="mt-4 rounded-2xl border border-dashed border-border bg-card/50 p-10 text-center">
                <p class="font-medium">Nog geen spelers om te beoordelen</p>
                <p v-if="canCreatePlayer" class="mt-1 text-sm text-muted-foreground">Voeg eerst een speler toe; daarna vul je hier zijn rapport in.</p>
                <p v-else class="mt-1 text-sm text-muted-foreground">Zodra de school je spelers heeft, staan ze hier.</p>
                <Link
                    v-if="canCreatePlayer"
                    href="/players/create"
                    class="mt-4 inline-flex min-h-11 items-center rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                >
                    Speler toevoegen
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
