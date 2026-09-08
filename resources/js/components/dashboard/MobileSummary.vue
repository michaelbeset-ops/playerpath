<script setup lang="ts">
import { toneFill, type Tone } from '@/lib/tone';
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * Wat er op een telefoon overblijft van de blokken die daar niet passen.
 *
 * Het ontwikkelingsvak en het financiële vak zijn op een klein scherm te veel
 * en te lang: een eigenaar pakt zijn telefoon om te zien hoe het ervoor staat,
 * niet om te analyseren. Ze verdwijnen daar dus — maar niet zonder spoor, want
 * dan weet hij niet meer waar hij moet kijken.
 *
 * Wat ervoor in de plaats komt is per blok één regel met het cijfer waar het om
 * draait, en een link naar het volledige overzicht. Twee regels dus, geen twee
 * kaarten: dit is een wegwijzer, geen samenvatting.
 *
 * **Een regel zonder inhoud verdwijnt.** Zonder rapporten valt er geen dekking
 * te melden, en staat de betaallaag uit dan is er geen Financiën om naartoe te
 * gaan. Een lege regel met alleen een pijltje leest als een fout.
 */
const props = defineProps<{
    development?: { coverage: { percentage: number | null; tone: Tone; current: number; total: number } } | null;
    finance?: {
        revenue: { thisMonth: string; lastMonth: string; change: number | null; tone: Tone } | null;
        outstanding: string;
        outstandingCount: number;
        outstandingTone: Tone;
    } | null;
}>();

const dekking = computed(() => props.development?.coverage ?? null);

const dekkingKleur = computed(() => toneFill[dekking.value?.tone ?? 'neutral']);

// Alleen tonen als er iets te melden valt: nul openstaande rekeningen is goed
// nieuws en geen regel waard, maar de omzet staat er wél als de eigenaar de
// omzettegel heeft weggehaald — anders ziet hij hem nergens.
const toonFinancieel = computed(
    () => props.finance !== null && props.finance !== undefined && (props.finance.outstandingCount > 0 || props.finance.revenue !== null),
);
</script>

<template>
    <div v-if="(dekking && dekking.percentage !== null) || toonFinancieel" class="space-y-2">
        <!-- Ontwikkeling: het enige cijfer waar een school op stuurt. -->
        <Link
            v-if="dekking && dekking.percentage !== null"
            href="/reports"
            class="flex min-h-14 items-center gap-3 rounded-xl border border-border bg-card p-3 shadow-sm transition hover:border-primary"
        >
            <span class="min-w-0 flex-1">
                <span class="flex items-baseline gap-2">
                    <span class="tabular text-lg font-bold leading-none">{{ dekking.percentage }}%</span>
                    <span class="min-w-0 truncate text-sm text-muted-foreground">heeft een actueel rapport</span>
                </span>

                <!-- De balk is de tweede drager van hetzelfde cijfer; kleur is
                     nooit het enige dat het verschil maakt. -->
                <span class="mt-1.5 block h-1.5 overflow-hidden rounded-full bg-secondary">
                    <span class="block h-full rounded-full transition-all" :class="dekkingKleur" :style="{ width: dekking.percentage + '%' }"></span>
                </span>

                <span class="tabular mt-1 block text-[11px] text-muted-foreground">{{ dekking.current }} van {{ dekking.total }} spelers</span>
            </span>

            <ChevronRight class="size-4 shrink-0 text-muted-foreground" />
        </Link>

        <!-- Financieel: openstaand is het enige dat om actie vraagt. -->
        <Link
            v-if="toonFinancieel && finance"
            href="/payments"
            class="flex min-h-14 items-center gap-3 rounded-xl border border-border bg-card p-3 shadow-sm transition hover:border-primary"
        >
            <span class="min-w-0 flex-1">
                <span v-if="finance.outstandingCount" class="flex items-baseline gap-2">
                    <span class="tabular text-lg font-bold leading-none text-warning">{{ finance.outstanding }}</span>
                    <span class="min-w-0 truncate text-sm text-muted-foreground">
                        openstaand<span class="tabular"> &middot; {{ finance.outstandingCount }}</span>
                    </span>
                </span>

                <!-- Alleen als de omzettegel er niet staat; elk cijfer op één plek. -->
                <span v-if="finance.revenue" class="flex items-baseline gap-2" :class="finance.outstandingCount ? 'mt-1' : ''">
                    <span class="tabular text-lg font-bold leading-none">{{ finance.revenue.thisMonth }}</span>
                    <span class="min-w-0 truncate text-sm text-muted-foreground">omzet deze maand</span>
                </span>

                <span class="mt-1 block text-[11px] text-muted-foreground">Naar Financiën</span>
            </span>

            <ChevronRight class="size-4 shrink-0 text-muted-foreground" />
        </Link>
    </div>
</template>
