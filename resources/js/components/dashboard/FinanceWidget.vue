<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Plug } from 'lucide-vue-next';

/**
 * Het financiële vak, compact.
 *
 * "Omzet deze maand" staat er bewust niet in: dat is een kerncijfer bovenaan,
 * en elk cijfer hoort op precies één plek te staan.
 */
defineProps<{
    data: {
        outstanding: string;
        outstandingCount: number;
        activeSubscriptions: number;
        yearlyValue: string;
        gateway: { connected: boolean; name: string };
    };
}>();
</script>

<template>
    <section class="flex h-full flex-col overflow-hidden rounded-xl border border-border bg-card p-5 shadow-sm">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <p class="font-medium">Financieel</p>
            <Link href="/payments" class="text-xs font-medium text-primary underline underline-offset-4">Alles</Link>
        </div>

        <div class="mt-4 space-y-3">
            <div>
                <p class="tabular text-xl font-bold leading-none" :class="data.outstandingCount ? 'text-warning' : 'text-muted-foreground/60'">
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

            <div class="border-t border-border pt-3">
                <p class="tabular text-xl font-bold leading-none">{{ data.yearlyValue }}</p>
                <!-- Een vooruitblik, geen omzet: hier is nog niets van betaald. -->
                <p class="mt-1 text-xs text-muted-foreground">verwacht op jaarbasis</p>
            </div>
        </div>

        <p v-if="!data.gateway.connected" class="mt-auto flex items-start gap-2 pt-4 text-[11px] leading-snug text-muted-foreground">
            <Plug class="mt-0.5 size-3 shrink-0" />
            {{ data.gateway.name }} is nog niet aangesloten; deze cijfers komen uit wat je zelf hebt vastgelegd.
        </p>
    </section>
</template>
