<script setup lang="ts">
import AttentionPanel, { type AandachtItem } from '@/components/dashboard/AttentionPanel.vue';
import BirthdaysWidget from '@/components/dashboard/BirthdaysWidget.vue';
import DashboardGrid, { type Beschikbaar, type Plek } from '@/components/dashboard/DashboardGrid.vue';
import DevelopmentWidget from '@/components/dashboard/DevelopmentWidget.vue';
import FamilyDashboard from '@/components/dashboard/FamilyDashboard.vue';
import FinanceWidget from '@/components/dashboard/FinanceWidget.vue';
import KpiWidget from '@/components/dashboard/KpiWidget.vue';
import MyPlayersWidget from '@/components/dashboard/MyPlayersWidget.vue';
import MyTrainingsWidget from '@/components/dashboard/MyTrainingsWidget.vue';
import PlayerDashboard from '@/components/dashboard/PlayerDashboard.vue';
import ReportPrompt, { type Herinnering } from '@/components/dashboard/ReportPrompt.vue';
import TrainingsWidget from '@/components/dashboard/TrainingsWidget.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import type { FamilyAanbod, FamilyBericht, FamilyKind, FamilyTraining } from '@/types/family';
import type { SpelerDashboardData } from '@/types/player-dashboard';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { CalendarDays, CalendarPlus, Check, ClipboardList, Euro, Star, UserPlus, Users } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    view: 'school' | 'gezin' | 'speler';
    /** Het antwoord op "wat moet ik doen?". Staat vast bovenaan. */
    /** Rapporten die nu ingevuld kunnen worden; zie ReportPrompts. */
    reportPrompts?: Herinnering[];
    attention?: AandachtItem[];
    /** Waarop "wegklikken" wordt onthouden; zie AttentionItems::signature(). */
    attentionSignature?: string | null;
    /** Weggeklikt: dan staat het blok er helemaal niet. */
    attentionDismissed?: boolean;
    /** Waar de widgets staan; de server bepaalt volgorde en breedte. */
    layout?: Plek[];
    /** Wat je erbij kunt zetten in de bewerkmodus. */
    availableWidgets?: Beschikbaar[];
    /** De inhoud per widget. Null betekent: staat niet op dit dashboard. */
    widgets?: Record<string, any>;
    checklist?: {
        steps: { key: string; title: string; body: string; href: string; action: string; done: boolean }[];
        done: number;
        total: number;
        hasTrainer: boolean;
    } | null;
    can?: { managePlayers: boolean; manageGroups: boolean; planTrainings: boolean };
    /** Trainer zonder eigenaarsrol: andere kop, andere snelle acties. */
    isTrainerOnly?: boolean;

    // --- Ouder en speler ---
    /** De kinderen van deze ouder, compact; de kaart zit één tik verderop. */
    children?: FamilyKind[];
    upcoming?: FamilyTraining[];
    offerings?: FamilyAanbod[];
    messages?: FamilyBericht[];

    // --- Speler ---
    /** Alles van het spelerdashboard; zie PlayerDashboard.php. */
    player?: SpelerDashboardData['player'];
    card?: SpelerDashboardData['card'];
    quarter?: SpelerDashboardData['quarter'];
    categories?: SpelerDashboardData['categories'];
    hasEnoughData?: boolean;
    nextStep?: SpelerDashboardData['nextStep'];
    nextBadge?: SpelerDashboardData['nextBadge'];
    nextTraining?: SpelerDashboardData['nextTraining'];
}>();

const page = usePage<SharedData>();
const school = computed(() => page.props.school);
const rollen = computed(() => page.props.auth.roles ?? []);

