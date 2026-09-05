<script setup lang="ts">
import GoalList, { type Doel } from '@/components/GoalList.vue';
import LineChart from '@/components/LineChart.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ClipboardList, IdCard, Minus, TrendingDown, TrendingUp, Trophy } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface CategorieVerloop {
    category: string;
    label: string;
    series: (number | null)[];
    first: number | null;
    last: number | null;
    delta: number | null;
}

const props = defineProps<{
    player: { id: number; name: string; first_name: string; position: string; overall_rating: number | null };
    progress: {
        points: { date: string; label: string; overall: number; scores: Record<string, number> }[];
        categories: CategorieVerloop[];
        overall: { series: number[]; first: number | null; last: number | null; delta: number | null };
        hasEnoughData: boolean;
    };
    timeline: { type: string; date: string; title: string; body: string | null; value: number | null; delta: number | null }[];
    quarter: { reports: number; trainings: number; growth: number | null; best: string | null };
    goals: Doel[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: props.player.name, href: '/players/' + props.player.id + '/card' },
    { title: 'Voortgang', href: '/players/' + props.player.id + '/progress' },
];

const labels = computed(() => props.progress.points.map((p) => p.label));

// Een tabelweergave hoort erbij: de cijfers mogen nooit alleen in een plaatje
// zitten. Zie de datavisualisatie-richtlijnen.
const toonTabel = ref(false);

const deltaTekst = (delta: number | null) => (delta === null ? '' : delta > 0 ? `+${delta}` : `${delta}`);
</script>

