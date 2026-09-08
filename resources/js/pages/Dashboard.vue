<script setup lang="ts">
import AttentionPanel, { type AandachtItem } from '@/components/dashboard/AttentionPanel.vue';
import BirthdaysWidget from '@/components/dashboard/BirthdaysWidget.vue';
import DashboardGrid, { type Beschikbaar, type Plek } from '@/components/dashboard/DashboardGrid.vue';
import DevelopmentWidget from '@/components/dashboard/DevelopmentWidget.vue';
import FamilyDashboard from '@/components/dashboard/FamilyDashboard.vue';
import FinanceWidget from '@/components/dashboard/FinanceWidget.vue';
import KpiWidget from '@/components/dashboard/KpiWidget.vue';
import MobileSummary from '@/components/dashboard/MobileSummary.vue';
import MyPlayersWidget from '@/components/dashboard/MyPlayersWidget.vue';
import MyTrainingsWidget from '@/components/dashboard/MyTrainingsWidget.vue';
import PlayerDashboard from '@/components/dashboard/PlayerDashboard.vue';
import ReportPrompt, { type Herinnering } from '@/components/dashboard/ReportPrompt.vue';
import TrainingsWidget from '@/components/dashboard/TrainingsWidget.vue';
import SetupChecklist from '@/components/onboarding/SetupChecklist.vue';
import WelcomeNote from '@/components/onboarding/WelcomeNote.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import type { FamilyAanbod, FamilyBericht, FamilyKind, FamilyTraining } from '@/types/family';
import type { SpelerDashboardData } from '@/types/player-dashboard';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { CalendarDays, CalendarPlus, ClipboardList, Euro, Star, UserPlus, Users } from 'lucide-vue-next';
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
        steps: { key: string; title: string; body: string; href: string; action: string; done: boolean; highlight?: boolean }[];
        done: number;
        total: number;
        complete: boolean;
        dismissed: boolean;
    } | null;
    can?: { managePlayers: boolean; manageGroups: boolean; planTrainings: boolean };
    /** Trainer zonder eigenaarsrol: andere kop, andere snelle acties. */
    isTrainerOnly?: boolean;
    /** De eigenaar kijkt naar het ouderscherm, met de voorbeeldspelers als kinderen. */
    preview?: boolean;

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
    badges?: SpelerDashboardData['badges'];
    share?: SpelerDashboardData['share'];
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
                    <!-- De startlijst: wat er moet gebeuren voordat de school
                         van jou is. Verdwijnt met een felicitatie zodra alles
                         gedaan is, en is weg te klikken. -->
                    <SetupChecklist v-if="checklist" class="mt-5" :data="checklist" />

                    <!--
                        De volgorde verschilt per schermmaat, en dat is geen
                        cosmetica.

                        Op een laptop staan de snelle acties bovenaan: je hebt
                        het hele dashboard toch in beeld, en je opent het om
                        iets te doen. Op een telefoon zie je maar één ding
                        tegelijk, en dan is de eerste vraag "moet er iets
                        gebeuren?" — dus staat het aandacht-blok daar boven de
                        knoppen. Met order-klassen op één flexkolom, want het is
                        dezelfde inhoud in een andere volgorde en geen tweede
                        dashboard dat kan gaan afwijken.
                    -->
                    <div class="mt-5 flex flex-col gap-4">
                        <!-- 1. Snelle acties. Op een telefoon hooguit twee: de rest
                     zit onder de plusknop in de balk, en vier knoppen duwen de
                     cijfers een half scherm naar beneden. -->
                        <div class="order-3 grid grid-cols-2 gap-2 sm:flex sm:flex-wrap lg:order-1">
                            <Link
                                href="/reports"
                                class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl bg-primary px-2.5 text-sm font-semibold text-primary-foreground transition hover:opacity-90 sm:justify-start sm:px-4"
                            >
                                <ClipboardList class="size-4" />
                                Rapport invullen
                            </Link>

                            <!-- Voor wie zelf voor de groep staat: waar moet ik zijn.
                             Dat is zijn eerste vraag, nog voor het invullen. -->
                            <Link
                                v-if="isTrainerOnly"
                                href="/trainings/mijn"
                                class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl border border-border bg-card px-2.5 text-sm font-medium shadow-sm transition hover:border-primary sm:justify-start sm:px-4"
                            >
                                <CalendarDays class="size-4" />
                                Mijn trainingen
                            </Link>

                            <Link
                                v-if="can?.planTrainings"
                                href="/trainings/create"
                                class="min-h-11 items-center justify-center gap-1.5 rounded-xl border border-border bg-card px-2.5 text-sm font-medium shadow-sm transition hover:border-primary sm:justify-start sm:px-4"
                                :class="isTrainerOnly ? 'hidden sm:inline-flex' : 'inline-flex'"
                            >
                                <CalendarPlus class="size-4" />
                                Training inplannen
                            </Link>

                            <Link
                                v-if="can?.managePlayers"
                                href="/players/create"
                                class="hidden min-h-11 items-center justify-center gap-1.5 rounded-xl border border-border bg-card px-2.5 text-sm font-medium shadow-sm transition hover:border-primary sm:inline-flex sm:justify-start sm:px-4"
                            >
                                <UserPlus class="size-4" />
                                Speler toevoegen
                            </Link>

                            <Link
                                v-if="can?.manageGroups"
                                href="/groups/create"
                                class="hidden min-h-11 items-center justify-center gap-1.5 rounded-xl border border-border bg-card px-2.5 text-sm font-medium shadow-sm transition hover:border-primary sm:inline-flex sm:justify-start sm:px-4"
                            >
                                <Users class="size-4" />
                                Groep toevoegen
                            </Link>
                        </div>

                        <!-- Nog vóór het aandacht-blok: dit is tijdgebonden, over vijf
                     uur is het weg, en de trainer staat nu nog op het veld. -->
                        <ReportPrompt v-if="reportPrompts?.length" class="order-1 lg:order-2" :prompts="reportPrompts" />

                        <!-- 2. Wat vraagt om actie. Vastgepind, niet weg te halen.
                     Is er niets, dan staat er één rustige regel: een dashboard
                     dat zwijgt laat je twijfelen of je iets mist. Weggeklikt
                     verdwijnt het wel helemaal. -->
                        <div v-if="!attentionDismissed" class="order-2 lg:order-3">
                            <AttentionPanel :items="attention ?? []" :signature="attentionSignature" />
                        </div>

                        <!-- 3 t/m 7. De widgets in het raster van twaalf kolommen; op
                     een telefoon een kolom, in dezelfde volgorde. -->
                        <DashboardGrid class="order-4" :layout="layout ?? []" :available="availableWidgets ?? []">
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

                        <!-- Wat er op een telefoon overblijft van de blokken die
                         daar niet passen: één regel per stuk, met een link. -->
                        <MobileSummary class="order-5 lg:hidden" :development="widgets?.development" :finance="widgets?.finance" />
                    </div>
                </template>

                <!-- Ouder en speler krijgen geen wizard en geen rondleiding: die
                     moeten het meteen snappen. Wel één vriendelijke regel bij het
                     eerste bezoek, die na sluiten niet terugkomt. -->
                <!-- De eigenaar kijkt mee met een ouder. Zeg dat erbij, anders
                     denkt hij dat zijn eigen dashboard ineens anders is. -->
                <div v-if="preview" class="mt-5 rounded-xl border border-primary/30 bg-primary/5 p-3 text-sm">
                    <p class="font-medium">Zo ziet een ouder het</p>
                    <p class="text-xs text-muted-foreground">
                        Dit is het dashboard van een ouder, met de voorbeeldspelers als kinderen. Geen instellingen, niets in te vullen: wanneer is de
                        training, hoe gaat het met mijn kind, wat staat er open.
                    </p>
                    <Link
                        href="/dashboard"
                        class="mt-2 inline-flex min-h-11 items-center text-xs font-medium text-primary underline underline-offset-4"
                    >
                        Terug naar mijn dashboard
                    </Link>
                </div>

                <WelcomeNote
                    v-if="!preview && (view === 'speler' || view === 'gezin')"
                    class="mt-5"
                    :role="view === 'speler' ? 'speler' : 'ouder'"
                    :name="view === 'speler' ? (player?.first_name ?? null) : voornaam"
                />

                <!-- De speler zelf: mijn kaart, mijn voortgang, volgende training.
                     Bewust een eigen v-if en geen v-else aan de welkomstregel:
                     zo hing dit blok aan een regel die je kunt wegklikken, en
                     zag een speler bij zijn eerste bezoek alleen die regel. -->
                <PlayerDashboard
                    v-if="view === 'speler' && card && player && quarter && categories"
                    class="mt-6"
                    :player="player"
                    :card="card"
                    :quarter="quarter"
                    :categories="categories"
                    :has-enough-data="hasEnoughData ?? false"
                    :next-step="nextStep ?? null"
                    :next-badge="nextBadge ?? null"
                    :next-training="nextTraining ?? null"
                    :upcoming="upcoming ?? []"
                    :badges="badges ?? []"
                    :share="share ?? null"
                />

                <!-- Ouder: praktisch bovenaan, de kaart één tik verderop. Alleen
                     voor de gezinsweergave: als terugval stond dit ook onder het
                     dashboard van de eigenaar en de trainer. -->
                <FamilyDashboard
                    v-else-if="view === 'gezin'"
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
