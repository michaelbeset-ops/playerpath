<script setup lang="ts">
import AttentionPanel, { type AandachtItem } from '@/components/dashboard/AttentionPanel.vue';
import BirthdaysWidget from '@/components/dashboard/BirthdaysWidget.vue';
import DevelopmentWidget from '@/components/dashboard/DevelopmentWidget.vue';
import FinanceWidget from '@/components/dashboard/FinanceWidget.vue';
import KpiWidget from '@/components/dashboard/KpiWidget.vue';
import TrainingsWidget from '@/components/dashboard/TrainingsWidget.vue';
import GoalList, { type Doel } from '@/components/GoalList.vue';
import PlayerCardVisual from '@/components/PlayerCardVisual.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { CalendarPlus, Check, ClipboardList, Euro, IdCard, MapPin, Star, TrendingUp, Trophy, UserPlus, Users } from 'lucide-vue-next';
import { computed, type Component } from 'vue';

interface SpelerKaart {
    id: number;
    name: string;
    photo: string | null;
    first_name: string;
    position: string;
    position_key: 'keeper' | 'field';
    age: number | null;
    overall_rating: number | null;
    last_report_on: string | null;
    report_count: number;
    categories: { category: string; label: string; rating: number | null }[];
    level: { key: string; label: string; description: string };
    badges: { key: string; label: string; description: string }[];
    next_badge: { key: string; label: string; description: string } | null;
    goals: Doel[];
}

/** Een plek in het raster: welke widget, hoe breed, waar. */
interface Plek {
    key: string;
    size: number;
    height: number;
    x: number;
    y: number;
}

const props = defineProps<{
    view: 'school' | 'gezin';
    /** Het antwoord op "wat moet ik doen?". Staat vast bovenaan. */
    attention?: AandachtItem[];
    /** Waar de widgets staan; de server bepaalt volgorde en breedte. */
    layout?: Plek[];
    /** De inhoud per widget. Null betekent: staat niet op dit dashboard. */
    widgets?: Record<string, any>;
    checklist?: {
        steps: { key: string; title: string; body: string; href: string; action: string; done: boolean }[];
        done: number;
        total: number;
        hasTrainer: boolean;
    } | null;
    can?: { managePlayers: boolean; manageGroups: boolean; planTrainings: boolean };
    players?: SpelerKaart[];
    nextTraining?: { id: number; group: string; date: string; time: string; location: string | null } | null;
}>();

const page = usePage<SharedData>();
const school = computed(() => page.props.school);
const rollen = computed(() => page.props.auth.roles ?? []);

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

// De volgorde in het raster is ook de volgorde op een telefoon: van boven naar
// beneden, dan van links naar rechts. Zo staat het belangrijkste op een klein
// scherm bovenaan zonder een tweede lijst bij te houden.
const plekken = computed<Plek[]>(() => [...(props.layout ?? [])].sort((a, b) => a.y - b.y || a.x - b.x));

// Tailwind heeft de klasse als hele naam nodig, dus geen samengestelde string.
const kolomKlasse: Record<number, string> = {
    3: 'lg:col-span-3',
    4: 'lg:col-span-4',
    6: 'lg:col-span-6',
    8: 'lg:col-span-8',
    12: 'lg:col-span-12',
};

const kpiIconen: Record<string, Component> = {
    kpi_players: Users,
    kpi_rating: Star,
    kpi_reports: ClipboardList,
    kpi_revenue: Euro,
};

const kpiLabels: Record<string, string> = {
    kpi_players: 'Actieve spelers',
    kpi_rating: 'Gemiddelde rating',
    kpi_reports: 'Rapporten deze week',
    kpi_revenue: 'Omzet deze maand',
};

