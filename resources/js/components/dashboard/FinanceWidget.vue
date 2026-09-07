<script setup lang="ts">
import { toneText, type Tone } from '@/lib/tone';
import { Link } from '@inertiajs/vue3';
import { ArrowDownRight, ArrowUpRight, Plug } from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * Het financiële vak: wat er binnenkwam, wat er openstaat en wat er doorloopt.
 *
 * "Verwacht op jaarbasis" stond hier ooit. Dat is een som van lopende
 * abonnementen maal twaalf, en daar kan een eigenaar niets mee: het is geen
 * omzet, geen prognose en het verandert alleen als er iemand opzegt.
 *
 * Omzet deze maand staat hier **alleen als hij niet al als kerncijfer bovenaan
 * staat**. Elk cijfer op precies één plek; welke van de twee dat is bepaalt de
 * server, want die weet welke widgets er staan.
 */
const props = defineProps<{
    data: {
        revenue: { thisMonth: string; lastMonth: string; change: number | null; tone: Tone } | null;
        outstanding: string;
        outstandingCount: number;
        outstandingTone: Tone;
        activeSubscriptions: number;
        gateway: { connected: boolean; name: string };
    };
}>();

const omzetKleur = computed(() => toneText[props.data.revenue?.tone ?? 'neutral']);
const openstaandKleur = computed(() => toneText[props.data.outstandingTone]);
</script>

<template>
    <section class="flex h-full flex-col overflow-hidden rounded-xl border border-border bg-card p-5 shadow-sm">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <p class="font-medium">Financieel</p>
            <Link href="/payments" class="text-xs font-medium text-primary underline underline-offset-4">Alles</Link>
        </div>

        <div class="mt-4 space-y-3">
            <!-- Alleen als de omzet niet al als kerncijfer bovenaan staat. -->
            <div v-if="data.revenue">
                <p class="tabular text-xl font-bold leading-none">{{ data.revenue.thisMonth }}</p>
                <p class="mt-1 flex flex-wrap items-center gap-x-2 text-xs text-muted-foreground">
                    <span>omzet deze maand</span>
                    <span v-if="data.revenue.change !== null" class="tabular inline-flex items-center gap-0.5 font-medium" :class="omzetKleur">
                        <component :is="data.revenue.change >= 0 ? ArrowUpRight : ArrowDownRight" class="size-3" />
                        {{ data.revenue.change > 0 ? '+' : '' }}{{ data.revenue.change }}%
                    </span>
                </p>
                <p class="tabular mt-0.5 text-xs text-muted-foreground">vorige maand {{ data.revenue.lastMonth }}</p>
            </div>

            <div :class="data.revenue ? 'border-t border-border pt-3' : ''">
                <p class="tabular text-xl font-bold leading-none" :class="data.outstandingCount ? openstaandKleur : 'text-muted-foreground/60'">
                    {{ data.outstandingCount ? data.outstanding : '—' }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    openstaand<template v-if="data.outstandingCount">
                        &middot; {{ data.outstandingCount }} {{ data.outstandingCount === 1 ? 'rekening' : 'rekeningen' }}</template
                    >
                </p>
            </div>

            <div class="border-t border-border pt-3">
                <p class="tabular text-xl font-bold leading-none">{{ data.activeSubscriptions }}</p>
                <p class="mt-1 text-xs text-muted-foreground">lopende abonnementen</p>
            </div>
        </div>

        <p v-if="!data.gateway.connected" class="mt-auto flex items-start gap-2 pt-4 text-[11px] leading-snug text-muted-foreground">
            <Plug class="mt-0.5 size-3 shrink-0" />
            {{ data.gateway.name }} is nog niet aangesloten; deze cijfers komen uit wat je zelf hebt vastgelegd.
        </p>
    </section>
</template>
