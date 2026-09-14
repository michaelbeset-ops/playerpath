<script setup lang="ts">
import GoalList, { type Doel } from '@/components/GoalList.vue';
import GradeChart from '@/components/GradeChart.vue';
import GradeChip from '@/components/GradeChip.vue';
import LineChart from '@/components/LineChart.vue';
import type { Kaart } from '@/components/PlayerCardVisual.vue';
import CourseProgressBlock, { type Cursus } from '@/components/progress/CourseProgressBlock.vue';
import RatingExplanation from '@/components/RatingExplanation.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useGrading } from '@/lib/grade';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import {
    CalendarCheck,
    CircleHelp,
    ClipboardList,
    Flame,
    GraduationCap,
    IdCard,
    Minus,
    Sparkles,
    Target,
    TrendingDown,
    TrendingUp,
    Trophy,
    Zap,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Trend {
    key: 'sterk' | 'groei' | 'stabiel' | 'aandacht';
    label: string;
}

interface CategorieVerloop {
    category: string;
    label: string;
    series: (number | null)[];
    first: number | null;
    last: number | null;
    delta: number | null;
    trend: Trend | null;
}

const props = defineProps<{
    player: { id: number; name: string; first_name: string; position: string; overall_rating: number | null };
    card: Kaart;
    level: { key: string; label: string; xp: number; next: { key: string; label: string; xp: number; remaining: number } | null; progress: number };
    progress: {
        points: { date: string; label: string; overall: number; scores: Record<string, number> }[];
        categories: CategorieVerloop[];
        overall: { series: number[]; first: number | null; last: number | null; delta: number | null; trend: Trend | null };
        hasEnoughData: boolean;
    };
    timeline: { type: string; date: string; title: string; body: string | null; value: number | null; delta: number | null }[];
    quarter: { reports: number; trainings: number; growth: number | null; best: string | null };
    goals: Doel[];
    nextStep: {
        type: 'goal' | 'suggestion';
        category: string;
        label: string;
        hint?: string;
        from: number | null;
        to: number;
        on_track: boolean | null;
        days_left: number | null;
    } | null;
    canReport: boolean;
    /** Inzetkaart: begin- en eindniveau per cursus. */
    courses: Cursus[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: props.player.name, href: '/players/' + props.player.id + '/card' },
    { title: 'Voortgang', href: '/players/' + props.player.id + '/progress' },
];

const labels = computed(() => props.progress.points.map((p) => p.label));

// In kleuren: een grafiek van werkpunt naar top, en nergens een getal.
const { kleuren, niveauVoor, inzet, voortgang } = useGrading();

// Een tabelweergave hoort erbij: de cijfers mogen nooit alleen in een plaatje
// zitten. Zie de datavisualisatie-richtlijnen.
const toonTabel = ref(false);
const uitlegOpen = ref(false);

const deltaTekst = (delta: number | null) => (delta === null ? '' : delta > 0 ? `+${delta}` : `${delta}`);

/*
 * De kop van het verhaal, in gewone taal.
 *
 * Groei wordt gevierd, maar een mindere periode wordt niet weggepoetst: een
 * pagina die altijd juicht gelooft een ouder na twee keer niet meer. Wel warm
 * geformuleerd - achteruitgang hoort bij leren, en dat mag er staan.
 */
const kop = computed(() => {
    const groei = props.quarter.growth;

    if (groei === null) {
        return {
            toon: 'rustig',
            tekst: 'Nog te weinig rapporten voor een vergelijking',
            sub: 'Vanaf twee rapporten in drie maanden zie je hier de groei.',
        };
    }

    if (groei > 0) {
        return {
            toon: 'goed',
            tekst: kleuren.value ? 'Gegroeid in drie\u00a0maanden\u00a0🎉' : `+${groei} gegroeid in drie\u00a0maanden\u00a0🎉`,
            sub: kleuren.value
                ? `${props.player.first_name} staat nu op ${niveauVoor(props.progress.overall.last)?.label ?? '-'}.`
                : `${props.player.first_name} staat nu op ${props.progress.overall.last ?? '-'}.`,
        };
    }

    if (groei === 0) {
        return { toon: 'rustig', tekst: 'Stabiel de afgelopen drie maanden', sub: 'Hetzelfde niveau vasthouden is ook een prestatie.' };
    }

    return {
        toon: 'aandacht',
        tekst: kleuren.value ? 'Iets minder dan drie maanden geleden' : `${groei} punten ten opzichte van drie maanden geleden`,
        sub: 'Dat hoort bij leren. Een paar goede trainingen en de lijn draait weer.',
    };
});