// Alleen de voornaam: "Hallo Marieke de Vries" leest als een brief van de bank.
const voornaam = computed(() => (page.props.auth.user?.name ?? '').split(' ')[0]);

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div>
            <div class="mx-auto w-full max-w-6xl p-4">
                <!-- Een ouder krijgt een begroeting; hij komt niet naar een
                 "dashboard" maar kijken hoe het met zijn kind gaat. De rol
                 erachter zegt hem niets, dus die staat er alleen bij wie voor
                 de school werkt. -->
                <div class="flex flex-wrap items-baseline gap-x-3">
                    <h1 class="text-2xl font-semibold tracking-tight">
                        <template v-if="view === 'gezin'">Hallo {{ voornaam }}</template>
                        <template v-else-if="view === 'speler'">Hoi {{ player?.first_name ?? voornaam }} 👋</template>
                        <template v-else-if="isTrainerOnly">Hallo {{ voornaam }}</template>
                        <template v-else>Dashboard</template>
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        <template v-if="school">{{ school.name }}</template>
                        <span v-if="view === 'school' && rollen.length" class="text-muted-foreground/70"> &middot; {{ rollen.join(', ') }}</span>
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
                            class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-primary px-3 text-sm font-semibold text-primary-foreground transition hover:opacity-90 sm:justify-start sm:px-4"
                        >
                            <ClipboardList class="size-4" />
                            Rapport invullen
                        </Link>

                        <!-- Voor wie zelf voor de groep staat: waar moet ik zijn.
                             Dat is zijn eerste vraag, nog voor het invullen. -->
                        <Link
                            v-if="isTrainerOnly"
                            href="/trainings/mijn"
                            class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-border bg-card px-3 text-sm font-medium shadow-sm transition hover:border-primary sm:justify-start sm:px-4"
                        >
                            <CalendarDays class="size-4" />
                            Mijn trainingen
                        </Link>

                        <Link
                            v-if="can?.planTrainings"
                            href="/trainings/create"
                            class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-border bg-card px-3 text-sm font-medium shadow-sm transition hover:border-primary sm:justify-start sm:px-4"
                        >
                            <CalendarPlus class="size-4" />
                            Training inplannen
                        </Link>

                        <Link
                            v-if="can?.managePlayers"
                            href="/players/create"
                            class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-border bg-card px-3 text-sm font-medium shadow-sm transition hover:border-primary sm:justify-start sm:px-4"
                        >
                            <UserPlus class="size-4" />
                            Speler toevoegen
                        </Link>

                        <Link
                            v-if="can?.manageGroups"
                            href="/groups/create"
                            class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-border bg-card px-3 text-sm font-medium shadow-sm transition hover:border-primary sm:justify-start sm:px-4"
                        >
                            <Users class="size-4" />
                            Groep toevoegen
                        </Link>
                    </div>

                    <!-- Nog vóór het aandacht-blok: dit is tijdgebonden, over vijf
                     uur is het weg, en de trainer staat nu nog op het veld. -->
                    <ReportPrompt v-if="reportPrompts?.length" class="mt-4" :prompts="reportPrompts" />

                    <!-- 2. Wat vraagt om actie. Vastgepind, niet weg te halen.
                     Is er niets, dan staat er één rustige regel: een dashboard
                     dat zwijgt laat je twijfelen of je iets mist. Weggeklikt
                     verdwijnt het wel helemaal. -->
                    <div v-if="!attentionDismissed" class="mt-4">
                        <AttentionPanel :items="attention ?? []" :signature="attentionSignature" />
                    </div>

                    <!-- 3 t/m 7. De widgets in het raster van twaalf kolommen; op
                     een telefoon een kolom, in dezelfde volgorde. -->
                    <DashboardGrid class="mt-4" :layout="layout ?? []" :available="availableWidgets ?? []">
                        <template #kpi_players>
                            <KpiWidget
                                v-if="widgets?.kpi_players"
                                label="Actieve spelers"
                                :value="widgets.kpi_players.value"
                                :change="widgets.kpi_players.change"
                                :unit="widgets.kpi_players.unit"
                                :hint="widgets.kpi_players.hint"
                                :tone="widgets.kpi_players.tone"
                                :icon="Users"
                                href="/clients"
                            />
                        </template>

                        <template #kpi_rating>
                            <KpiWidget
                                v-if="widgets?.kpi_rating"
                                label="Gemiddelde rating"
                                :value="widgets.kpi_rating.value"
                                :change="widgets.kpi_rating.change"
                                :unit="widgets.kpi_rating.unit"
                                :hint="widgets.kpi_rating.hint"
                                :tone="widgets.kpi_rating.tone"
                                :icon="Star"
                            />
                        </template>

                        <template #kpi_reports>
                            <KpiWidget
                                v-if="widgets?.kpi_reports"
                                label="Rapporten deze week"
                                :value="widgets.kpi_reports.value"
                                :change="widgets.kpi_reports.change"
                                :unit="widgets.kpi_reports.unit"
                                :hint="widgets.kpi_reports.hint"
                                :tone="widgets.kpi_reports.tone"
                                :icon="ClipboardList"
                                href="/reports"
                            />
                        </template>

                        <template #kpi_revenue>
                            <KpiWidget
                                v-if="widgets?.kpi_revenue"
                                label="Omzet deze maand"
                                :value="widgets.kpi_revenue.value"
                                :change="widgets.kpi_revenue.change"
                                :unit="widgets.kpi_revenue.unit"
                                :hint="widgets.kpi_revenue.hint"
                                :tone="widgets.kpi_revenue.tone"
                                :icon="Euro"
                                href="/payments?tab=paid&period=this_month"
                            />
                        </template>

                        <template #development>
                            <DevelopmentWidget v-if="widgets?.development" :data="widgets.development" />
                        </template>

                        <template #finance>
                            <FinanceWidget v-if="widgets?.finance" :data="widgets.finance" />
                        </template>

                        <template #trainings>
                            <TrainingsWidget v-if="widgets?.trainings" :data="widgets.trainings" :can-plan="can?.planTrainings ?? false" />
                        </template>

                        <template #birthdays>
                            <BirthdaysWidget v-if="widgets?.birthdays" :data="widgets.birthdays" />
                        </template>

                        <template #my_trainings>
                            <MyTrainingsWidget v-if="widgets?.my_trainings" :data="widgets.my_trainings" />
                        </template>

                        <template #my_players>
                            <MyPlayersWidget v-if="widgets?.my_players" :data="widgets.my_players" />
                        </template>
                    </DashboardGrid>
                </template>

                <!-- De speler zelf: mijn kaart, mijn voortgang, volgende training -->
                <PlayerDashboard
                    v-else-if="view === 'speler' && card && player && quarter && categories"
                    class="mt-6"
                    :player="player"
                    :card="card"
                    :quarter="quarter"
                    :categories="categories"
                    :has-enough-data="hasEnoughData ?? false"
                    :next-step="nextStep ?? null"
                    :next-badge="nextBadge ?? null"
                    :next-training="nextTraining ?? null"
                />

                <!-- Ouder: praktisch bovenaan, de kaart één tik verderop -->
                <FamilyDashboard
                    v-else
                    class="mt-6"
                    :children="children ?? []"
                    :upcoming="upcoming ?? []"
                    :offerings="offerings ?? []"
                    :messages="messages ?? []"
                />
            </div>
        </div>
    </AppLayout>
</template>
