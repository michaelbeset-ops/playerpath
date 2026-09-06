<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { TrendingDown, TrendingUp } from 'lucide-vue-next';
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
        coverage: { percentage: number | null; current: number; total: number };
        averageChange: number | null;
    };
}>();

// Onder de helft is het product aan het stilvallen; boven de tachtig levert de
// school wat ze belooft. Daartussen is het "kan beter".
const dekkingKleur = computed(() => {
    const pct = props.data.coverage.percentage;

    if (pct === null) {
        return 'bg-border';
    }

    return pct >= 80 ? 'bg-primary' : pct >= 50 ? 'bg-warning' : 'bg-destructive';
});
</script>

<template>
    <section class="flex h-full flex-col overflow-hidden rounded-xl border border-border bg-card p-5 shadow-sm">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <p class="font-medium">Ontwikkeling</p>
            <Link href="/reports" class="text-xs font-medium text-primary underline underline-offset-4">Alle rapporten</Link>
        </div>

        <!-- Het cijfer waar een school op stuurt, dus bovenaan en als balk. -->
        <div class="mt-4">
            <div class="flex items-baseline justify-between gap-2">
                <p class="text-sm text-muted-foreground">Spelers met een actueel rapport</p>
                <p class="tabular text-sm font-semibold">
                    <template v-if="data.coverage.percentage !== null">{{ data.coverage.percentage }}%</template>
                    <template v-else>—</template>
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

                <p v-else class="mt-2 text-xs text-muted-foreground">Niemand die achteruitgaat. Netjes.</p>
            </div>
        </div>

        <p class="mt-4 text-[11px] text-muted-foreground">
            Vergeleken over de laatste {{ data.days }} dagen, tussen het eerste en het laatste rapport van een speler.
        </p>
    </section>
</template>