const trendKleur = (trend: Trend | null) => {
    if (trend === null) {
        return 'text-muted-foreground';
    }

    return trend.key === 'aandacht' ? 'text-warning' : trend.key === 'stabiel' ? 'text-muted-foreground' : 'text-primary';
};

const trendIcoon = (trend: Trend | null) => {
    if (trend === null || trend.key === 'stabiel') {
        return Minus;
    }

    return trend.key === 'aandacht' ? TrendingDown : TrendingUp;
};

const tijdlijnIcoon = (type: string) =>
    type === 'level' ? Sparkles : type === 'mijlpaal' ? Trophy : type === 'inzet' ? Flame : type === 'cursus' ? GraduationCap : ClipboardList;

// Standaard de laatste paar; de rest achter een knop. Een tijdlijn van dertig
// rapporten is geen scherm maar een archief.
const TIJDLIJN_KORT = 4;
const tijdlijnUit = ref(false);
const tijdlijnZichtbaar = computed(() => (tijdlijnUit.value ? props.timeline : props.timeline.slice(0, TIJDLIJN_KORT)));
</script>

<template>
    <Head :title="'Voortgang - ' + player.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-4xl p-4">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <h1 class="text-2xl font-semibold tracking-tight">Voortgang</h1>
                    <p class="mt-1 text-sm text-muted-foreground">{{ player.name }} &middot; {{ player.position }}</p>
                </div>

                <Link
                    :href="'/players/' + player.id + '/card'"
                    class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-border bg-card px-4 py-2 text-sm font-medium shadow-sm transition hover:border-primary"
                >
                    <IdCard class="size-4" />
                    Terug naar de kaart
                </Link>
            </div>

            <!-- Inzetkaart: wat het kind verdiende, en waar het beter in werd per cursus. -->
            <template v-if="inzet">
                <div class="mt-6 overflow-hidden rounded-2xl border border-primary/30 bg-card shadow-sm">
                    <div class="h-1 bg-primary"></div>
                    <div class="p-5">
                        <p class="text-xl font-bold leading-tight text-primary sm:text-2xl">
                            {{ card.effort?.points ? card.effort.points + ' punten verdiend dit seizoen' : 'Het seizoen is net begonnen' }}
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">Punten voor er zijn en voor inzet. Ze gaan nooit omlaag: wie traint, groeit.</p>

                        <div class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-3">
                            <div>
                                <p class="tabular flex items-center gap-1.5 text-2xl font-bold leading-none">
                                    <Zap class="size-5 text-muted-foreground" />
                                    {{ card.effort?.points ?? level.xp }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">inzetpunten</p>
                            </div>
                            <div>
                                <p class="tabular flex items-center gap-1.5 text-2xl font-bold leading-none">
                                    <CalendarCheck class="size-5 text-muted-foreground" />
                                    {{ card.effort?.trainings ?? 0 }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">trainingen aanwezig</p>
                            </div>
                            <div>
                                <p class="tabular flex items-center gap-1.5 text-2xl font-bold leading-none">
                                    <Flame class="size-5 text-gold" />
                                    {{ card.effort?.standouts ?? 0 }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">keer {{ (card.effort?.standout_label ?? 'uitblinker').toLowerCase() }}</p>
                            </div>
                        </div>

                        <div class="mt-5 rounded-xl border border-border bg-background p-4">
                            <div class="flex flex-wrap items-baseline justify-between gap-2">
                                <p class="text-sm font-medium">Level {{ level.label }}</p>
                                <p class="tabular text-xs text-muted-foreground">{{ level.xp }} XP</p>
                            </div>
                            <div class="mt-2 h-2 overflow-hidden rounded-full bg-secondary">
                                <div class="h-full rounded-full bg-primary transition-all" :style="{ width: level.progress + '%' }"></div>
                            </div>
                            <p class="mt-2 text-sm text-muted-foreground">
                                <template v-if="level.next">
                                    Nog <span class="font-semibold text-foreground">{{ level.next.remaining }} punten</span> tot {{ level.next.label }}. Elke
                                    training telt mee.
                                </template>
                                <template v-else>Het hoogste level bereikt.</template>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex flex-col gap-3 rounded-xl border border-border bg-card p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                    <p class="min-w-0 flex-1 text-sm text-muted-foreground">
                        De kaart van {{ player.first_name }} beloont inzet, niet talent. Waar {{ player.first_name }} beter in wordt, zie je hieronder per
                        cursus, in kleuren en zonder vergelijking met anderen.
                    </p>
                    <button
                        type="button"
                        class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 self-start rounded-lg border border-border px-3 py-2 text-sm font-medium transition hover:border-primary"
                        @click="uitlegOpen = true"
                    >
                        <CircleHelp class="size-4" />
                        Hoe werkt dit?
                    </button>
                </div>

                <h2 class="mt-6 font-semibold">Waar {{ player.first_name }} beter in wordt</h2>
                <div v-if="courses.length" class="mt-3 space-y-4">
                    <CourseProgressBlock v-for="c in courses" :key="c.product.id" :course="c" :levels="voortgang" :first-name="player.first_name" />
                </div>
                <p v-else class="mt-3 rounded-2xl border border-dashed border-border bg-card/50 p-6 text-center text-sm text-muted-foreground">
                    Aan het begin en het eind van een cursus legt de trainer per onderdeel vast waar {{ player.first_name }} staat. Dat verschijnt hier.
                </p>

                <div v-if="card.recent_trainings?.length" class="mt-4 rounded-xl border border-border bg-card p-5 shadow-sm">
                    <p class="font-medium">Laatste trainingen</p>
                    <ul class="mt-3 divide-y divide-border">
                        <li v-for="t in card.recent_trainings" :key="t.date + t.label" class="py-2.5 first:pt-0 last:pb-0">
                            <div class="flex items-baseline justify-between gap-3">
                                <p class="min-w-0 truncate text-sm font-medium">{{ t.label }}</p>
                                <p class="tabular shrink-0 text-sm font-bold text-primary">+{{ t.points }}</p>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                {{ t.date }}<template v-if="t.effort || t.attitude"> &middot; {{ [t.effort, t.attitude].filter(Boolean).join(', ') }}</template>
                            </p>
                            <p v-if="t.note" class="mt-1 text-sm text-muted-foreground">{{ t.note }}</p>
                        </li>
                    </ul>
                </div>
            </template>

            <template v-else-if="progress.hasEnoughData">
                <!-- De kop van het verhaal: wat er in drie maanden gebeurd is -->
                <div
                    class="mt-6 overflow-hidden rounded-2xl border bg-card shadow-sm"
                    :class="kop.toon === 'goed' ? 'border-primary/30' : kop.toon === 'aandacht' ? 'border-warning/30' : 'border-border'"
                >
                    <div class="h-1" :class="kop.toon === 'goed' ? 'bg-primary' : kop.toon === 'aandacht' ? 'bg-warning' : 'bg-border'"></div>

                    <div class="p-5">
                        <p
                            class="text-xl font-bold leading-tight sm:text-2xl"
                            :class="kop.toon === 'goed' ? 'text-primary' : kop.toon === 'aandacht' ? 'text-warning' : ''"
                        >
                            {{ kop.tekst }}
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">{{ kop.sub }}</p>

                        <!-- Twee op een rij op een telefoon: drie cijfers onder
                             elkaar kostte een half scherm voor drie getallen. -->
                        <div class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-3">
                            <div>
                                <p class="tabular flex items-center gap-1.5 text-2xl font-bold leading-none">
                                    <ClipboardList class="size-5 text-muted-foreground" />
                                    {{ quarter.reports }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">rapporten</p>
                            </div>
                            <div>
                                <p class="tabular flex items-center gap-1.5 text-2xl font-bold leading-none">
                                    <CalendarCheck class="size-5 text-muted-foreground" />
                                    {{ quarter.trainings }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">trainingen aanwezig</p>
                            </div>
                            <div class="min-w-0">
                                <p class="flex items-center gap-1.5 text-lg font-bold leading-none">
                                    <Trophy class="size-5 shrink-0 text-gold" />
                                    <span class="min-w-0 break-words">{{ quarter.best ?? '-' }}</span>
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">sterkste categorie</p>
                            </div>
                        </div>

                        <!-- Level: het stimuleringsdeel, met iets om naartoe te werken -->
                        <div class="mt-5 rounded-xl border border-border bg-background p-4">
                            <div class="flex flex-wrap items-baseline justify-between gap-2">
                                <p class="text-sm font-medium">Level {{ level.label }}</p>
                                <p class="tabular text-xs text-muted-foreground">{{ level.xp }} XP</p>
                            </div>

                            <div class="mt-2 h-2 overflow-hidden rounded-full bg-secondary">
                                <div class="h-full rounded-full bg-primary transition-all" :style="{ width: level.progress + '%' }"></div>
                            </div>

                            <p class="mt-2 text-sm text-muted-foreground">
                                <template v-if="level.next">
                                    Nog <span class="font-semibold text-foreground">{{ level.next.remaining }} XP</span> tot {{ level.next.label }}.
                                    Elke training en elk rapport telt mee.
                                </template>
                                <template v-else>Het hoogste level bereikt.</template>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Waar de cijfers over gaan. Positief: goed voor je leeftijd. -->
                <div
                    class="mt-4 flex flex-col gap-3 rounded-xl border border-border bg-card p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between"
                >
                    <p v-if="kleuren" class="min-w-0 flex-1 text-sm text-muted-foreground">
                        De kleuren laten zien hoe {{ player.first_name }} groeit, van werkpunt naar top. Het gaat om de eigen ontwikkeling, niet om
                        een vergelijking met anderen.
                    </p>
                    <p v-else class="min-w-0 flex-1 text-sm text-muted-foreground">
                        Deze cijfers gaan over hoe {{ player.first_name }} het doet vergeleken met andere spelers van
                        <span class="font-medium text-foreground">{{ card.age_category?.label ?? 'dezelfde leeftijd' }}</span
                        >. Een hoog cijfer betekent dus: goed voor deze leeftijd.
                    </p>

                    <button
                        type="button"
                        class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 self-start rounded-lg border border-border px-3 py-2 text-sm font-medium transition hover:border-primary"
                        @click="uitlegOpen = true"
                    >
                        <CircleHelp class="size-4" />
                        Hoe werkt dit?
                    </button>
                </div>

                <!-- Overall: één lijn, want dit is het hoofdverhaal -->
                <div class="mt-4 rounded-xl border border-border bg-card p-5 shadow-sm">
                    <div class="flex flex-wrap items-baseline justify-between gap-3">
                        <div>
                            <p class="font-medium">{{ kleuren ? 'Zo gaat het in het algemeen' : 'Overall rating' }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ kleuren ? 'De kleur per rapport' : 'Het gemiddelde per rapport' }}, over {{ progress.points.length }} rapporten
                            </p>
                        </div>
                        <p
                            v-if="progress.overall.trend"
                            class="flex items-center gap-1.5 text-sm font-semibold"
                            :class="trendKleur(progress.overall.trend)"
                        >
                            <component :is="trendIcoon(progress.overall.trend)" class="size-4" />
                            {{ progress.overall.trend.label }}
                        </p>
                    </div>

                    <!-- Begin en eind als getal: een lijn zonder cijfers laat je raden -->
                    <div class="mt-4 flex items-end justify-between gap-4">
                        <div>
                            <GradeChip :rating="progress.overall.first" size="lg">
                                <p class="tabular text-2xl font-bold leading-none text-muted-foreground">{{ progress.overall.first ?? '-' }}</p>
                            </GradeChip>
                            <p class="mt-1 text-xs text-muted-foreground">eerste rapport</p>
                        </div>
                        <p v-if="progress.overall.delta !== null && !kleuren" class="tabular text-sm font-semibold" :class="trendKleur(progress.overall.trend)">
                            {{ deltaTekst(progress.overall.delta) }}
                        </p>
                        <div class="text-right">
                            <GradeChip :rating="progress.overall.last" size="lg">
                                <p class="tabular text-3xl font-bold leading-none text-primary">{{ progress.overall.last ?? '-' }}</p>
                            </GradeChip>
                            <p class="mt-1 text-xs text-muted-foreground">nu</p>
                        </div>
                    </div>

                    <GradeChart v-if="kleuren" class="mt-4" :series="progress.overall.series" :labels="labels" />
                    <LineChart v-else class="mt-3" :series="progress.overall.series" :labels="labels" :height="200" />
                </div>

                <!-- Eén ding om aan te werken. Bewust één: een lijstje met zes
                     verbeterpunten leest als kritiek. -->
                <div v-if="nextStep" class="mt-4 rounded-xl border border-border bg-card p-5 shadow-sm">
                    <div class="flex items-start gap-3">
                        <span class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <Target class="size-5" />
                        </span>

                        <div class="min-w-0 flex-1">
                            <p class="font-medium">Volgend doel</p>

                            <p class="mt-1 text-sm">
                                <span class="font-semibold">{{ nextStep.label }}</span>
                                <template v-if="kleuren">
                                    <template v-if="nextStep.from !== null"> van <GradeChip :rating="nextStep.from" size="sm" /> </template>
                                    naar <GradeChip :rating="nextStep.to" size="sm" />
                                </template>
                                <template v-else>
                                    <template v-if="nextStep.from !== null"> van {{ nextStep.from }} </template>
                                    naar <span class="font-semibold text-primary">{{ nextStep.to }}</span>
                                </template>
                            </p>

                            <p v-if="nextStep.type === 'goal'" class="mt-1 text-sm text-muted-foreground">
                                <template v-if="nextStep.on_track">Op koers.</template>
                                <template v-else>Nog even doorzetten.</template>
                                <template v-if="nextStep.days_left"> Nog {{ nextStep.days_left }} dagen te gaan.</template>
                            </p>

                            <p v-else class="mt-1 text-sm text-muted-foreground">
                                Hier valt nu de meeste winst te halen<template v-if="nextStep.hint">: {{ nextStep.hint.toLowerCase() }}</template
                                >.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Per categorie: kleine meervouden in plaats van zes lijnen door elkaar -->
                <div class="mt-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="font-medium">Per categorie</p>
                            <p class="text-xs text-muted-foreground">
                                {{ kleuren ? 'Van werkpunt (onder) naar top (boven), één stip per rapport.' : 'Elke grafiek staat op dezelfde schaal van 0 tot 100.' }}
                            </p>
                        </div>

                        <button
                            type="button"
                            class="inline-flex min-h-11 items-center text-sm font-medium text-primary underline underline-offset-4"
                            @click="toonTabel = !toonTabel"
                        >
                            {{ toonTabel ? 'Toon grafieken' : 'Toon als tabel' }}
                        </button>
                    </div>

                    <div v-if="!toonTabel" class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="categorie in progress.categories"
                            :key="categorie.category"
                            class="rounded-xl border border-border bg-card p-4 shadow-sm"
                        >
                            <p class="text-sm font-medium">{{ categorie.label }}</p>

                            <p v-if="kleuren" class="mt-2 flex flex-wrap items-center gap-1.5 text-sm text-muted-foreground">
                                <GradeChip :rating="categorie.first" size="sm" />
                                <span aria-hidden="true">&rarr;</span>
                                <GradeChip :rating="categorie.last" size="sm" />
                            </p>
                            <div v-else class="mt-2 flex items-end justify-between gap-2">
                                <p class="tabular text-3xl font-bold leading-none" :class="trendKleur(categorie.trend)">
                                    {{ categorie.delta === null ? '-' : deltaTekst(categorie.delta) }}
                                </p>
                                <p class="tabular text-right text-sm text-muted-foreground">
                                    nu <span class="text-base font-semibold text-foreground">{{ categorie.last ?? '-' }}</span>
                                </p>
                            </div>

                            <p v-if="categorie.trend" class="mt-1 flex items-center gap-1 text-xs font-medium" :class="trendKleur(categorie.trend)">
                                <component :is="trendIcoon(categorie.trend)" class="size-3.5" />
                                {{ categorie.trend.label }}
                            </p>

                            <GradeChart v-if="kleuren" class="mt-3" :series="categorie.series" :labels="labels" compact />
                            <LineChart v-else class="mt-2" :series="categorie.series" :labels="labels" :height="86" :show-end-label="false" />
                        </div>
                    </div>

                    <!-- Tabelweergave: dezelfde cijfers, zonder kleur nodig -->
                    <div v-else class="mt-3 overflow-x-auto rounded-xl border border-border bg-card shadow-sm">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-border text-left">
                                    <th class="p-3 font-medium">Categorie</th>
                                    <th v-for="(label, i) in labels" :key="i" class="tabular whitespace-nowrap p-3 text-right font-medium">
                                        {{ label }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="categorie in progress.categories" :key="categorie.category" class="border-b border-border last:border-0">
                                    <td class="whitespace-nowrap p-3">{{ categorie.label }}</td>
                                    <td v-for="(waarde, i) in categorie.series" :key="i" class="tabular p-3 text-right">
                                        <GradeChip :rating="waarde" size="sm">{{ waarde ?? '-' }}</GradeChip>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>

            <div v-else class="mt-6 rounded-2xl border border-dashed border-border bg-card/50 p-10 text-center">
                <p class="font-medium">Nog te weinig rapporten</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    Vanaf het tweede rapport kunnen we groei laten zien. Nu zijn het er {{ progress.points.length }}.
                </p>
            </div>

            <div v-if="goals.length" class="mt-4 rounded-xl border border-border bg-card p-5 shadow-sm">
                <p class="font-medium">Doelen</p>
                <GoalList class="mt-3" :goals="goals" />
            </div>

            <!-- Tijdlijn: de momenten onder elkaar, met een lijn ertussen -->
            <div class="mt-4 rounded-xl border border-border bg-card p-5 shadow-sm">
                <p class="font-medium">Tijdlijn</p>

                <ol v-if="timeline.length" class="mt-4">
                    <li v-for="(item, index) in tijdlijnZichtbaar" :key="index" class="relative flex gap-3 pb-5 last:pb-0">
                        <!-- De verbindingslijn loopt door tot het volgende punt -->
                        <span
                            v-if="index < tijdlijnZichtbaar.length - 1"
                            class="absolute bottom-0 left-4 top-9 w-px -translate-x-1/2 bg-border"
                            aria-hidden="true"
                        ></span>

                        <span
                            class="relative flex size-8 shrink-0 items-center justify-center rounded-full ring-4 ring-card"
                            :class="item.type === 'rapport' ? 'bg-primary/10 text-primary' : 'bg-gold/15 text-gold'"
                        >
                            <component :is="tijdlijnIcoon(item.type)" class="size-4" />
                        </span>

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                                <p class="text-sm font-medium">{{ item.title }}</p>
                                <p class="tabular text-xs text-muted-foreground">{{ item.date }}</p>
                            </div>

                            <p v-if="item.value !== null" class="tabular mt-1 flex items-center gap-2 text-sm">
                                <span
                                    ><template v-if="kleuren">Kleur <GradeChip :rating="item.value" size="sm" /></template
                                    ><template v-else>Gemiddelde <span class="font-semibold">{{ item.value }}</span></template></span
                                >

                                <span
                                    v-if="!kleuren && item.delta !== null && item.delta !== 0"
                                    class="inline-flex items-center gap-0.5 rounded-md px-1.5 py-0.5 text-xs font-semibold"
                                    :class="item.delta > 0 ? 'bg-primary/10 text-primary' : 'bg-warning/10 text-warning'"
                                >
                                    <TrendingUp v-if="item.delta > 0" class="size-3" />
                                    <TrendingDown v-else class="size-3" />
                                    {{ deltaTekst(item.delta) }}
                                </span>
                            </p>

                            <p v-if="item.body" class="mt-1 text-sm text-muted-foreground">{{ item.body }}</p>
                        </div>
                    </li>
                </ol>

                <button
                    v-if="timeline.length > TIJDLIJN_KORT"
                    type="button"
                    class="mt-3 inline-flex min-h-11 w-full items-center justify-center rounded-lg border border-border text-sm font-medium transition hover:border-primary"
                    @click="tijdlijnUit = !tijdlijnUit"
                >
                    {{ tijdlijnUit ? 'Minder weergeven' : 'Meer weergeven (' + (timeline.length - TIJDLIJN_KORT) + ')' }}
                </button>

                <p v-else class="mt-3 text-sm text-muted-foreground">Er is nog niets gebeurd om te laten zien.</p>
            </div>

            <div v-if="canReport && !inzet" class="mt-4">
                <Link
                    :href="'/players/' + player.id + '/reports/create'"
                    class="inline-flex min-h-11 items-center rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                >
                    Nieuw rapport invullen
                </Link>
            </div>
        </div>

        <RatingExplanation v-model:open="uitlegOpen" :card="card" :audience="canReport ? 'trainer' : 'gezin'" />
    </AppLayout>
</template>
