<script setup lang="ts">
import GradeChip from '@/components/GradeChip.vue';
import { useGrading } from '@/lib/grade';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { Award, ChevronUp, Sparkles, TrendingDown, TrendingUp } from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * Wat die ronde opleverde.
 *
 * Eén samenvatting aan het eind, geen viering per speler: acht keer hetzelfde
 * blok kijkt niemand na de tweede nog naar. Wel worden level-ups apart genoemd,
 * want dat is het bericht dat de trainer straks aan het kind vertelt.
 *
 * Groei wordt gevierd, achteruitgang niet weggepoetst - een scherm dat altijd
 * juicht gelooft een trainer na twee keer niet meer.
 */
interface Resultaat {
    player: { id: number; first_name: string };
    overall: { from: number | null; to: number | null; delta: number | null };
    categories: { category: string; label: string; from: number; to: number; delta: number }[];
    xp: { from: number; to: number; gained: number };
    level: { from: { key: string; label: string }; to: { key: string; label: string }; up: boolean };
    badges: { key: string; label: string; description: string }[];
}

const props = defineProps<{
    training: { id: number; group: string; date: string };
    results: Resultaat[];
    total: number;
    doneCount: number;
    openCount: number;
    open: { id: number; name: string }[];
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Mijn trainingen', href: '/trainings/mijn' },
    { title: props.training.group, href: '/trainings/' + props.training.id },
]);

const gestegen = computed(() => props.results.filter((r) => (r.overall.delta ?? 0) > 0));
const gedaald = computed(() => props.results.filter((r) => (r.overall.delta ?? 0) < 0));
const levelUps = computed(() => props.results.filter((r) => r.level.up));
const mijlpalen = computed(() => props.results.filter((r) => r.badges.length));

const { kleuren } = useGrading();
</script>

