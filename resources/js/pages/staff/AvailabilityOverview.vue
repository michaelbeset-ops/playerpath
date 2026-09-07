<script setup lang="ts">
import Avatar from '@/components/Avatar.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { AlertTriangle, CalendarOff, Check, UserX } from 'lucide-vue-next';

/**
 * De beschikbaarheid van het hele team, voor de eigenaar.
 *
 * Twee dingen op één scherm, in deze volgorde: waar de planning nú wringt, en
 * daaronder wie wanneer kan. Andersom moet een eigenaar zelf gaan uitrekenen
 * welke training een probleem is, en dat is precies het werk dat dit scherm
 * hoort weg te nemen.
 *
 * Een trainer die niets heeft ingevuld staat er als "nog niet ingevuld" bij, en
 * niet als een lege week. Onbekend is iets anders dan nooit beschikbaar.
 */
interface Trainer {
    id: number;
    name: string;
    photo: string | null;
    has_set: boolean;
    grid: Record<string, Record<string, boolean>>;
    exceptions: { id: number; label: string; available: boolean; note: string | null }[];
    slots: number;
}

defineProps<{
    trainers: Trainer[];
    dayparts: { value: string; label: string; hint: string }[];
    conflicts: {
        unavailable: { id: number; group: string; date: string; time: string; trainer: string }[];
        unstaffed: { id: number; group: string; date: string; time: string }[];
    };
    lookaheadDays: number;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Personeel', href: '/staff' },
    { title: 'Beschikbaarheid', href: '/personeel/beschikbaarheid' },
];

const dagen = [
    { nummer: 1, kort: 'ma' },
    { nummer: 2, kort: 'di' },
    { nummer: 3, kort: 'wo' },
    { nummer: 4, kort: 'do' },
    { nummer: 5, kort: 'vr' },
    { nummer: 6, kort: 'za' },
    { nummer: 7, kort: 'zo' },
];
</script>

