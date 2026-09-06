<script setup lang="ts">
import GoalList, { type Doel } from '@/components/GoalList.vue';
import PlayerCardVisual from '@/components/PlayerCardVisual.vue';
import StatCard from '@/components/StatCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { CalendarDays, CalendarPlus, Check, ClipboardList, CreditCard, IdCard, Inbox, MapPin, Plug, Star, TrendingUp, Trophy, UserPlus, UserRoundCheck, Users } from 'lucide-vue-next';
import { computed } from 'vue';

interface SpelerKaart {
    id: number;
    name: string;
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

const props = defineProps<{
    view: 'school' | 'gezin';
    stats?: {
        players: number;
        keepers: number;
        averageRating: number | null;
        reportsThisWeek: number;
        trainingsThisWeek: number;
        attendanceRate: { percentage: number | null; present: number; total: number };
        groups: number;
        pendingEnrollments: number;
        playersWithGoal: number;
    };
    checklist?: {
        steps: { key: string; title: string; body: string; href: string; action: string; done: boolean }[];
        done: number;
        total: number;
        hasTrainer: boolean;
    } | null;
    needsAttention?: { id: number; name: string; position: string; overall_rating: number | null; last_report_on: string | null }[];
    attentionAfterDays?: number;
    upcomingTrainings?: { id: number; group: string; date: string; time: string; location: string | null }[];
    can?: { managePlayers: boolean; manageGroups: boolean; planTrainings: boolean; seeFinance: boolean };
    finance?: {
        revenueThisMonth: string;
        outstanding: string;
        outstandingCount: number;
        overdue: string;
        overdueCount: number;
        activeSubscriptions: number;
        yearlyValue: string;
        needsAttentionCount: number;
    } | null;
    gateway?: { connected: boolean; name: string; message: string };
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

    const s = props.stats;

    return [
        {
            label: 'Actieve spelers',
            value: s.players,
            hint: `${s.keepers} ${s.keepers === 1 ? 'keeper' : 'keepers'} · ${s.groups} ${s.groups === 1 ? 'groep' : 'groepen'}`,
            icon: Users,
            href: '/users',
        },
        {
            label: 'Gemiddelde rating',
            value: s.averageRating,
            hint: s.averageRating === null ? 'Nog geen rapporten' : s.playersWithGoal + (s.playersWithGoal === 1 ? ' speler' : ' spelers') + ' met een actief doel',
            icon: Star,
        },
        {
            label: 'Rapporten',
            value: s.reportsThisWeek,
            hint: s.reportsThisWeek === 0 ? 'deze week nog niemand beoordeeld' : 'deze week, ingevuld door je trainers',
            icon: ClipboardList,
            href: '/reports',
        },
        {
            label: 'Opkomst',
            value: s.attendanceRate.percentage === null ? null : `${s.attendanceRate.percentage}%`,
            hint:
                s.attendanceRate.percentage === null
                    ? 'Nog niets afgevinkt'
                    : `${s.attendanceRate.present} van ${s.attendanceRate.total} laatste 30 dagen`,
            icon: UserRoundCheck,
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
                <!-- Drie stappen voor een nieuwe school. Verdwijnt zodra ze
                     gedaan zijn en komt nooit terug. -->
                <div v-if="checklist" class="mt-6 rounded-xl border border-primary/30 bg-primary/5 p-5">
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

                    <p v-if="!checklist.hasTrainer" class="mt-3 text-xs text-muted-foreground">
                        Werk je met meer trainers? Nodig ze uit bij
                        <Link href="/users" class="underline underline-offset-4">Gebruikers</Link>.
                    </p>
                </div>

                <!-- Snelle acties staan boven de cijfers, niet eronder. Je opent
                     een dashboard om iets te dóén; op een telefoon stond de eerste
                     knop op 712 pixels, een volledig scherm scrollen. -->
                <!-- Alleen wat deze rol echt mag; zie de policies. -->
                <div class="mt-6 grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
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

                <div class="mt-4 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
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

                <!-- Nieuwe inschrijvingen: alleen voor de eigenaar, en alleen als er iets ligt -->
                <Link
                    v-if="can?.seeFinance && stats && stats.pendingEnrollments > 0"
                    href="/enrollments"
                    class="mt-4 flex items-center gap-3 rounded-xl border border-warning/40 bg-warning/5 p-4 transition hover:border-warning"
                >
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-warning/10 text-warning">
                        <Inbox class="size-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="font-medium">
                            {{ stats.pendingEnrollments }} {{ stats.pendingEnrollments === 1 ? 'nieuwe inschrijving' : 'nieuwe inschrijvingen' }}
                        </p>
                        <p class="text-xs text-muted-foreground">Wacht op je goedkeuring.</p>
                    </div>
                </Link>

                <div class="mt-4 grid gap-4 lg:grid-cols-2">
                    <!-- Waar valt het stil: het belangrijkste lijstje voor een eigenaar -->
                    <div class="rounded-xl border border-border bg-card p-5 shadow-sm">
                        <p class="font-medium">Vraagt om aandacht</p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Spelers zonder rapport in de laatste {{ attentionAfterDays }} dagen. Een lege kaart is precies waarom een ouder
                            afhaakt.
                        </p>

                        <div v-if="needsAttention?.length" class="mt-4 space-y-2">
                            <Link
                                v-for="speler in needsAttention"
                                :key="speler.id"
                                :href="'/players/' + speler.id + '/reports/create'"
                                class="flex items-center gap-3 rounded-lg border border-border p-3 transition hover:border-primary"
                            >
                                <span
                                    class="tabular flex size-9 shrink-0 items-center justify-center rounded-lg text-sm font-bold"
                                    :class="speler.overall_rating ? 'bg-secondary text-muted-foreground' : 'bg-warning/10 text-warning'"
                                >
                                    {{ speler.overall_rating ?? '—' }}
                                </span>

                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium">{{ speler.name }}</p>
                                    <p class="truncate text-xs text-muted-foreground">
                                        {{ speler.position }} &middot;
                                        <template v-if="speler.last_report_on">laatst beoordeeld {{ speler.last_report_on }}</template>
                                        <template v-else>nog nooit beoordeeld</template>
                                    </p>
                                </div>
                            </Link>
                        </div>

                        <p v-else class="mt-4 rounded-lg border border-primary/25 bg-primary/5 p-3 text-sm">
                            Iedereen is recent beoordeeld. Netjes.
                        </p>
                    </div>

                    <!-- Komende trainingen -->
                    <div class="rounded-xl border border-border bg-card p-5 shadow-sm">
                        <div class="flex items-baseline justify-between gap-2">
                            <p class="font-medium">Komende trainingen</p>
                            <Link href="/trainings" class="text-xs font-medium text-primary underline underline-offset-4">Alles</Link>
                        </div>

                        <div v-if="upcomingTrainings?.length" class="mt-4 space-y-2">
                            <Link
                                v-for="training in upcomingTrainings"
                                :key="training.id"
                                :href="'/trainings/' + training.id"
                                class="flex items-center gap-3 rounded-lg border border-border p-3 transition hover:border-primary"
                            >
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                    <CalendarDays class="size-4" />
                                </span>

                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium">{{ training.group }}</p>
                                    <p class="truncate text-xs text-muted-foreground first-letter:uppercase">
                                        {{ training.date }} &middot; {{ training.time }}
                                        <span v-if="training.location"> &middot; {{ training.location }}</span>
                                    </p>
                                </div>
                            </Link>
                        </div>

                        <p v-else class="mt-4 text-sm text-muted-foreground">
                            Er staat niets gepland.
                            <Link v-if="can?.planTrainings" href="/trainings/create" class="font-medium text-primary underline underline-offset-4">
                                Plan een training
                            </Link>
                        </p>
                    </div>
                </div>

                <!--
                    Financieel overzicht. De cijfers komen uit de administratie;
                    of er ook echt geïncasseerd wordt hangt af van de gateway.
                -->
                <div v-if="can?.seeFinance && finance" class="mt-4 rounded-xl border border-border bg-card p-5 shadow-sm">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <p class="font-medium">Financieel overzicht</p>
                        <Link href="/payments" class="text-xs font-medium text-primary underline underline-offset-4">Alle betalingen</Link>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                        <div>
                            <p class="tabular text-2xl font-bold leading-none text-primary">{{ finance.revenueThisMonth }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">ontvangen deze maand</p>
                        </div>
                        <div>
                            <p
                                class="tabular text-2xl font-bold leading-none"
                                :class="finance.outstandingCount ? 'text-foreground' : 'text-muted-foreground/60'"
                            >
                                {{ finance.outstanding }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                openstaand<span v-if="finance.overdueCount"> &middot; {{ finance.overdueCount }} te laat</span>
                            </p>
                        </div>
                        <div>
                            <p class="tabular text-2xl font-bold leading-none">{{ finance.activeSubscriptions }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">lopende abonnementen</p>
                        </div>
                        <div>
                            <p class="tabular text-2xl font-bold leading-none">{{ finance.yearlyValue }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">op jaarbasis</p>
                        </div>
                    </div>

                    <p
                        v-if="finance.needsAttentionCount"
                        class="mt-4 rounded-lg border border-destructive/25 bg-destructive/5 px-3 py-2 text-sm"
                    >
                        {{ finance.needsAttentionCount }}
                        {{ finance.needsAttentionCount === 1 ? 'betaling is mislukt of gestorneerd' : 'betalingen zijn mislukt of gestorneerd' }}.
                        <Link href="/payments?status=failed" class="font-medium text-destructive underline underline-offset-4">Bekijken</Link>
                    </p>

                    <p v-if="!gateway?.connected" class="mt-4 flex items-start gap-2 text-xs text-muted-foreground">
                        <Plug class="mt-0.5 size-3.5 shrink-0" />
                        {{ gateway?.name }} is nog niet aangesloten, dus er wordt niets automatisch geïncasseerd. Deze cijfers komen uit wat
                        je zelf hebt vastgelegd.
                    </p>
                </div>

            </template>

            <!-- Ouder en speler: het eigen kind -->
            <template v-else>
                <!-- De kaart zelf, meteen in beeld: dat is waar een kind voor komt -->
                <div v-for="speler in players" :key="speler.id" class="mt-6">
                    <div class="theme-donker rounded-3xl bg-background p-4 text-foreground sm:p-6">
                        <PlayerCardVisual
                            :name="speler.name"
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
                        <div v-if="speler.next_badge" class="mx-auto mt-4 flex max-w-[22.5rem] items-start gap-3 rounded-xl border border-border bg-card/60 p-3">
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
