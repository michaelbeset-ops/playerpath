<script setup lang="ts">
import StatCard from '@/components/StatCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { CalendarDays, ClipboardList, IdCard, MapPin, TrendingUp, Users } from 'lucide-vue-next';
import { computed } from 'vue';

interface SpelerKaart {
    id: number;
    name: string;
    first_name: string;
    position: string;
    overall_rating: number | null;
    last_report_on: string | null;
}

const props = defineProps<{
    view: 'school' | 'gezin';
    stats?: { players: number; reportsThisWeek: number; trainingsThisWeek: number };
    players?: SpelerKaart[];
    nextTraining?: { id: number; group: string; date: string; time: string; location: string | null } | null;
}>();

const page = usePage<SharedData>();
const school = computed(() => page.props.school);
const rollen = computed(() => page.props.auth.roles ?? []);

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

const kaarten = computed(() => {
    if (props.view !== 'school' || !props.stats) {
        return [];
    }

    return [
        {
            label: 'Spelers',
            value: props.stats.players,
            hint: props.stats.players === 1 ? 'actieve speler' : 'actieve spelers',
            icon: Users,
            href: '/players',
        },
        {
            label: 'Rapporten deze week',
            value: props.stats.reportsThisWeek,
            hint: props.stats.reportsThisWeek === 0 ? 'Nog niemand beoordeeld deze week' : 'ingevuld door je trainers',
            icon: ClipboardList,
            href: '/reports',
        },
        {
            label: 'Trainingen deze week',
            value: props.stats.trainingsThisWeek,
            hint: props.stats.trainingsThisWeek === 0 ? 'Niets ingepland deze week' : 'ingepland',
            icon: CalendarDays,
            href: '/trainings',
        },
    ];
});
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-5xl p-4">
            <div class="flex flex-wrap items-baseline gap-x-3">
                <h1 class="text-2xl font-semibold tracking-tight">Dashboard</h1>
                <p class="text-sm text-muted-foreground">
                    <template v-if="school">{{ school.name }}</template>
                    <span v-if="rollen.length" class="text-muted-foreground/70"> &middot; {{ rollen.join(', ') }}</span>
                </p>
            </div>

            <!-- Eigenaar en trainer: de school -->
            <template v-if="view === 'school'">
                <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <StatCard
                        v-for="kaart in kaarten"
                        :key="kaart.label"
                        :label="kaart.label"
                        :value="kaart.value"
                        :hint="kaart.hint"
                        :icon="kaart.icon"
                        :href="kaart.href"
                    />
                </div>

                <div class="mt-4 rounded-xl border border-border bg-card p-6 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div class="min-w-0">
                            <p class="font-medium">Aan de slag</p>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Vul een rapport in en de spelerskaart is meteen bijgewerkt. De ouders krijgen er automatisch bericht van.
                            </p>
                        </div>

                        <Link
                            href="/reports"
                            class="inline-flex shrink-0 items-center rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                        >
                            Rapport invullen
                        </Link>
                    </div>
                </div>
            </template>

            <!-- Ouder en speler: het eigen kind -->
            <template v-else>
                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    <Link
                        v-for="speler in players"
                        :key="speler.id"
                        :href="'/players/' + speler.id + '/card'"
                        class="group relative overflow-hidden rounded-xl border border-border bg-card p-5 shadow-sm transition hover:border-primary/40 hover:shadow"
                    >
                        <span
                            class="absolute inset-x-0 top-0 h-1"
                            :class="speler.overall_rating ? 'bg-primary' : 'bg-border'"
                            aria-hidden="true"
                        ></span>

                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <p class="truncate font-medium">{{ speler.name }}</p>
                                <p class="mt-1 text-xs text-muted-foreground">{{ speler.position }}</p>
                            </div>

                            <p
                                class="tabular shrink-0 text-3xl font-bold leading-none"
                                :class="speler.overall_rating ? 'text-primary' : 'text-muted-foreground/60'"
                            >
                                {{ speler.overall_rating ?? '—' }}
                            </p>
                        </div>

                        <p class="mt-3 text-xs text-muted-foreground">
                            <template v-if="speler.last_report_on">Laatste rapport {{ speler.last_report_on }}</template>
                            <template v-else>Nog geen rapport</template>
                        </p>

                        <span class="mt-4 inline-flex items-center gap-1.5 text-sm font-medium text-primary">
                            <IdCard class="size-4" />
                            Bekijk de spelerskaart
                        </span>
                    </Link>
                </div>

                <!-- De eerstvolgende training -->
                <div class="mt-4 rounded-xl border border-border bg-card p-5 shadow-sm">
                    <p class="font-medium">Eerstvolgende training</p>

                    <template v-if="nextTraining">
                        <p class="mt-2 text-sm first-letter:uppercase">{{ nextTraining.date }} &middot; {{ nextTraining.time }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">{{ nextTraining.group }}</p>
                        <p v-if="nextTraining.location" class="mt-1 flex items-center gap-1.5 text-xs text-muted-foreground">
                            <MapPin class="size-3.5" />
                            {{ nextTraining.location }}
                        </p>

                        <Link
                            :href="'/trainings/' + nextTraining.id"
                            class="mt-4 inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                        >
                            Aan- of afmelden
                        </Link>
                    </template>

                    <p v-else class="mt-2 text-sm text-muted-foreground">Er staat nog geen training gepland.</p>
                </div>

                <div v-if="players?.length" class="mt-4">
                    <Link
                        :href="'/players/' + players[0].id + '/progress'"
                        class="inline-flex items-center gap-2 text-sm font-medium text-primary underline underline-offset-4"
                    >
                        <TrendingUp class="size-4" />
                        Bekijk de voortgang van {{ players[0].first_name }}
                    </Link>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