const kpiLinks: Record<string, string | undefined> = {
    kpi_players: '/clients',
    kpi_reports: '/reports',
    kpi_revenue: '/payments?tab=paid&period=this_month',
};
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-6xl p-4">
            <div class="flex flex-wrap items-baseline gap-x-3">
                <h1 class="text-2xl font-semibold tracking-tight">Dashboard</h1>
                <p class="text-sm text-muted-foreground">
                    <template v-if="school">{{ school.name }}</template>
                    <span v-if="rollen.length" class="text-muted-foreground/70"> &middot; {{ rollen.join(', ') }}</span>
                </p>
            </div>

            <!-- Eigenaar en trainer: de school -->
            <template v-if="view === 'school'">
                <!-- Drie stappen voor een nieuwe school. Verdwijnt zodra ze
                     gedaan zijn en komt nooit terug. -->
                <div v-if="checklist" class="mt-5 rounded-xl border border-primary/30 bg-primary/5 p-5">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <p class="font-medium">Nog even dit, dan draait je school</p>
                        <p class="tabular text-xs text-muted-foreground">{{ checklist.done }} van {{ checklist.total }} gedaan</p>
                    </div>

                    <ol class="mt-4 space-y-2">
                        <li
                            v-for="stap in checklist.steps"
                            :key="stap.key"
                            class="flex flex-wrap items-center gap-3 rounded-lg border border-border bg-card p-3"
                            :class="stap.done ? 'opacity-60' : ''"
                        >
                            <span
                                class="flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold"
                                :class="stap.done ? 'bg-primary text-primary-foreground' : 'border border-border text-muted-foreground'"
                            >
                                <Check v-if="stap.done" class="size-3.5" />
                                <template v-else>{{ checklist.steps.indexOf(stap) + 1 }}</template>
                            </span>

                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium" :class="stap.done ? 'line-through' : ''">{{ stap.title }}</p>
                                <p v-if="!stap.done" class="text-xs text-muted-foreground">{{ stap.body }}</p>
                            </div>

                            <Link
                                v-if="!stap.done"
                                :href="stap.href"
                                class="inline-flex h-9 shrink-0 items-center rounded-lg bg-primary px-3 text-sm font-medium text-primary-foreground"
                            >
                                {{ stap.action }}
                            </Link>
                        </li>
                    </ol>
                </div>

                <!-- 1. Snelle acties. Boven de cijfers: je opent een dashboard
                     om iets te doen. -->
                <div class="mt-5 grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
                    <Link
                        href="/reports"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-3 py-2.5 text-sm font-semibold text-primary-foreground transition hover:opacity-90 sm:justify-start sm:px-4"
                    >
                        <ClipboardList class="size-4" />
                        Rapport invullen
                    </Link>

                    <Link
                        v-if="can?.planTrainings"
                        href="/trainings/create"
                        class="inline-flex items-center justify-center gap-2 rounded-xl border border-border bg-card px-3 py-2.5 text-sm font-medium shadow-sm transition hover:border-primary sm:justify-start sm:px-4"
                    >
                        <CalendarPlus class="size-4" />
                        Training inplannen
                    </Link>

                    <Link
                        v-if="can?.managePlayers"
                        href="/players/create"
                        class="inline-flex items-center justify-center gap-2 rounded-xl border border-border bg-card px-3 py-2.5 text-sm font-medium shadow-sm transition hover:border-primary sm:justify-start sm:px-4"
                    >
                        <UserPlus class="size-4" />
                        Speler toevoegen
                    </Link>

                    <Link
                        v-if="can?.manageGroups"
                        href="/groups/create"
                        class="inline-flex items-center justify-center gap-2 rounded-xl border border-border bg-card px-3 py-2.5 text-sm font-medium shadow-sm transition hover:border-primary sm:justify-start sm:px-4"
                    >
                        <Users class="size-4" />
                        Groep toevoegen
                    </Link>
                </div>

                <!-- 2. Wat vraagt om actie. Vastgepind, niet weg te halen. -->
                <div class="mt-4">
                    <AttentionPanel :items="attention ?? []" />
                </div>

                <!-- 3 t/m 7. De widgets in het raster van twaalf kolommen; op
                     een telefoon een kolom, in dezelfde volgorde. -->
                <div class="mt-4 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-12">
                    <div
                        v-for="plek in plekken"
                        :key="plek.key"
                        :class="[kolomKlasse[plek.size] ?? 'lg:col-span-6', plek.size > 3 ? 'col-span-2' : '']"
                    >
                        <KpiWidget
                            v-if="plek.key.startsWith('kpi_') && widgets?.[plek.key]"
                            :label="kpiLabels[plek.key]"
                            :value="widgets[plek.key].value"
                            :change="widgets[plek.key].change"
                            :unit="widgets[plek.key].unit"
                            :hint="widgets[plek.key].hint"
                            :icon="kpiIconen[plek.key] ?? TrendingUp"
                            :href="kpiLinks[plek.key]"
                        />

                        <DevelopmentWidget v-else-if="plek.key === 'development' && widgets?.development" :data="widgets.development" />

                        <FinanceWidget v-else-if="plek.key === 'finance' && widgets?.finance" :data="widgets.finance" />

                        <TrainingsWidget
                            v-else-if="plek.key === 'trainings' && widgets?.trainings"
                            :data="widgets.trainings"
                            :can-plan="can?.planTrainings ?? false"
                        />

                        <BirthdaysWidget v-else-if="plek.key === 'birthdays' && widgets?.birthdays" :data="widgets.birthdays" />
                    </div>
                </div>
            </template>

            <!-- Ouder en speler: het eigen kind -->
            <template v-else>
                <!-- De kaart zelf, meteen in beeld: dat is waar een kind voor komt -->
                <div v-for="speler in players" :key="speler.id" class="mt-6">
                    <div class="theme-donker rounded-3xl bg-background p-4 text-foreground sm:p-6">
                        <PlayerCardVisual
                            :name="speler.name"
                            :photo="speler.photo"
                            :position="speler.position"
                            :position-key="speler.position_key"
                            :age="speler.age"
                            :overall="speler.overall_rating"
                            :categories="speler.categories"
                            :level="speler.level"
                            :badges="speler.badges"
                            :report-count="speler.report_count"
                        />

                        <!-- Iets om naartoe te werken -->
                        <div
                            v-if="speler.next_badge"
                            class="mx-auto mt-4 flex max-w-[22.5rem] items-start gap-3 rounded-xl border border-border bg-card/60 p-3"
                        >
                            <Trophy class="mt-0.5 size-4 shrink-0 text-gold" />
                            <p class="text-sm">
                                <span class="font-medium">Volgende mijlpaal: {{ speler.next_badge.label }}.</span>
                                <span class="text-muted-foreground"> {{ speler.next_badge.description }}.</span>
                            </p>
                        </div>
                    </div>

                    <div v-if="speler.goals.length" class="mt-3 rounded-xl border border-border bg-card p-4 shadow-sm">
                        <p class="text-sm font-medium">Waar {{ speler.first_name }} aan werkt</p>
                        <GoalList class="mt-2" :goals="speler.goals" />
                    </div>

                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <Link
                            :href="'/players/' + speler.id + '/card'"
                            class="inline-flex items-center justify-center gap-2 rounded-xl border border-border bg-card px-4 py-2.5 text-sm font-medium shadow-sm transition hover:border-primary"
                        >
                            <IdCard class="size-4" />
                            Kaart en delen
                        </Link>
                        <Link
                            :href="'/players/' + speler.id + '/progress'"
                            class="inline-flex items-center justify-center gap-2 rounded-xl border border-border bg-card px-4 py-2.5 text-sm font-medium shadow-sm transition hover:border-primary"
                        >
                            <TrendingUp class="size-4" />
                            Voortgang
                        </Link>
                    </div>
                </div>

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
            </template>
        </div>
    </AppLayout>
</template>