<template>
    <Head title="Rapporten afgerond" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-3xl p-4">
            <div class="rounded-2xl border border-primary/40 bg-primary/5 p-5 text-center">
                <Sparkles class="mx-auto size-7 text-primary" />
                <h1 class="mt-2 text-xl font-semibold tracking-tight">
                    <template v-if="results.length === 1">Eén rapport opgeslagen</template>
                    <template v-else-if="results.length">{{ results.length }} rapporten opgeslagen</template>
                    <template v-else>Deze ronde is klaar</template>
                </h1>
                <p class="mt-1 text-sm text-muted-foreground first-letter:uppercase">{{ training.group }} &middot; {{ training.date }}</p>
                <p class="tabular mt-2 text-sm">
                    <span class="font-bold text-primary">{{ doneCount }}</span>
                    <span class="text-muted-foreground">/{{ total }} spelers hebben een rapport van deze training</span>
                </p>
            </div>

            <!-- Een level erbij is het bericht dat de trainer straks vertelt -->
            <section v-if="levelUps.length" class="mt-5 rounded-2xl border border-gold/40 bg-card p-4 shadow-sm">
                <p class="flex items-center gap-2 font-medium">
                    <ChevronUp class="size-4 shrink-0 text-gold" />
                    Nieuw level
                </p>
                <ul class="mt-2 space-y-1 text-sm">
                    <li v-for="rij in levelUps" :key="rij.player.id">
                        <Link :href="'/players/' + rij.player.id + '/card'" class="inline-flex min-h-11 items-center underline underline-offset-4">
                            {{ rij.player.first_name }} is nu {{ rij.level.to.label }}
                        </Link>
                    </li>
                </ul>
            </section>

            <section v-if="mijlpalen.length" class="mt-4 rounded-2xl border border-border bg-card p-4 shadow-sm">
                <p class="flex items-center gap-2 font-medium">
                    <Award class="size-4 shrink-0 text-gold" />
                    Mijlpalen behaald
                </p>
                <ul class="mt-2 space-y-1 text-sm">
                    <li v-for="rij in mijlpalen" :key="rij.player.id" class="text-muted-foreground">
                        <span class="font-medium text-foreground">{{ rij.player.first_name }}</span>
                        &middot; {{ rij.badges.map((b) => b.label).join(', ') }}
                    </li>
                </ul>
            </section>

            <!-- Wie ging vooruit, wie achteruit -->
            <section v-if="results.length" class="mt-4 overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
                <p class="border-b border-border px-4 py-3 font-medium">Wat er veranderde</p>

                <ul class="divide-y divide-border">
                    <!-- De hele rij is de link, niet alleen de naam: een tikvlak
                         van 24 pixels mis je met een duim. -->
                    <li v-for="rij in results" :key="rij.player.id">
                        <Link :href="'/players/' + rij.player.id + '/card'" class="flex min-h-14 items-center gap-3 px-4 py-3">
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-medium">{{ rij.player.first_name }}</span>
                                <span v-if="rij.categories.length" class="block truncate text-xs text-muted-foreground">
                                    {{
                                        rij.categories
                                            .slice(0, 3)
                                            .map((c) => c.label + ' ' + (kleuren ? (c.delta > 0 ? '▲' : '▼') : (c.delta > 0 ? '+' : '') + c.delta))
                                            .join(' · ')
                                    }}
                                </span>
                                <span v-else class="block text-xs text-muted-foreground">Geen verschil met het vorige rapport</span>
                            </span>

                            <span class="tabular shrink-0 text-right">
                                <GradeChip :rating="rij.overall.to" size="sm">
                                    <span class="block text-base font-bold leading-none">{{ rij.overall.to ?? '-' }}</span>
                                </GradeChip>
                                <span
                                    v-if="rij.overall.delta && !kleuren"
                                    class="flex items-center justify-end gap-0.5 text-xs font-medium"
                                    :class="rij.overall.delta > 0 ? 'text-primary' : 'text-warning'"
                                >
                                    <TrendingUp v-if="rij.overall.delta > 0" class="size-3" />
                                    <TrendingDown v-else class="size-3" />
                                    {{ rij.overall.delta > 0 ? '+' : '' }}{{ rij.overall.delta }}
                                </span>
                                <span v-else-if="!kleuren" class="block text-xs text-muted-foreground">gelijk</span>
                            </span>
                        </Link>
                    </li>
                </ul>

                <p class="border-t border-border px-4 py-3 text-xs text-muted-foreground">
                    <template v-if="gestegen.length">
                        {{ gestegen.length }} {{ gestegen.length === 1 ? 'speler ging' : 'spelers gingen' }} vooruit<template v-if="gedaald.length"
                            >, {{ gedaald.length }} achteruit</template
                        >. Achteruitgang hoort bij leren.
                    </template>
                    <template v-else>Geen stijgers deze ronde. De kaart rekent met de laatste drie rapporten, dus dat trekt vanzelf bij.</template>
                </p>
            </section>

            <!-- Wie er nog open staat: overslaan mocht, vergeten niet -->
            <section v-if="open.length" class="mt-4 rounded-2xl border border-border bg-card p-4 shadow-sm">
                <p class="font-medium">Nog open: {{ openCount }}</p>
                <p class="mt-1 text-xs text-muted-foreground">Deze spelers hebben nog geen rapport van deze training.</p>

                <div class="mt-3 flex flex-wrap gap-2">
                    <Link
                        v-for="rij in open"
                        :key="rij.id"
                        :href="'/trainings/' + training.id + '/rapporten?speler=' + rij.id"
                        class="inline-flex min-h-11 items-center rounded-xl border border-border bg-background px-3 text-sm transition hover:border-primary"
                    >
                        {{ rij.name }}
                    </Link>
                </div>
            </section>

            <div class="mt-6 grid gap-2 sm:grid-cols-2">
                <Link
                    href="/dashboard"
                    class="inline-flex min-h-12 items-center justify-center rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                >
                    Naar het dashboard
                </Link>
                <Link
                    :href="'/trainings/' + training.id"
                    class="inline-flex min-h-12 items-center justify-center rounded-xl border border-border bg-card px-4 text-sm font-medium shadow-sm transition hover:border-primary"
                >
                    Terug naar de training
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
