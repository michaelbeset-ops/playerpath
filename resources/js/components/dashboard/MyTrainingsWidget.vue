<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CalendarPlus, CheckSquare, ClipboardList, MapPin, Users } from 'lucide-vue-next';

/**
 * Mijn trainingen, op het dashboard van de trainer.
 *
 * Niet alleen wanneer, maar ook wat je er moet doen: per training zit afvinken
 * en beoordelen er direct naast. Een lijstje "je hebt om 18:00 training" stuurt
 * je alsnog op zoek naar de knop, en dat is precies wat er op een telefoon
 * langs het veld niet moet gebeuren.
 *
 * De twee knoppen verschijnen pas als de training is begonnen. Aanwezigheid
 * afvinken van iets dat morgen pas is, is een lege lijst.
 */
export interface MijnTraining {
    id: number;
    group: string;
    date: string;
    dayNumber: string;
    month: string;
    time: string;
    location: string | null;
    cancelled: boolean;
    isToday: boolean;
    hasStarted: boolean;
    expected: number;
    trainers: string[];
}

defineProps<{ data: MijnTraining[] }>();
</script>

<template>
    <section class="flex h-full min-w-0 flex-col rounded-xl border border-border bg-card p-4 shadow-sm sm:p-5">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="font-medium">Mijn trainingen</h2>
            <Link href="/trainings/mijn" class="inline-flex min-h-11 items-center text-xs text-muted-foreground underline underline-offset-4">
                Alles
            </Link>
        </div>

        <div v-if="data.length" class="mt-3 min-w-0 flex-1 space-y-2">
            <article
                v-for="training in data"
                :key="training.id"
                class="min-w-0 rounded-xl border p-3"
                :class="
                    training.cancelled
                        ? 'border-destructive/30 bg-card opacity-70'
                        : training.isToday
                          ? 'border-primary/40 bg-primary/5'
                          : 'border-border bg-card'
                "
            >
                <div class="flex min-w-0 items-center gap-3">
                    <span
                        class="flex size-11 shrink-0 flex-col items-center justify-center rounded-lg"
                        :class="training.isToday ? 'bg-primary/15 text-primary' : 'bg-secondary text-muted-foreground'"
                    >
                        <span class="tabular text-base font-bold leading-none">{{ training.dayNumber }}</span>
                        <span class="text-[10px] uppercase">{{ training.month }}</span>
                    </span>

                    <Link :href="'/trainings/' + training.id" class="min-w-0 flex-1">
                        <span class="flex flex-wrap items-center gap-2">
                            <span class="font-medium" :class="training.cancelled ? 'line-through' : ''">{{ training.group }}</span>
                            <span
                                v-if="training.isToday && !training.cancelled"
                                class="rounded-full bg-primary px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-primary-foreground"
                            >
                                vandaag
                            </span>
                            <span v-if="training.cancelled" class="rounded bg-destructive/10 px-1.5 py-0.5 text-[10px] font-medium text-destructive">
                                afgezegd
                            </span>
                        </span>
                        <span class="tabular mt-0.5 block text-xs text-muted-foreground">{{ training.time }}</span>
                        <span class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-muted-foreground">
                            <span v-if="training.location" class="inline-flex min-w-0 items-center gap-1">
                                <MapPin class="size-3 shrink-0" />
                                <span class="truncate">{{ training.location }}</span>
                            </span>
                            <span class="inline-flex shrink-0 items-center gap-1">
                                <Users class="size-3" />
                                <span class="tabular">{{ training.expected }}</span>
                            </span>
                        </span>
                    </Link>
                </div>

                <!-- De twee dingen die je na een training doet, direct bij de hand -->
                <div v-if="training.hasStarted && !training.cancelled" class="mt-3 grid grid-cols-2 gap-2">
                    <Link
                        :href="'/trainings/' + training.id"
                        class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-lg border border-border bg-background px-2 text-xs font-medium transition hover:border-primary"
                    >
                        <CheckSquare class="size-3.5 shrink-0" />
                        Aanwezigheid
                    </Link>
                    <Link
                        :href="'/trainings/' + training.id + '/rapporten'"
                        class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-lg bg-primary px-2 text-xs font-semibold text-primary-foreground transition hover:opacity-90"
                    >
                        <ClipboardList class="size-3.5 shrink-0" />
                        Rapporten
                    </Link>
                </div>
            </article>
        </div>

        <div v-else class="mt-3 flex flex-1 flex-col items-center justify-center rounded-xl border border-dashed border-border p-6 text-center">
            <p class="text-sm font-medium">Geen trainingen gepland</p>
            <p class="mt-1 text-xs text-muted-foreground">Sta je wel bij een training maar zie je hem niet? Dan is er iemand anders aan gekoppeld.</p>
            <Link
                href="/trainings/create"
                class="mt-3 inline-flex min-h-11 items-center gap-2 rounded-lg border border-border bg-background px-3 text-sm font-medium transition hover:border-primary"
            >
                <CalendarPlus class="size-4" />
                Training inplannen
            </Link>
        </div>
    </section>
</template>