<template>
    <Head title="Beschikbaarheid team" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-4xl p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <h1 class="text-2xl font-semibold tracking-tight">Beschikbaarheid</h1>
                    <p class="mt-1 text-sm text-muted-foreground">Wie kan wanneer, en waar de planning nu wringt.</p>
                </div>

                <Link href="/beschikbaarheid" class="inline-flex min-h-11 items-center text-sm text-muted-foreground underline underline-offset-4">
                    Die van jezelf
                </Link>
            </div>

            <!-- Eerst wat er misgaat: dat is waarom je hier komt -->
            <section v-if="conflicts.unavailable.length || conflicts.unstaffed.length" class="mt-5 space-y-3">
                <div v-if="conflicts.unavailable.length" class="rounded-2xl border border-warning/40 bg-warning/5 p-4">
                    <p class="flex items-center gap-2 font-medium">
                        <AlertTriangle class="size-4 shrink-0 text-warning" />
                        Trainer kan niet
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Deze trainingen staan de komende {{ lookaheadDays }} dagen gepland met een trainer die dan niet beschikbaar is.
                    </p>

                    <ul class="mt-3 space-y-2">
                        <li v-for="rij in conflicts.unavailable" :key="rij.id + '-' + rij.trainer">
                            <Link
                                :href="'/trainings/' + rij.id"
                                class="flex min-h-11 flex-col gap-0.5 rounded-xl border border-border bg-card p-3 transition hover:border-primary sm:flex-row sm:items-center sm:justify-between sm:gap-3"
                            >
                                <span class="min-w-0">
                                    <span class="block font-medium">{{ rij.group }}</span>
                                    <span class="block text-xs text-muted-foreground first-letter:uppercase"
                                        >{{ rij.date }} &middot; {{ rij.time }}</span
                                    >
                                </span>
                                <span class="shrink-0 text-xs font-medium text-warning">{{ rij.trainer }} kan niet</span>
                            </Link>
                        </li>
                    </ul>
                </div>

                <div v-if="conflicts.unstaffed.length" class="rounded-2xl border border-border bg-card p-4 shadow-sm">
                    <p class="flex items-center gap-2 font-medium">
                        <UserX class="size-4 shrink-0 text-muted-foreground" />
                        Nog geen trainer
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Bij deze trainingen staat niemand gekoppeld. Iedereen ziet ze, maar niemand is ervan.
                    </p>

                    <ul class="mt-3 space-y-2">
                        <li v-for="rij in conflicts.unstaffed" :key="rij.id">
                            <Link
                                :href="'/trainings/' + rij.id + '/edit'"
                                class="flex min-h-11 flex-col gap-0.5 rounded-xl border border-border bg-background p-3 transition hover:border-primary sm:flex-row sm:items-center sm:justify-between sm:gap-3"
                            >
                                <span class="min-w-0">
                                    <span class="block font-medium">{{ rij.group }}</span>
                                    <span class="block text-xs text-muted-foreground first-letter:uppercase"
                                        >{{ rij.date }} &middot; {{ rij.time }}</span
                                    >
                                </span>
                                <span class="shrink-0 text-xs font-medium text-primary">Trainer koppelen</span>
                            </Link>
                        </li>
                    </ul>
                </div>
            </section>

            <p v-else class="mt-5 rounded-2xl border border-border bg-card p-4 text-sm text-muted-foreground shadow-sm">
                De komende {{ lookaheadDays }} dagen staat overal een trainer bij die op dat moment kan.
            </p>

            <!-- En dan wie wanneer kan -->
            <h2 class="mt-8 font-medium">Het team</h2>

            <div class="mt-3 space-y-3">
                <article v-for="trainer in trainers" :key="trainer.id" class="rounded-2xl border border-border bg-card p-4 shadow-sm">
                    <div class="flex items-center gap-3">
                        <Avatar :name="trainer.name" :photo="trainer.photo" size="size-10" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium">{{ trainer.name }}</p>
                            <p class="text-xs text-muted-foreground">
                                <template v-if="!trainer.has_set">Nog niet ingevuld</template>
                                <template v-else-if="trainer.slots === 0">Geen enkel dagdeel aangevinkt</template>
                                <template v-else>{{ trainer.slots }} dagdelen beschikbaar</template>
                            </p>
                        </div>
                    </div>

                    <!-- Het raster: rijen zijn dagdelen, kolommen dagen. Zeven
                         smalle kolommen passen op 375 pixels; zeven rijen met
                         drie knoppen zou dit scherm drie keer zo lang maken. -->
                    <div v-if="trainer.has_set" class="mt-4 overflow-x-auto">
                        <table class="w-full min-w-[18rem] border-separate border-spacing-1 text-center text-xs">
                            <thead>
                                <tr>
                                    <th class="w-14 text-left font-normal text-muted-foreground"></th>
                                    <th v-for="dag in dagen" :key="dag.nummer" class="font-medium text-muted-foreground">{{ dag.kort }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="deel in dayparts" :key="deel.value">
                                    <th class="text-left text-xs font-normal text-muted-foreground">{{ deel.label }}</th>
                                    <td v-for="dag in dagen" :key="dag.nummer">
                                        <span
                                            class="flex h-7 items-center justify-center rounded-md"
                                            :class="trainer.grid?.[dag.nummer]?.[deel.value] ? 'bg-primary/15 text-primary' : 'bg-secondary'"
                                            :aria-label="trainer.grid?.[dag.nummer]?.[deel.value] ? 'beschikbaar' : 'niet beschikbaar'"
                                        >
                                            <Check v-if="trainer.grid?.[dag.nummer]?.[deel.value]" class="size-3.5" />
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <p v-else class="mt-3 text-xs text-muted-foreground">
                        Zolang hier niets staat gaat de planning ervan uit dat deze trainer altijd kan.
                    </p>

                    <ul v-if="trainer.exceptions.length" class="mt-3 space-y-1.5 border-t border-border pt-3">
                        <li v-for="rij in trainer.exceptions" :key="rij.id" class="flex items-start gap-2 text-xs">
                            <Check v-if="rij.available" class="mt-0.5 size-3.5 shrink-0 text-primary" />
                            <CalendarOff v-else class="mt-0.5 size-3.5 shrink-0 text-destructive" />
                            <span class="min-w-0 first-letter:uppercase">
                                {{ rij.label }}
                                <span class="text-muted-foreground">
                                    &middot; {{ rij.available ? 'wel beschikbaar' : 'niet beschikbaar'
                                    }}<span v-if="rij.note"> ({{ rij.note }})</span>
                                </span>
                            </span>
                        </li>
                    </ul>
                </article>

                <p
                    v-if="!trainers.length"
                    class="rounded-2xl border border-dashed border-border bg-card/50 p-8 text-center text-sm text-muted-foreground"
                >
                    Nog geen trainers. Nodig ze uit bij Personeel.
                </p>
            </div>
        </div>
    </AppLayout>
</template>
