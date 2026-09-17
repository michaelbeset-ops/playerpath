<script setup lang="ts">
import FilterSheet from '@/components/FilterSheet.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { CalendarDays, ChevronLeft, ChevronRight, LayoutGrid, List, MapPin, Plus, UserCog } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';

interface Training {
    id: number;
    date: string;
    starts_at: string;
    ends_at: string;
    group: string;
    location: string | null;
    trainers: string[];
    has_passed: boolean;
    cancelled: boolean;
    is_mine: boolean;
    /** Ouder: hier kan een van je kinderen nog op inschrijven. */
    enrollable?: boolean;
    price?: string | null;
    is_full?: boolean;
}

const props = defineProps<{
    view: 'month' | 'week';
    scope: 'all' | 'mine';
    canChooseScope: boolean;
    date: string;
    today: string;
    range: { from: string; to: string };
    title: string;
    trainings: Training[];
    canManage: boolean;
    /** Groep, trainer en locatie; alleen gevuld voor wie het hele rooster ziet. */
    filters: { group: number | null; trainer: number | null; location: number | null };
    groups: { id: number; name: string }[];
    trainers: { id: number; name: string }[];
    locations: { id: number; name: string }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Kalender', href: '/calendar' }];

// --- Datumhulpjes. Alles in lokale tijd, zonder tijdzone-verrassingen. ---

const parse = (iso: string) => {
    const [j, m, d] = iso.split('-').map(Number);

    return new Date(j, m - 1, d);
};

const iso = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

const plusDagen = (d: Date, n: number) => {
    const kopie = new Date(d);
    kopie.setDate(kopie.getDate() + n);

    return kopie;
};

const dagNamen = ['ma', 'di', 'wo', 'do', 'vr', 'za', 'zo'];

const langeDag = new Intl.DateTimeFormat('nl-NL', { weekday: 'long', day: 'numeric', month: 'long' });
const korteDag = new Intl.DateTimeFormat('nl-NL', { weekday: 'short', day: 'numeric' });

// --- Lijst of raster ---
//
// Dit gaat over hoe dezelfde periode getekend wordt, niet over welke gegevens
// er zijn. Een raster van zeven kolommen op 375 pixels is geen raster, dus op
// een telefoon is de lijst de standaard en het raster de optie. Die keuze hoort
// bij het scherm waarop je kijkt en staat daarom in de browser, niet op de
// server: op je laptop wil je iets anders dan op je telefoon.

const smal = ref(typeof window !== 'undefined' && window.matchMedia('(max-width: 639px)').matches);

let media: MediaQueryList | null = null;
const opBreedte = (e: MediaQueryListEvent) => (smal.value = e.matches);

onMounted(() => {
    media = window.matchMedia('(max-width: 639px)');
    media.addEventListener('change', opBreedte);
});

onBeforeUnmount(() => media?.removeEventListener('change', opBreedte));

// Lezen kan net zo goed stuklopen als schrijven (privévenster, geblokkeerde opslag).
const leesBewaard = (): string | null => {
    try {
        return typeof localStorage !== 'undefined' ? localStorage.getItem('pp.calendar.layout') : null;
    } catch {
        return null;
    }
};
const bewaard = leesBewaard();
const weergave = ref<'lijst' | 'raster'>(bewaard === 'raster' ? 'raster' : 'lijst');

const kiesWeergave = (keuze: 'lijst' | 'raster') => {
    weergave.value = keuze;

    try {
        localStorage.setItem('pp.calendar.layout', keuze);
    } catch {
        // Een browser die niets mag bewaren hoort het scherm niet te slopen.
    }
};

// Op een breed scherm is het raster het beeld van de maand; smal wint de keuze.
const toonRaster = computed(() => props.view === 'month' && (!smal.value || weergave.value === 'raster'));

// --- De gegevens per dag ---

// Trainingen per dag, zodat een cel niet steeds de hele lijst hoeft te filteren.
const perDag = computed(() => {
    const map: Record<string, Training[]> = {};

    for (const t of props.trainings) {
        (map[t.date] ??= []).push(t);
    }

    return map;
});

const dagen = computed(() => {
    const van = parse(props.range.from);
    const tot = parse(props.range.to);
    const maand = parse(props.date).getMonth();
    const lijst: { iso: string; dag: number; inMaand: boolean; isVandaag: boolean; datum: Date }[] = [];

    for (let d = van; d <= tot; d = plusDagen(d, 1)) {
        lijst.push({
            iso: iso(d),
            dag: d.getDate(),
            inMaand: d.getMonth() === maand,
            isVandaag: iso(d) === props.today,
            datum: d,
        });
    }

    return lijst;
});

// De agenda toont alleen dagen waar iets staat: bladeren door lege dagen is
// bladeren door niets.
const agenda = computed(() => dagen.value.filter((d) => perDag.value[d.iso]?.length));

// In het raster tik je een dag aan en zie je die eronder. Standaard: vandaag als
// die in beeld is, anders de eerste dag met een training, anders de eerste dag.
const geselecteerd = ref<string>(dagen.value.some((d) => d.iso === props.today) ? props.today : (props.trainings[0]?.date ?? props.range.from));

watch(
    () => props.date,
    () => {
        geselecteerd.value = dagen.value.some((d) => d.iso === props.today) ? props.today : (props.trainings[0]?.date ?? props.range.from);
    },
);

const geselecteerdeTrainingen = computed(() => perDag.value[geselecteerd.value] ?? []);

// --- Navigatie ---

// Groep, trainer en locatie reizen met elke navigatie mee; anders ben je je
// filter kwijt zodra je een maand verder bladert.
const filters = reactive({ ...props.filters });

const ga = (view: 'month' | 'week', date: string, scope?: 'all' | 'mine') =>
    router.get(
        '/calendar',
        {
            view,
            date,
            scope: scope ?? props.scope,
            group: filters.group ?? '',
            trainer: filters.trainer ?? '',
            location: filters.location ?? '',
        },
        // De staat blijft, zodat het filterpaneel op een telefoon open blijft
        // terwijl de trainingen eronder verversen.
        { preserveScroll: true, preserveState: true },
    );

watch(filters, () => ga(props.view, props.date));

// Wat er in het bolletje op de filterknop staat: alles wat afwijkt van
// "alle trainingen". De weergave telt niet mee; dat is geen filter.
const actieveFilters = computed(
    () => (props.canChooseScope && props.scope === 'mine' ? 1 : 0) + [filters.group, filters.trainer, filters.location].filter(Boolean).length,
);

// Op een telefoon is de weergave één keuze uit drie: lijst, maandraster of week.
const modus = computed<'lijst' | 'raster' | 'week'>(() => (props.view === 'week' ? 'week' : weergave.value));

const kiesModus = (keuze: 'lijst' | 'raster' | 'week') => {
    if (keuze === 'week') {
        wisselWeergave('week');
        return;
    }

    kiesWeergave(keuze);

    if (props.view !== 'month') {
        wisselWeergave('month');
    }
};

const keuzeKlasse = 'min-h-11 w-full rounded-lg border border-input bg-card px-3 text-sm outline-none focus:border-primary';

const vorige = () => {
    const d = parse(props.date);

    if (props.view === 'week') {
        ga('week', iso(plusDagen(d, -7)));
    } else {
        ga('month', iso(new Date(d.getFullYear(), d.getMonth() - 1, 1)));
    }
};

const volgende = () => {
    const d = parse(props.date);

    if (props.view === 'week') {
        ga('week', iso(plusDagen(d, 7)));
    } else {
        ga('month', iso(new Date(d.getFullYear(), d.getMonth() + 1, 1)));
    }
};

const naarVandaag = () => ga(props.view, props.today);

const wisselWeergave = (view: 'month' | 'week') => ga(view, props.date);

const kiesBereik = (scope: 'all' | 'mine') => ga(props.view, props.date, scope);

// Een inschrijfbare training opent meteen de inschrijving.
const hrefVoor = (t: Training) => (t.enrollable ? '/trainings/' + t.id + '/inschrijven' : '/trainings/' + t.id);

const leegTekst = computed(() => (props.scope === 'mine' ? 'Geen trainingen van jou in deze periode.' : 'Geen trainingen in deze periode.'));
</script>

<template>
    <Head title="Kalender" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-6xl p-3 sm:p-4" data-tour="calendar">
            <!-- Kop. Op een telefoon: de titel met de filterknop en het plusje
                 op één regel, en daaronder bladeren. Vier losse knoppenrijen
                 onder elkaar was te rommelig; alles wat kiest zit nu achter één
                 knop met een teller. Op een groot scherm staat het gewoon in
                 beeld. -->
            <div class="flex flex-wrap items-center gap-3">
                <div class="order-2 flex w-full min-w-0 items-center gap-1 sm:order-1 sm:w-auto">
                    <button
                        type="button"
                        class="flex size-11 items-center justify-center rounded-lg border border-border bg-card shadow-sm transition hover:border-primary"
                        aria-label="Vorige"
                        @click="vorige"
                    >
                        <ChevronLeft class="size-5" />
                    </button>
                    <button
                        type="button"
                        class="h-11 rounded-lg border border-border bg-card px-3 text-sm font-medium shadow-sm transition hover:border-primary"
                        @click="naarVandaag"
                    >
                        Vandaag
                    </button>
                    <button
                        type="button"
                        class="flex size-11 items-center justify-center rounded-lg border border-border bg-card shadow-sm transition hover:border-primary"
                        aria-label="Volgende"
                        @click="volgende"
                    >
                        <ChevronRight class="size-5" />
                    </button>
                </div>

                <h1 class="order-1 min-w-0 flex-1 text-xl font-semibold tracking-tight sm:order-2 sm:flex-none sm:text-2xl">
                    {{ title }}
                </h1>

                <div class="order-1 flex items-center gap-2 sm:order-3 sm:ml-auto">
                    <div class="hidden rounded-lg border border-border bg-card p-1 shadow-sm sm:inline-flex">
                        <button
                            type="button"
                            class="min-h-11 rounded-md px-3 text-sm font-medium transition"
                            :class="view === 'month' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'"
                            @click="wisselWeergave('month')"
                        >
                            Maand
                        </button>
                        <button
                            type="button"
                            class="min-h-11 rounded-md px-3 text-sm font-medium transition"
                            :class="view === 'week' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'"
                            @click="wisselWeergave('week')"
                        >
                            Week
                        </button>
                    </div>

                    <FilterSheet :count="actieveFilters" title="Agenda" :inline="false">
                        <template #mobile>
                            <div>
                                <p class="text-xs font-medium text-muted-foreground">Weergave</p>
                                <div class="mt-1.5 grid grid-cols-3 rounded-lg border border-border bg-card p-1 shadow-sm">
                                    <button
                                        v-for="keuze in [
                                            { key: 'lijst', label: 'Lijst', icon: List },
                                            { key: 'raster', label: 'Maand', icon: LayoutGrid },
                                            { key: 'week', label: 'Week', icon: CalendarDays },
                                        ]"
                                        :key="keuze.key"
                                        type="button"
                                        class="flex min-h-11 items-center justify-center gap-1.5 rounded-md text-sm font-medium transition"
                                        :class="modus === keuze.key ? 'bg-primary text-primary-foreground' : 'text-muted-foreground'"
                                        @click="kiesModus(keuze.key as 'lijst' | 'raster' | 'week')"
                                    >
                                        <component :is="keuze.icon" class="size-4" />
                                        {{ keuze.label }}
                                    </button>
                                </div>
                            </div>
                        </template>

                        <div v-if="canChooseScope">
                            <p class="text-xs font-medium text-muted-foreground">Wiens trainingen</p>
                            <div class="mt-1.5 grid grid-cols-2 rounded-lg border border-border bg-card p-1 shadow-sm">
                                <button
                                    type="button"
                                    class="min-h-11 rounded-md text-sm font-medium transition"
                                    :class="scope === 'all' ? 'bg-secondary text-foreground' : 'text-muted-foreground'"
                                    @click="kiesBereik('all')"
                                >
                                    Alle trainingen
                                </button>
                                <button
                                    type="button"
                                    class="min-h-11 rounded-md text-sm font-medium transition"
                                    :class="scope === 'mine' ? 'bg-secondary text-foreground' : 'text-muted-foreground'"
                                    @click="kiesBereik('mine')"
                                >
                                    Mijn trainingen
                                </button>
                            </div>
                        </div>

                        <template v-if="canChooseScope">
                            <label class="block">
                                <span class="text-xs font-medium text-muted-foreground">Groep</span>
                                <select v-model="filters.group" :class="keuzeKlasse" class="mt-1.5">
                                    <option :value="null">Alle groepen</option>
                                    <option v-for="groep in groups" :key="groep.id" :value="groep.id">{{ groep.name }}</option>
                                </select>
                            </label>
                            <label class="block">
                                <span class="text-xs font-medium text-muted-foreground">Trainer</span>
                                <select v-model="filters.trainer" :class="keuzeKlasse" class="mt-1.5">
                                    <option :value="null">Alle trainers</option>
                                    <option v-for="trainer in trainers" :key="trainer.id" :value="trainer.id">{{ trainer.name }}</option>
                                </select>
                            </label>
                            <label class="block">
                                <span class="text-xs font-medium text-muted-foreground">Locatie</span>
                                <select v-model="filters.location" :class="keuzeKlasse" class="mt-1.5">
                                    <option :value="null">Alle locaties</option>
                                    <option v-for="locatie in locations" :key="locatie.id" :value="locatie.id">{{ locatie.name }}</option>
                                </select>
                            </label>
                        </template>
                    </FilterSheet>

                    <Link
                        v-if="canManage"
                        href="/trainings/create"
                        class="inline-flex h-11 items-center gap-2 rounded-lg bg-primary px-3 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                    >
                        <Plus class="size-4" />
                        <span class="hidden sm:inline">Inplannen</span>
                    </Link>
                </div>
            </div>

            <!-- Groot scherm: wiens trainingen, en de filters, gewoon in beeld. -->
            <div v-if="canChooseScope" class="mt-3 hidden flex-wrap items-center gap-2 sm:flex">
                <div class="inline-flex rounded-lg border border-border bg-card p-1 shadow-sm">
                    <button
                        type="button"
                        class="min-h-11 rounded-md px-3 text-sm font-medium transition"
                        :class="scope === 'all' ? 'bg-secondary text-foreground' : 'text-muted-foreground hover:text-foreground'"
                        @click="kiesBereik('all')"
                    >
                        Alle trainingen
                    </button>
                    <button
                        type="button"
                        class="min-h-11 rounded-md px-3 text-sm font-medium transition"
                        :class="scope === 'mine' ? 'bg-secondary text-foreground' : 'text-muted-foreground hover:text-foreground'"
                        @click="kiesBereik('mine')"
                    >
                        Mijn trainingen
                    </button>
                </div>

                <select v-model="filters.group" :class="keuzeKlasse" class="!w-auto min-w-36" aria-label="Groep">
                    <option :value="null">Alle groepen</option>
                    <option v-for="groep in groups" :key="groep.id" :value="groep.id">{{ groep.name }}</option>
                </select>
                <select v-model="filters.trainer" :class="keuzeKlasse" class="!w-auto min-w-36" aria-label="Trainer">
                    <option :value="null">Alle trainers</option>
                    <option v-for="trainer in trainers" :key="trainer.id" :value="trainer.id">{{ trainer.name }}</option>
                </select>
                <select v-model="filters.location" :class="keuzeKlasse" class="!w-auto min-w-36" aria-label="Locatie">
                    <option :value="null">Alle locaties</option>
                    <option v-for="locatie in locations" :key="locatie.id" :value="locatie.id">{{ locatie.name }}</option>
                </select>
            </div>

            <!-- ================= MAANDRASTER ================= -->
            <div v-if="toonRaster" class="mt-4 overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <div class="grid grid-cols-7 border-b border-border text-center text-xs font-medium text-muted-foreground">
                    <div v-for="naam in dagNamen" :key="naam" class="py-2">{{ naam }}</div>
                </div>

                <div class="grid grid-cols-7">
                    <button
                        v-for="(dag, index) in dagen"
                        :key="dag.iso"
                        type="button"
                        class="flex min-h-[3.25rem] min-w-0 flex-col items-stretch border-b border-r border-border/60 p-1 text-left transition sm:min-h-[7rem] sm:p-1.5"
                        :class="[
                            index % 7 === 6 ? 'border-r-0' : '',
                            dag.inMaand ? '' : 'text-muted-foreground/50',
                            dag.isVandaag ? 'bg-primary/5 ring-1 ring-inset ring-primary/40' : '',
                            geselecteerd === dag.iso && !dag.isVandaag ? 'ring-2 ring-inset ring-primary/60 sm:ring-0' : '',
                        ]"
                        @click="geselecteerd = dag.iso"
                    >
                        <span class="mb-1 flex items-center gap-1 self-start">
                            <span
                                class="tabular flex size-6 items-center justify-center rounded-full text-xs font-semibold"
                                :class="dag.isVandaag ? 'bg-primary text-primary-foreground' : ''"
                            >
                                {{ dag.dag }}
                            </span>
                            <span v-if="dag.isVandaag" class="hidden text-[11px] font-semibold uppercase tracking-wide text-primary sm:inline">
                                vandaag
                            </span>
                        </span>

                        <!-- Smal: stippen. Breed: de training uitgeschreven. -->
                        <div v-if="perDag[dag.iso]?.length" class="flex flex-wrap gap-1 sm:hidden">
                            <span
                                v-for="t in perDag[dag.iso].slice(0, 3)"
                                :key="t.id"
                                class="size-1.5 rounded-full"
                                :class="t.enrollable ? 'border border-primary bg-transparent' : t.has_passed ? 'bg-muted-foreground/40' : 'bg-primary'"
                            ></span>
                        </div>

                        <div class="hidden min-w-0 space-y-1 sm:block">
                            <span
                                v-for="t in perDag[dag.iso]?.slice(0, 2) ?? []"
                                :key="t.id"
                                role="link"
                                class="block min-w-0 cursor-pointer border-l-2 pl-1.5 text-[11px] leading-tight transition hover:opacity-70"
                                :class="t.has_passed || t.cancelled ? 'border-border text-muted-foreground' : 'border-primary'"
                                @click.stop="router.get(hrefVoor(t))"
                            >
                                <span class="block truncate">
                                    <span class="tabular font-semibold">{{ t.starts_at }}</span>
                                    <span :class="t.cancelled ? 'line-through' : ''">{{ ' ' + t.group }}</span>
                                    <span v-if="t.is_mine && scope === 'all'" class="font-semibold text-primary"> · jij</span>
                                </span>
                                <span class="block truncate text-muted-foreground">
                                    {{ [t.trainers.join(', '), t.location].filter(Boolean).join(' · ') || 'geen trainer' }}
                                </span>
                            </span>
                            <!-- De rest van die dag staat in de weekweergave. -->
                            <span
                                v-if="(perDag[dag.iso]?.length ?? 0) > 2"
                                role="link"
                                tabindex="0"
                                class="block cursor-pointer pl-1.5 text-[11px] font-medium text-primary underline underline-offset-2 hover:opacity-70"
                                @click.stop="ga('week', dag.iso)"
                                @keydown.enter.stop.prevent="ga('week', dag.iso)"
                            >
                                +{{ perDag[dag.iso].length - 2 }} meer
                            </span>
                        </div>
                    </button>
                </div>
            </div>

            <!-- Bij het raster op een telefoon: de aangetikte dag uitgeschreven -->
            <div v-if="toonRaster" class="mt-4 sm:hidden">
                <p class="text-sm font-medium first-letter:uppercase">{{ langeDag.format(parse(geselecteerd)) }}</p>

                <div v-if="geselecteerdeTrainingen.length" class="mt-2 space-y-2">
                    <Link
                        v-for="t in geselecteerdeTrainingen"
                        :key="t.id"
                        :href="hrefVoor(t)"
                        class="block rounded-xl border border-border bg-card p-3 shadow-sm transition hover:border-primary"
                    >
                        <div class="flex items-baseline justify-between gap-2">
                            <p class="min-w-0 font-medium" :class="t.cancelled ? 'line-through' : ''">{{ t.group }}</p>
                            <p class="tabular shrink-0 text-sm text-muted-foreground">{{ t.starts_at }} – {{ t.ends_at }}</p>
                        </div>
                        <p class="mt-1 flex items-center gap-1.5 text-xs text-muted-foreground">
                            <UserCog class="size-3.5 shrink-0" />
                            {{ t.trainers.join(', ') || 'geen trainer' }}
                        </p>
                        <p v-if="t.location" class="mt-0.5 flex items-center gap-1.5 text-xs text-muted-foreground">
                            <MapPin class="size-3.5 shrink-0" />
                            {{ t.location }}
                        </p>
                    </Link>
                </div>

                <p v-else class="mt-2 rounded-xl border border-dashed border-border bg-card/50 p-4 text-center text-sm text-muted-foreground">
                    Geen training op deze dag.
                </p>
            </div>

            <!-- ================= WEEK: kolommen op een breed scherm ================= -->
            <div v-if="view === 'week'" class="mt-4 hidden gap-2 sm:grid sm:grid-cols-7">
                <div
                    v-for="dag in dagen"
                    :key="dag.iso"
                    class="min-w-0 rounded-xl border bg-card shadow-sm"
                    :class="dag.isVandaag ? 'border-primary/50 bg-primary/5' : 'border-border'"
                >
                    <div class="flex flex-col items-start gap-0.5 border-b border-border px-3 py-2">
                        <p class="text-sm font-medium first-letter:uppercase">{{ korteDag.format(dag.datum) }}</p>
                        <span v-if="dag.isVandaag" class="rounded-full bg-primary px-2 py-0.5 text-[10px] font-semibold text-primary-foreground">
                            vandaag
                        </span>
                    </div>

                    <div class="space-y-2 p-2">
                        <Link
                            v-for="t in perDag[dag.iso] ?? []"
                            :key="t.id"
                            :href="hrefVoor(t)"
                            class="block min-w-0 rounded-lg border p-2.5 transition hover:border-primary"
                            :class="t.has_passed || t.cancelled ? 'border-border bg-secondary/40' : 'border-primary/30 bg-primary/5'"
                        >
                            <p class="tabular text-xs font-semibold" :class="t.has_passed || t.cancelled ? 'text-muted-foreground' : 'text-primary'">
                                {{ t.starts_at }} – {{ t.ends_at }}
                            </p>
                            <p class="mt-0.5 text-sm font-medium leading-tight" :class="t.cancelled ? 'line-through' : ''">{{ t.group }}</p>
                            <p v-if="t.is_mine && scope === 'all'" class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-primary">
                                jouw training
                            </p>
                            <p v-if="t.cancelled" class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">afgezegd</p>
                            <p v-if="t.enrollable" class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-primary">
                                {{ t.is_full ? 'wachtlijst' : 'inschrijven' }}<template v-if="t.price"> · {{ t.price }}</template>
                            </p>
                            <p class="mt-1 flex items-start gap-1 text-xs text-muted-foreground">
                                <UserCog class="mt-0.5 size-3 shrink-0" />
                                <span>{{ t.trainers.join(', ') || 'geen trainer' }}</span>
                            </p>
                            <p v-if="t.location" class="mt-0.5 flex items-start gap-1 text-xs text-muted-foreground">
                                <MapPin class="mt-0.5 size-3 shrink-0" />
                                <span>{{ t.location }}</span>
                            </p>
                        </Link>

                        <p v-if="!perDag[dag.iso]?.length" class="px-1 py-6 text-center text-xs text-muted-foreground/70">-</p>
                    </div>
                </div>
            </div>

            <!-- ================= LIJST ================= -->
            <!-- De maand als agenda (standaard op een telefoon) en de week op elk smal scherm. -->
            <div v-if="!toonRaster" class="mt-4 space-y-4" :class="view === 'week' ? 'sm:hidden' : ''">
                <section v-for="dag in agenda" :key="dag.iso">
                    <div class="flex items-center gap-2">
                        <p class="text-sm font-semibold first-letter:uppercase" :class="dag.isVandaag ? 'text-primary' : ''">
                            {{ langeDag.format(dag.datum) }}
                        </p>
                        <span v-if="dag.isVandaag" class="rounded-full bg-primary px-2 py-0.5 text-[10px] font-semibold text-primary-foreground">
                            vandaag
                        </span>
                    </div>

                    <div class="mt-2 space-y-2">
                        <Link
                            v-for="t in perDag[dag.iso] ?? []"
                            :key="t.id"
                            :href="hrefVoor(t)"
                            class="flex min-w-0 gap-3 rounded-xl border bg-card p-3 shadow-sm transition hover:border-primary"
                            :class="
                                t.enrollable ? 'border-dashed border-primary/60' : t.is_mine && scope === 'all' ? 'border-primary/40' : 'border-border'
                            "
                        >
                            <span
                                class="tabular w-14 shrink-0 text-sm font-semibold"
                                :class="t.has_passed || t.cancelled ? 'text-muted-foreground' : 'text-primary'"
                            >
                                {{ t.starts_at }}
                                <span class="block text-xs font-normal text-muted-foreground">{{ t.ends_at }}</span>
                            </span>

                            <span class="min-w-0 flex-1">
                                <span class="flex flex-wrap items-center gap-2">
                                    <span class="font-medium" :class="t.cancelled ? 'line-through' : ''">{{ t.group }}</span>
                                    <span
                                        v-if="t.is_mine && scope === 'all'"
                                        class="rounded-full bg-primary/10 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-primary"
                                    >
                                        jij
                                    </span>
                                    <span
                                        v-if="t.cancelled"
                                        class="rounded-full bg-secondary px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground"
                                    >
                                        afgezegd
                                    </span>
                                    <span
                                        v-if="t.enrollable"
                                        class="rounded-full border border-dashed border-primary/60 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-primary"
                                    >
                                        {{ t.is_full ? 'wachtlijst' : 'inschrijven' }}<template v-if="t.price"> · {{ t.price }}</template>
                                    </span>
                                </span>
                                <span class="mt-1 flex items-start gap-1.5 text-xs text-muted-foreground">
                                    <UserCog class="mt-0.5 size-3.5 shrink-0" />
                                    <span>{{ t.trainers.join(', ') || 'geen trainer' }}</span>
                                </span>
                                <span v-if="t.location" class="mt-0.5 flex items-start gap-1.5 text-xs text-muted-foreground">
                                    <MapPin class="mt-0.5 size-3.5 shrink-0" />
                                    <span>{{ t.location }}</span>
                                </span>
                            </span>
                        </Link>
                    </div>
                </section>

                <p
                    v-if="!agenda.length"
                    class="flex flex-col items-center gap-2 rounded-xl border border-dashed border-border bg-card/50 p-8 text-center text-sm text-muted-foreground"
                >
                    <CalendarDays class="size-6 opacity-60" />
                    {{ leegTekst }}
                </p>
            </div>
        </div>
    </AppLayout>
</template>