<template>
    <Head :title="'Voortgang - ' + player.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-4xl p-4">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Voortgang</h1>
                    <p class="mt-1 text-sm text-muted-foreground">{{ player.name }} &middot; {{ player.position }}</p>
                </div>

                <Link
                    :href="'/players/' + player.id + '/card'"
                    class="inline-flex items-center gap-2 rounded-xl border border-border bg-card px-4 py-2 text-sm font-medium shadow-sm transition hover:border-primary"
                >
                    <IdCard class="size-4" />
                    Spelerskaart
                </Link>
            </div>

            <template v-if="progress.hasEnoughData">
                <!-- Kwartaal-terugblik: de kop van het verhaal, in woorden -->
                <div class="mt-6 rounded-xl border border-border bg-card p-5 shadow-sm">
                    <p class="font-medium">De afgelopen drie maanden</p>
                    <div class="mt-4 grid gap-4 sm:grid-cols-4">
                        <div>
                            <p class="tabular text-2xl font-bold leading-none text-primary">{{ quarter.reports }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">rapporten</p>
                        </div>
                        <div>
                            <p class="tabular text-2xl font-bold leading-none text-primary">{{ quarter.trainings }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">trainingen aanwezig</p>
                        </div>
                        <div>
                            <p
                                class="tabular flex items-center gap-1 text-2xl font-bold leading-none"
                                :class="quarter.growth === null || quarter.growth === 0 ? 'text-muted-foreground' : quarter.growth > 0 ? 'text-primary' : 'text-warning'"
                            >
                                <TrendingUp v-if="quarter.growth !== null && quarter.growth > 0" class="size-5" />
                                <TrendingDown v-else-if="quarter.growth !== null && quarter.growth < 0" class="size-5" />
                                <Minus v-else class="size-5" />
                                {{ quarter.growth === null ? '—' : deltaTekst(quarter.growth) }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">punten groei</p>
                        </div>
                        <div>
                            <p class="flex items-center gap-1.5 text-sm font-semibold leading-none">
                                <Trophy class="size-4 text-gold" />
                                {{ quarter.best ?? '—' }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">sterkste categorie</p>
                        </div>
                    </div>
                </div>

                <!-- Overall: één lijn, want dit is het hoofdverhaal -->
                <div class="mt-4 rounded-xl border border-border bg-card p-5 shadow-sm">
                    <div class="flex items-baseline justify-between gap-3">
                        <div>
                            <p class="font-medium">Overall rating</p>
                            <p class="text-xs text-muted-foreground">Het gemiddelde per rapport, over {{ progress.points.length }} rapporten</p>
                        </div>
                        <p
                            v-if="progress.overall.delta !== null"
                            class="tabular text-sm font-semibold"
                            :class="progress.overall.delta > 0 ? 'text-primary' : progress.overall.delta < 0 ? 'text-warning' : 'text-muted-foreground'"
                        >
                            {{ deltaTekst(progress.overall.delta) }} sinds het eerste rapport
                        </p>
                    </div>

                    <LineChart class="mt-4" :series="progress.overall.series" :labels="labels" :height="150" :width="640" />
                </div>

                <!-- Per categorie: kleine meervouden in plaats van zes lijnen door elkaar -->
                <div class="mt-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="font-medium">Per categorie</p>
                            <p class="text-xs text-muted-foreground">Elke grafiek staat op dezelfde schaal van 0 tot 100.</p>
                        </div>

                        <button
                            type="button"
                            class="text-sm font-medium text-primary underline underline-offset-4"
                            @click="toonTabel = !toonTabel"
                        >
                            {{ toonTabel ? 'Toon grafieken' : 'Toon als tabel' }}
                        </button>
                    </div>

                    <div v-if="!toonTabel" class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div v-for="categorie in progress.categories" :key="categorie.category" class="rounded-xl border border-border bg-card p-4 shadow-sm">
                            <div class="flex items-baseline justify-between gap-2">
                                <p class="truncate text-sm font-medium">{{ categorie.label }}</p>
                                <p
                                    v-if="categorie.delta !== null"
                                    class="tabular shrink-0 text-xs font-semibold"
                                    :class="categorie.delta > 0 ? 'text-primary' : categorie.delta < 0 ? 'text-warning' : 'text-muted-foreground'"
                                >
                                    {{ deltaTekst(categorie.delta) }}
                                </p>
                            </div>

                            <LineChart class="mt-2" :series="categorie.series" :labels="labels" :height="86" :width="230" />
                        </div>
                    </div>

                    <!-- Tabelweergave: dezelfde cijfers, zonder kleur nodig -->
                    <div v-else class="mt-3 overflow-x-auto rounded-xl border border-border bg-card shadow-sm">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-border text-left">
                                    <th class="p-3 font-medium">Categorie</th>
                                    <th v-for="(label, i) in labels" :key="i" class="tabular whitespace-nowrap p-3 text-right font-medium">
                                        {{ label }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="categorie in progress.categories" :key="categorie.category" class="border-b border-border last:border-0">
                                    <td class="whitespace-nowrap p-3">{{ categorie.label }}</td>
                                    <td v-for="(waarde, i) in categorie.series" :key="i" class="tabular p-3 text-right">
                                        {{ waarde ?? '—' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>

            <div v-else class="mt-6 rounded-2xl border border-dashed border-border bg-card/50 p-10 text-center">
                <p class="font-medium">Nog te weinig rapporten</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    Vanaf het tweede rapport kunnen we groei laten zien. Nu zijn het er {{ progress.points.length }}.
                </p>
            </div>

            <div v-if="goals.length" class="mt-4 rounded-xl border border-border bg-card p-5 shadow-sm">
                <p class="font-medium">Doelen</p>
                <GoalList class="mt-3" :goals="goals" />
            </div>

            <!-- Tijdlijn -->
            <div class="mt-4 rounded-xl border border-border bg-card p-5 shadow-sm">
                <p class="font-medium">Tijdlijn</p>

                <div v-if="timeline.length" class="mt-4 space-y-3">
                    <div v-for="(item, index) in timeline" :key="index" class="flex gap-3">
                        <span
                            class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg"
                            :class="item.type === 'mijlpaal' ? 'bg-gold/15 text-gold' : 'bg-primary/10 text-primary'"
                        >
                            <Trophy v-if="item.type === 'mijlpaal'" class="size-4" />
                            <ClipboardList v-else class="size-4" />
                        </span>

                        <div class="min-w-0 flex-1 border-b border-border pb-3 last:border-0">
                            <div class="flex flex-wrap items-baseline justify-between gap-2">
                                <p class="text-sm font-medium">{{ item.title }}</p>
                                <p class="tabular text-xs text-muted-foreground">{{ item.date }}</p>
                            </div>

                            <p v-if="item.value !== null" class="tabular mt-1 text-sm">
                                Gemiddelde <span class="font-semibold">{{ item.value }}</span>
                                <span
                                    v-if="item.delta !== null && item.delta !== 0"
                                    :class="item.delta > 0 ? 'text-primary' : 'text-warning'"
                                >
                                    ({{ deltaTekst(item.delta) }})
                                </span>
                            </p>

                            <p v-if="item.body" class="mt-1 text-sm text-muted-foreground">{{ item.body }}</p>
                        </div>
                    </div>
                </div>

                <p v-else class="mt-3 text-sm text-muted-foreground">Er is nog niets gebeurd om te laten zien.</p>
            </div>
        </div>
    </AppLayout>
</template>
