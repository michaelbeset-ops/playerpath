<script setup lang="ts">
import FlashMessage from '@/components/FlashMessage.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronRight, MapPin, Plus, UserCog, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface TrainingRij {
    id: number;
    group: string;
    group_id: number;
    day: string;
    day_label: string;
    is_today: boolean;
    date: string;
    starts_at: string;
    ends_at: string;
    time: string;
    location: string | null;
    trainers: string[];
    is_mine: boolean;
    has_passed: boolean;
    cancelled: boolean;
    recorded_count: number;
    present_count: number;
    expected_count: number;
    my_registration: string | null;
}

const props = defineProps<{
    upcoming: TrainingRij[];
    past: TrainingRij[];
    canManage: boolean;
    canRecord: boolean;
    isParticipant: boolean;
    filters: { group: number | null; trainer: number | null };
    groups: { id: number; name: string }[];
    trainers: { id: number; name: string }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Trainingen', href: '/trainings' }];

const tab = ref<'upcoming' | 'past'>('upcoming');

const lijst = computed(() => (tab.value === 'upcoming' ? props.upcoming : props.past));

// Per dag gegroepeerd: een rooster lees je per dag, niet als één lange rij.
// De volgorde komt van de server (komend oplopend, geweest aflopend), dus hier
// wordt alleen samengevoegd.
const dagen = computed(() => {
    const uit: { day: string; label: string; isToday: boolean; items: TrainingRij[] }[] = [];

    for (const training of lijst.value) {
        const laatste = uit[uit.length - 1];

        if (laatste?.day === training.day) {
            laatste.items.push(training);
        } else {
            uit.push({ day: training.day, label: training.day_label, isToday: training.is_today, items: [training] });
        }
    }

    return uit;
});

// --- Filteren ---
//
// De lijst is afgekapt op 50 komende trainingen, dus filteren hoort op de
// server te gebeuren: anders filter je in een lijst die al niet compleet is.
// preserveState houdt het gekozen tabblad staan.

const groep = ref<string>(props.filters.group ? String(props.filters.group) : '');
const trainer = ref<string>(props.filters.trainer ? String(props.filters.trainer) : '');

const filter = () =>
    router.get(
        '/trainings',
        { group: groep.value || undefined, trainer: trainer.value || undefined },
        { preserveState: true, preserveScroll: true, replace: true },
    );

const wisFilters = () => {
    groep.value = '';
    trainer.value = '';
    filter();
};

const gefilterd = computed(() => groep.value !== '' || trainer.value !== '');

const selectKlassen =
    'h-10 min-w-0 rounded-lg border border-border bg-card px-3 text-sm shadow-sm transition focus:border-primary focus:outline-none';

/**
 * Wat er van de aanwezigheid te zeggen valt.
 *
 * Afgevinkt en aanwezig zijn twee verschillende dingen: een training waar
 * niemand is afgevinkt heeft geen nul aanwezigen, er is niets vastgelegd.
 * En een groep zonder spelers levert "0/0" op, wat als een fout leest terwijl
 * er gewoon nog niemand ingedeeld is.
 */
const aanwezigheid = (t: TrainingRij): { tekst: string; toon: 'goed' | 'aandacht' | 'rustig' } => {
    if (t.expected_count === 0) {
        return { tekst: 'Nog geen spelers in deze groep', toon: 'rustig' };
    }

    if (t.cancelled) {
        return { tekst: 'Afgezegd', toon: 'rustig' };
    }

    if (!t.has_passed) {
        return { tekst: `${t.expected_count} ${t.expected_count === 1 ? 'speler' : 'spelers'} verwacht`, toon: 'rustig' };
    }

    if (t.recorded_count === 0) {
        return { tekst: 'Nog niet afgevinkt', toon: 'aandacht' };
    }

    return { tekst: `${t.present_count}/${t.expected_count} aanwezig`, toon: 'goed' };
};

const toonKlasse = (toon: 'goed' | 'aandacht' | 'rustig') =>
    toon === 'goed' ? 'bg-success/10 text-success' : toon === 'aandacht' ? 'bg-warning/10 text-warning' : 'bg-secondary text-muted-foreground';
</script>

<template>
    <Head title="Trainingen" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-3xl p-4">
            <FlashMessage />

            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="min-w-0">
                    <h1 class="text-2xl font-semibold tracking-tight">Trainingen</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ isParticipant ? 'De trainingen van jouw groep.' : 'Het rooster van je school.' }}
                    </p>
                </div>

                <Link
                    v-if="canManage"
                    href="/trainings/create"
                    class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                >
                    <Plus class="size-4" />
                    Training inplannen
                </Link>
            </div>

            <!-- Komend / geweest -->
            <div class="mt-6 inline-flex rounded-lg border border-border bg-card p-1 shadow-sm">
                <button
                    type="button"
                    class="rounded-md px-4 py-1.5 text-sm font-medium transition"
                    :class="tab === 'upcoming' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'"
                    @click="tab = 'upcoming'"
                >
                    Komend ({{ upcoming.length }})
                </button>
                <button
                    type="button"
                    class="rounded-md px-4 py-1.5 text-sm font-medium transition"
                    :class="tab === 'past' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'"
                    @click="tab = 'past'"
                >
                    Geweest ({{ past.length }})
                </button>
            </div>

            <!-- Filteren op groep en trainer -->
            <div v-if="groups.length || trainers.length" class="mt-3 flex flex-wrap items-center gap-2">
                <label class="sr-only" for="filter-groep">Groep</label>
                <select id="filter-groep" v-model="groep" :class="selectKlassen" @change="filter">
                    <option value="">Alle groepen</option>
                    <option v-for="g in groups" :key="g.id" :value="String(g.id)">{{ g.name }}</option>
                </select>

                <label class="sr-only" for="filter-trainer">Trainer</label>
                <select id="filter-trainer" v-model="trainer" :class="selectKlassen" @change="filter">
                    <option value="">Alle trainers</option>
                    <option v-for="t in trainers" :key="t.id" :value="String(t.id)">{{ t.name }}</option>
                </select>

                <button
                    v-if="gefilterd"
                    type="button"
                    class="inline-flex h-10 items-center gap-1 rounded-lg px-2 text-sm font-medium text-muted-foreground transition hover:text-foreground"
                    @click="wisFilters"
                >
                    <X class="size-4" />
                    Wis filter
                </button>
            </div>

            <!-- Het rooster, per dag -->
            <div v-if="dagen.length" class="mt-5 space-y-5">
                <section v-for="dag in dagen" :key="dag.day">
                    <div class="flex items-center gap-2">
                        <h2 class="text-sm font-semibold first-letter:uppercase" :class="dag.isToday ? 'text-primary' : ''">
                            {{ dag.label }}
                        </h2>
                        <span v-if="dag.isToday" class="rounded-full bg-primary px-2 py-0.5 text-[10px] font-semibold text-primary-foreground">
                            vandaag
                        </span>
                    </div>

                    <div class="mt-2 space-y-2">
                        <article
                            v-for="training in dag.items"
                            :key="training.id"
                            class="rounded-xl border bg-card p-3 shadow-sm"
                            :class="training.is_mine && !training.cancelled ? 'border-primary/40' : 'border-border'"
                        >
                            <div class="flex min-w-0 gap-3">
                                <span
                                    class="tabular w-12 shrink-0 text-sm font-semibold"
                                    :class="training.cancelled ? 'text-muted-foreground' : 'text-primary'"
                                >
                                    {{ training.starts_at }}
                                    <span class="block text-xs font-normal text-muted-foreground">{{ training.ends_at }}</span>
                                </span>

                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <Link
                                            :href="'/trainings/' + training.id"
                                            class="font-medium hover:text-primary"
                                            :class="training.cancelled ? 'line-through' : ''"
                                        >
                                            {{ training.group }}
                                        </Link>
                                        <span
                                            v-if="training.is_mine"
                                            class="rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-primary"
                                        >
                                            jij
                                        </span>
                                        <span
                                            v-if="training.cancelled"
                                            class="rounded-full bg-secondary px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground"
                                        >
                                            afgezegd
                                        </span>
                                    </div>

                                    <p class="mt-1 flex items-start gap-1.5 text-xs text-muted-foreground">
                                        <MapPin class="mt-0.5 size-3.5 shrink-0" />
                                        <span>{{ training.location || 'Geen locatie ingevuld' }}</span>
                                    </p>
                                    <p class="mt-0.5 flex items-start gap-1.5 text-xs text-muted-foreground">
                                        <UserCog class="mt-0.5 size-3.5 shrink-0" />
                                        <span>{{ training.trainers.join(', ') || 'Geen trainer gekoppeld' }}</span>
                                    </p>

                                    <!-- Stand en acties op één regel: dit is een lijst waar je
                                         doorheen scrolt, dus elke extra regel telt. -->
                                    <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1">
                                        <!-- Voor een trainer: hoe staat het met de aanwezigheid? -->
                                        <span
                                            v-if="!isParticipant"
                                            class="inline-flex items-center rounded-lg px-2 py-1 text-xs font-medium"
                                            :class="toonKlasse(aanwezigheid(training).toon)"
                                        >
                                            {{ aanwezigheid(training).tekst }}
                                        </span>

                                        <Link
                                            :href="'/trainings/' + training.id"
                                            class="ml-auto inline-flex items-center gap-0.5 text-xs font-medium text-muted-foreground transition hover:text-foreground"
                                        >
                                            Details
                                            <ChevronRight class="size-3.5" />
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </div>
                </section>
            </div>

            <div v-else class="mt-5 rounded-2xl border border-dashed border-border bg-card/50 p-10 text-center">
                <p class="font-medium">
                    <template v-if="gefilterd">Geen trainingen met dit filter</template>
                    <template v-else>{{ tab === 'upcoming' ? 'Geen komende trainingen' : 'Nog geen trainingen geweest' }}</template>
                </p>
                <p class="mt-1 text-sm text-muted-foreground">
                    <template v-if="gefilterd">Kies een andere groep of trainer, of wis het filter.</template>
                    <template v-else-if="tab === 'upcoming' && canManage">Plan er een in om te beginnen.</template>
                    <template v-else-if="tab === 'upcoming'">Zodra je trainer er een inplant, staat hij hier.</template>
                </p>
            </div>
        </div>
    </AppLayout>
</template>
