<script setup lang="ts">
import StatCard from '@/components/StatCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    CalendarDays,
    CalendarPlus,
    ClipboardList,
    CreditCard,
    IdCard,
    MapPin,
    Star,
    TrendingUp,
    UserPlus,
    UserRoundCheck,
    Users,
} from 'lucide-vue-next';
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
    stats?: {
        players: number;
        keepers: number;
        averageRating: number | null;
        reportsThisWeek: number;
        trainingsThisWeek: number;
        attendanceRate: { percentage: number | null; present: number; total: number };
        groups: number;
    };
    needsAttention?: { id: number; name: string; position: string; overall_rating: number | null; last_report_on: string | null }[];
    attentionAfterDays?: number;
    upcomingTrainings?: { id: number; group: string; date: string; time: string; location: string | null }[];
    can?: { managePlayers: boolean; manageGroups: boolean; planTrainings: boolean; seeFinance: boolean };
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
            href: '/players',
        },
        {
            label: 'Gemiddelde rating',
            value: s.averageRating,
            hint: s.averageRating === null ? 'Nog geen rapporten' : 'over alle spelerskaarten',
            icon: Star,
        },
        {
            label: 'Rapporten deze week',
            value: s.reportsThisWeek,
            hint: s.reportsThisWeek === 0 ? 'Nog niemand beoordeeld' : 'ingevuld door je trainers',
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
                <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
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

                <!-- Snelle acties: alleen wat deze rol echt mag -->
                <div class="mt-4 flex flex-wrap gap-2">
                    <Link
                        href="/reports"
                        class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                    >
                        <ClipboardList class="size-4" />
                        Rapport invullen
                    </Link>

                    <Link
                        v-if="can?.planTrainings"
                        href="/trainings/create"
                        class="inline-flex items-center gap-2 rounded-xl border border-border bg-card px-4 py-2.5 text-sm font-medium shadow-sm transition hover:border-primary"
                    >
                        <CalendarPlus class="size-4" />
                        Training inplannen
                    </Link>

                    <Link
                        v-if="can?.managePlayers"
                        href="/players/create"
                        class="inline-flex items-center gap-2 rounded-xl border border-border bg-card px-4 py-2.5 text-sm font-medium shadow-sm transition hover:border-primary"
                    >
                        <UserPlus class="size-4" />
                        Speler toevoegen
                    </Link>

                    <Link
                        v-if="can?.manageGroups"
                        href="/groups/create"
                        class="inline-flex items-center gap-2 rounded-xl border border-border bg-card px-4 py-2.5 text-sm font-medium shadow-sm transition hover:border-primary"
                    >
                        <Users class="size-4" />
                        Groep toevoegen
                    </Link>
                </div>

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
                    Financieel overzicht. Bewust leeg en eerlijk: er zijn nog geen
                    betaalgegevens, dus er staan hier geen cijfers. Mollie komt in
                    fase 7; dit vak laat alleen zien waar het straks landt.
                -->
                <div v-if="can?.seeFinance" class="mt-4 rounded-xl border border-dashed border-border bg-card/50 p-5">
                    <div class="flex items-start gap-3">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-secondary text-muted-foreground/70">
                            <CreditCard class="size-5" />
                        </span>

                        <div class="min-w-0">
                            <p class="font-medium">Financieel overzicht</p>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Hier komen je omzet, lopende abonnementen en openstaande betalingen te staan, zodra betalingen zijn
                                aangesloten. Dat is de laatste stap, bewust op een product dat verder al werkt.
                            </p>
                            <p class="mt-2 text-xs text-muted-foreground">Nog niet aangesloten &middot; komt in fase 7</p>
                        </div>
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
