<script setup lang="ts">
import { toneFill, type Tone } from '@/lib/tone';
import { Link } from '@inertiajs/vue3';
import { Check, Clock, TrendingDown, TrendingUp } from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * Wie groeit en wie achterblijft.
 *
 * Het onderscheidende deel van het product, dus het grootste vak. De
 * dekkingsbalk staat bovenaan: dat is het cijfer waar een school op stuurt.
 */
interface Verandering {
    id: number;
    name: string;
    from: number;
    to: number;
    change: number;
    reports: number;
}

const props = defineProps<{
    data: {
        days: number;
        risers: Verandering[];
        fallers: Verandering[];
        coverage: { percentage: number | null; tone: Tone; current: number; total: number };
        stalest: { id: number; name: string; days: number | null }[];
        averageChange: number | null;
    };
}>();

// De drempel staat op de server (Support\Dashboard\Signal); hier alleen de
// vertaling naar een kleur.
const dekkingKleur = computed(() => toneFill[props.data.coverage.tone ?? 'neutral']);
</script>

<template>
    <section class="flex h-full flex-col overflow-hidden rounded-xl border border-border bg-card p-5 shadow-sm">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <p class="font-medium">Ontwikkeling</p>
            <Link href="/reports" class="inline-flex min-h-11 items-center text-xs font-medium text-primary underline underline-offset-4">Alle rapporten</Link>
        </div>

        <!-- Het cijfer waar een school op stuurt, dus bovenaan en als balk. -->
        <div class="mt-4">
            <div class="flex items-baseline justify-between gap-2">
                <p class="text-sm text-muted-foreground">Spelers met een actueel rapport</p>
                <p class="tabular text-sm font-semibold">
                    <template v-if="data.coverage.percentage !== null">{{ data.coverage.percentage }}%</template>
                    <template v-else>-</template>
                </p>
            </div>

            <div class="mt-2 h-2 overflow-hidden rounded-full bg-secondary">
                <div class="h-full rounded-full transition-all" :class="dekkingKleur" :style="{ width: (data.coverage.percentage ?? 0) + '%' }"></div>
            </div>

            <p class="tabular mt-1 text-xs text-muted-foreground">
                {{ data.coverage.current }} van {{ data.coverage.total }}, in de laatste 30 dagen
            </p>
        </div>

        <div class="mt-5 grid gap-4 sm:grid-cols-2">
            <div class="min-w-0">
                <p class="flex items-center gap-1.5 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                    <TrendingUp class="size-3.5 text-primary" />
                    Grootste stijgers
                </p>

                <div v-if="data.risers.length" class="mt-2 space-y-1.5">
                    <Link
                        v-for="speler in data.risers"
                        :key="speler.id"
                        :href="'/players/' + speler.id + '/progress'"
                        class="flex items-center justify-between gap-2 rounded-lg border border-border px-3 py-2 text-sm transition hover:border-primary"
                    >
                        <span class="min-w-0 truncate">{{ speler.name }}</span>
                        <span class="tabular shrink-0 font-semibold text-primary">+{{ speler.change }}</span>
                    </Link>
                </div>

                <p v-else class="mt-2 text-xs text-muted-foreground">Nog niemand met twee rapporten in deze periode.</p>
            </div>

            <div class="min-w-0">
                <p class="flex items-center gap-1.5 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                    <TrendingDown class="size-3.5 text-warning" />
                    Blijft achter
                </p>

                <div v-if="data.fallers.length" class="mt-2 space-y-1.5">
                    <Link
                        v-for="speler in data.fallers"
                        :key="speler.id"
                        :href="'/players/' + speler.id + '/progress'"
                        class="flex items-center justify-between gap-2 rounded-lg border border-warning/30 bg-warning/5 px-3 py-2 text-sm transition hover:border-warning"
                    >
                        <span class="min-w-0 truncate">{{ speler.name }}</span>
                        <span class="tabular shrink-0 font-semibold text-warning">{{ speler.change }}</span>
                    </Link>
                </div>

                <!-- Niemand die achteruitgaat is goed nieuws, maar dan blijft
                     deze kolom leeg. Wie het langst niets gehad heeft is dan
                     het nuttigste dat er kan staan: daar kun je vandaag iets
                     aan doen. -->
                <template v-else>
                    <p class="mt-2 text-xs text-muted-foreground">Niemand die achteruitgaat.</p>

                    <p
                        v-if="data.stalest.length"
                        class="mt-3 flex items-center gap-1.5 text-xs font-medium uppercase tracking-wide text-muted-foreground"
                    >
                        <Clock class="size-3.5" />
                        Langst geen rapport
                    </p>

                    <p v-else class="mt-3 flex items-center gap-1.5 text-xs text-success">
                        <Check class="size-3.5 shrink-0" />
                        Iedereen heeft een recent rapport.
                    </p>

                    <div v-if="data.stalest.length" class="mt-2 space-y-1.5">
                        <Link
                            v-for="speler in data.stalest"
                            :key="speler.id"
                            :href="'/players/' + speler.id + '/reports/create'"
                            class="flex items-center justify-between gap-2 rounded-lg border border-border px-3 py-2 text-sm transition hover:border-primary"
                        >
                            <span class="min-w-0 truncate">{{ speler.name }}</span>
                            <span class="tabular shrink-0 text-xs text-muted-foreground">
                                {{ speler.days === null ? 'nog nooit' : speler.days + ' dgn' }}
                            </span>
                        </Link>
                    </div>
                </template>
            </div>
        </div>

        <p class="mt-4 text-[11px] text-muted-foreground">
            Vergeleken over de laatste {{ data.days }} dagen, tussen het eerste en het laatste rapport van een speler.
        </p>
    </section>
</template>
