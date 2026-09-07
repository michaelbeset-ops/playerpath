<script setup lang="ts">
import ReportPrompt, { type Herinnering } from '@/components/dashboard/ReportPrompt.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { CalendarPlus, MapPin, Users } from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * Mijn trainingen: waar moet ik vandaag zijn.
 *
 * Het rooster van de school staat op /trainings; dit is het scherm dat een
 * trainer op zijn telefoon openslaat. Per dag gegroepeerd, eerstvolgende
 * bovenaan, en bovenin de rapport-herinnering als die er is.
 */
interface Rij {
    id: number;
    group: string;
    date: string;
    day: string;
    dayNumber: string;
    month: string;
    time: string;
    location: string | null;
    cancelled: boolean;
    cancellationReason: string | null;
    trainers: string[];
    isToday: boolean;
    expected: number;
}

const props = defineProps<{
    trainings: Rij[];
    reportPrompts: Herinnering[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Agenda', href: '/trainings' },
    { title: 'Mijn trainingen', href: '/trainings/mijn' },
];

// Per dag gegroepeerd: een platte lijst met tien trainingen leest als een
// spreadsheet, en je wilt weten wat er "vandaag" en "zaterdag" staat.
const dagen = computed(() => {
    const map = new Map<string, Rij[]>();

    for (const training of props.trainings) {
        const rijen = map.get(training.date) ?? [];
        rijen.push(training);
        map.set(training.date, rijen);
    }

    return [...map.entries()].map(([datum, rijen]) => ({ datum, rijen, isToday: rijen[0].isToday }));
});
</script>

<template>
    <Head title="Mijn trainingen" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-3xl p-4">
            <FlashMessage />

            <!-- Bovenaan, want dit is tijdgebonden: over vijf uur is het weg. -->
            <ReportPrompt v-if="reportPrompts.length" class="mb-5" :prompts="reportPrompts" />

            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Mijn trainingen</h1>
                    <p class="mt-1 text-sm text-muted-foreground">Waar jij bij staat, eerstvolgende bovenaan.</p>
                </div>

                <Link href="/trainings" class="inline-flex min-h-11 items-center text-sm text-muted-foreground underline underline-offset-4">Hele rooster</Link>
            </div>

            <div v-if="dagen.length" class="mt-6 space-y-6">
                <div v-for="dag in dagen" :key="dag.datum">
                    <p
                        class="text-xs font-medium uppercase tracking-wide first-letter:uppercase"
                        :class="dag.isToday ? 'text-primary' : 'text-muted-foreground'"
                    >
                        {{ dag.isToday ? 'Vandaag' : dag.datum }}
                    </p>

                    <div class="mt-2 space-y-2">
                        <Link
                            v-for="training in dag.rijen"
                            :key="training.id"
                            :href="'/trainings/' + training.id"
                            class="flex items-center gap-4 rounded-xl border bg-card p-4 shadow-sm transition hover:border-primary"
                            :class="training.cancelled ? 'border-destructive/30 opacity-70' : 'border-border'"
                        >
                            <div
                                class="flex size-12 shrink-0 flex-col items-center justify-center rounded-lg"
                                :class="dag.isToday ? 'bg-primary/10 text-primary' : 'bg-secondary text-muted-foreground'"
                            >
                                <span class="tabular text-base font-bold leading-none">{{ training.dayNumber }}</span>
                                <span class="text-[10px] uppercase">{{ training.month }}</span>
                            </div>

                            <div class="min-w-0 flex-1">
                                <p class="flex flex-wrap items-center gap-2 truncate font-medium">
                                    {{ training.group }}
                                    <span
                                        v-if="training.cancelled"
                                        class="rounded bg-destructive/10 px-1.5 py-0.5 text-[10px] font-medium text-destructive"
                                    >
                                        afgezegd
                                    </span>
                                </p>
                                <p class="tabular truncate text-xs text-muted-foreground">
                                    {{ training.time }}
                                    <span v-if="training.location" class="inline-flex items-center gap-1">
                                        &middot; <MapPin class="size-3" />{{ training.location }}
                                    </span>
                                </p>
                                <p v-if="training.cancelled && training.cancellationReason" class="mt-1 text-xs text-destructive">
                                    {{ training.cancellationReason }}
                                </p>
                            </div>

                            <p class="tabular flex shrink-0 items-center gap-1 text-xs text-muted-foreground">
                                <Users class="size-3.5" />
                                {{ training.expected }}
                            </p>
                        </Link>
                    </div>
                </div>
            </div>

            <div v-else class="mt-6 rounded-2xl border border-dashed border-border bg-card/50 p-10 text-center">
                <p class="font-medium">Geen trainingen voor jou gepland</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    Sta je wel bij een training maar zie je hem niet? Dan is er iemand anders aan gekoppeld.
                </p>
                <Link
                    href="/trainings/create"
                    class="mt-4 inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground"
                >
                    <CalendarPlus class="size-4" />
                    Training inplannen
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
