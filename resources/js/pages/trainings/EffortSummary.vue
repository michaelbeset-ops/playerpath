<script setup lang="ts">
import Avatar from '@/components/Avatar.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { CalendarDays, CircleCheck, GraduationCap, Sparkles } from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * Na de inzetflow: wat deze training opleverde, in één overzicht.
 *
 * Geen ranglijst: de kinderen staan op volgorde van invullen, niet op
 * punten. Wie een level omhoog ging wordt gevierd; wie nog open staat, staat
 * er met één tik naar het invulscherm.
 */
const props = defineProps<{
    training: { id: number; group: string; date: string; time: string };
    results: {
        id: number;
        name: string;
        photo: string | null;
        present: boolean;
        points: number;
        labels: string[];
        level: string;
        level_up: string | null;
    }[];
    total: number;
    doneCount: number;
    open: { id: number; name: string }[];
    course: { id: number; name: string } | null;
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Mijn trainingen', href: '/trainings/mijn' },
    { title: props.training.group, href: '/trainings/' + props.training.id },
    { title: 'Inzet', href: '/trainings/' + props.training.id + '/inzet/klaar' },
]);

const levelUps = computed(() => props.results.filter((r) => r.level_up));
</script>

<template>
    <Head title="Inzet gegeven" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl p-4">
            <div class="rounded-2xl border border-primary/30 bg-card p-5 shadow-sm">
                <p class="flex items-center gap-2 text-xl font-bold text-primary">
                    <CircleCheck class="size-6" />
                    {{ open.length ? 'Opgeslagen' : 'Klaar voor vandaag' }}
                </p>
                <p class="mt-1 text-sm text-muted-foreground first-letter:uppercase">
                    {{ training.group }} &middot; {{ training.date }} &middot;
                    <span class="tabular">{{ doneCount }} van {{ total }}</span> gedaan
                </p>

                <div v-if="levelUps.length" class="mt-4 rounded-xl bg-gold/10 p-3">
                    <p class="flex items-center gap-2 text-sm font-semibold text-gold">
                        <Sparkles class="size-4" />
                        Nieuwe kaart!
                    </p>
                    <p class="mt-1 text-sm">
                        <span v-for="(r, i) in levelUps" :key="r.id"
                            >{{ i > 0 ? ', ' : '' }}<strong>{{ r.name }}</strong> is nu {{ r.level_up }}</span
                        >.
                    </p>
                </div>
            </div>

            <section v-if="results.length" class="mt-4 space-y-2">
                <div v-for="r in results" :key="r.id" class="flex items-center gap-3 rounded-xl border border-border bg-card p-3 shadow-sm">
                    <Avatar :name="r.name" :photo="r.photo" size="size-10" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium">{{ r.name }}</p>
                        <p class="mt-0.5 flex flex-wrap gap-1 text-xs text-muted-foreground">
                            <template v-if="!r.present">Afwezig</template>
                            <template v-else-if="r.labels.length">
                                <span v-for="label in r.labels" :key="label" class="rounded-md bg-primary/10 px-1.5 py-0.5 font-medium text-primary">{{
                                    label
                                }}</span>
                            </template>
                            <template v-else>Aanwezig</template>
                        </p>
                    </div>
                    <p v-if="r.present" class="tabular shrink-0 text-sm font-bold text-primary">+{{ r.points }}</p>
                </div>
            </section>

            <section v-if="open.length" class="mt-5">
                <p class="text-sm font-medium">Nog open</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    <Link
                        v-for="speler in open"
                        :key="speler.id"
                        :href="'/trainings/' + training.id + '/inzet?speler=' + speler.id"
                        class="inline-flex min-h-11 items-center rounded-xl border border-border bg-card px-3 text-sm transition hover:border-primary"
                    >
                        {{ speler.name }}
                    </Link>
                </div>
            </section>

            <div class="mt-6 grid gap-2 sm:grid-cols-2">
                <Link
                    v-if="course"
                    :href="'/aanbod/' + course.id + '/voortgang'"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-border bg-card px-4 text-sm font-medium shadow-sm transition hover:border-primary"
                >
                    <GraduationCap class="size-4" />
                    Niveaus {{ course.name }}
                </Link>
                <Link
                    href="/trainings/mijn"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                >
                    <CalendarDays class="size-4" />
                    Mijn trainingen
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
