<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, MapPin, Plus, UserCog } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Training {
    id: number;
    date: string;
    starts_at: string;
    ends_at: string;
    group: string;
    location: string | null;
    trainers: string[];
    has_passed: boolean;
}

const props = defineProps<{
    view: 'month' | 'week';
    date: string;
    today: string;
    range: { from: string; to: string };
    title: string;
    trainings: Training[];
    canManage: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Kalender', href: '/calendar' }];

// --- Datumhulpjes. Alles in lokale tijd, zonder tijdzone-verrassingen. ---

const parse = (iso: string) => {
    const [j, m, d] = iso.split('-').map(Number);

    return new Date(j, m - 1, d);
};

const iso = (d: Date) =>
    `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

const plusDagen = (d: Date, n: number) => {
    const kopie = new Date(d);
    kopie.setDate(kopie.getDate() + n);

    return kopie;
};

const dagNamen = ['ma', 'di', 'wo', 'do', 'vr', 'za', 'zo'];

const langeDag = new Intl.DateTimeFormat('nl-NL', { weekday: 'long', day: 'numeric', month: 'long' });
const korteDag = new Intl.DateTimeFormat('nl-NL', { weekday: 'short', day: 'numeric' });

// --- Het raster ---

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

// Op mobiel tik je een dag aan en zie je die eronder. Standaard: vandaag als
// die in beeld is, anders de eerste dag met een training, anders de eerste dag.
const geselecteerd = ref<string>(
    dagen.value.some((d) => d.iso === props.today)
        ? props.today
        : (props.trainings[0]?.date ?? props.range.from),
);

watch(
    () => props.date,
    () => {
        geselecteerd.value = dagen.value.some((d) => d.iso === props.today)
            ? props.today
            : (props.trainings[0]?.date ?? props.range.from);
    },
);

const geselecteerdeTrainingen = computed(() => perDag.value[geselecteerd.value] ?? []);

// --- Navigatie ---

const ga = (view: 'month' | 'week', date: string) => router.get('/calendar', { view, date }, { preserveScroll: true });

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

const open = (id: number) => router.get('/trainings/' + id);
</script>

<template>
    <Head title="Kalender" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-6xl p-3 sm:p-4">
            <!-- Kop: navigeren en schakelen, alles binnen duimbereik -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-1">
                    <button
                        type="button"
                        class="flex size-10 items-center justify-center rounded-lg border border-border bg-card shadow-sm transition hover:border-primary"
                        aria-label="Vorige"
                        @click="vorige"
                    >
                        <ChevronLeft class="size-5" />
                    </button>
                    <button
                        type="button"
                        class="h-10 rounded-lg border border-border bg-card px-3 text-sm font-medium shadow-sm transition hover:border-primary"
                        @click="naarVandaag"
                    >
                        Vandaag
                    </button>
                    <button
                        type="button"
                        class="flex size-10 items-center justify-center rounded-lg border border-border bg-card shadow-sm transition hover:border-primary"
                        aria-label="Volgende"
                        @click="volgende"
                    >
                        <ChevronRight class="size-5" />
                    </button>
                </div>

                <h1 class="order-first w-full text-xl font-semibold tracking-tight sm:order-none sm:w-auto sm:text-2xl">{{ title }}</h1>

                <div class="flex items-center gap-2">
                    <div class="inline-flex rounded-lg border border-border bg-card p-1 shadow-sm">
                        <button
                            type="button"
                            class="rounded-md px-3 py-1.5 text-sm font-medium transition"
                            :class="view === 'month' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'"
                            @click="wisselWeergave('month')"
                        >
                            Maand
                        </button>
                        <button
                            type="button"
                            class="rounded-md px-3 py-1.5 text-sm font-medium transition"
                            :class="view === 'week' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'"
                            @click="wisselWeergave('week')"
                        >
                            Week
                        </button>
                    </div>

                    <Link
                        v-if="canManage"
                        href="/trainings/create"
                        class="inline-flex h-10 items-center gap-2 rounded-lg bg-primary px-3 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                    >
                        <Plus class="size-4" />
                        <span class="hidden sm:inline">Inplannen</span>
                    </Link>
                </div>
            </div>

            <!-- ================= MAAND ================= -->
            <template v-if="view === 'month'">
                <div class="mt-4 overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                    <div class="grid grid-cols-7 border-b border-border bg-secondary/50 text-center text-xs font-medium text-muted-foreground">
                        <div v-for="naam in dagNamen" :key="naam" class="py-2">{{ naam }}</div>
                    </div>

                    <div class="grid grid-cols-7">
                        <button
                            v-for="(dag, index) in dagen"
                            :key="dag.iso"
                            type="button"
                            class="flex min-h-[3.25rem] flex-col items-stretch border-b border-r border-border p-1 text-left transition sm:min-h-[6.5rem] sm:p-1.5"
                            :class="[
                                index % 7 === 6 ? 'border-r-0' : '',
                                dag.inMaand ? 'bg-card' : 'bg-secondary/30 text-muted-foreground/60',
                                geselecteerd === dag.iso ? 'ring-2 ring-inset ring-primary sm:ring-0' : '',
                            ]"
                            @click="geselecteerd = dag.iso"
                        >
                            <span
                                class="tabular mb-1 flex size-6 items-center justify-center self-start rounded-full text-xs font-medium"
                                :class="dag.isVandaag ? 'bg-primary text-primary-foreground' : ''"
                            >
                                {{ dag.dag }}
                            </span>

                            <!-- Mobiel: stippen. Desktop: chips die je direct kunt openen. -->
                            <div v-if="perDag[dag.iso]?.length" class="flex flex-wrap gap-1 sm:hidden">
                                <span
                                    v-for="t in perDag[dag.iso].slice(0, 3)"
                                    :key="t.id"
                                    class="size-1.5 rounded-full"
                                    :class="t.has_passed ? 'bg-muted-foreground/50' : 'bg-primary'"
                                ></span>
                            </div>

                            <div class="hidden space-y-1 sm:block">
                                <span
                                    v-for="t in perDag[dag.iso]?.slice(0, 3) ?? []"
                                    :key="t.id"
                                    role="link"
                                    class="block cursor-pointer truncate rounded-md px-1.5 py-0.5 text-[11px] leading-tight transition hover:opacity-80"
                                    :class="t.has_passed ? 'bg-secondary text-muted-foreground' : 'bg-primary/10 text-primary'"
                                    :title="t.group + ' · ' + (t.trainers.join(', ') || 'geen trainer') + (t.location ? ' · ' + t.location : '')"
                                    @click.stop="open(t.id)"
                                >
                                    <span class="tabular font-semibold">{{ t.starts_at }}</span> {{ t.group }}
                                </span>
                                <span v-if="(perDag[dag.iso]?.length ?? 0) > 3" class="block px-1.5 text-[11px] text-muted-foreground">
                                    +{{ perDag[dag.iso].length - 3 }} meer
                                </span>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Mobiel: de aangetikte dag uitgeschreven -->
                <div class="mt-4 sm:hidden">
                    <p class="text-sm font-medium first-letter:uppercase">{{ langeDag.format(parse(geselecteerd)) }}</p>

                    <div v-if="geselecteerdeTrainingen.length" class="mt-2 space-y-2">
                        <Link
                            v-for="t in geselecteerdeTrainingen"
                            :key="t.id"
                            :href="'/trainings/' + t.id"
                            class="block rounded-xl border border-border bg-card p-3 shadow-sm transition hover:border-primary"
                        >
                            <div class="flex items-baseline justify-between gap-2">
                                <p class="font-medium">{{ t.group }}</p>
                                <p class="tabular shrink-0 text-sm text-muted-foreground">{{ t.starts_at }} – {{ t.ends_at }}</p>
                            </div>
                            <p v-if="t.trainers.length" class="mt-1 flex items-center gap-1.5 text-xs text-muted-foreground">
                                <UserCog class="size-3.5 shrink-0" />
                                {{ t.trainers.join(', ') }}
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
            </template>

            <!-- ================= WEEK ================= -->
            <template v-else>
                <!-- Desktop: zeven kolommen. Mobiel: dagen onder elkaar als agenda. -->
                <div class="mt-4 grid gap-2 sm:grid-cols-7">
                    <div
                        v-for="dag in dagen"
                        :key="dag.iso"
                        class="rounded-xl border border-border bg-card shadow-sm"
                        :class="dag.isVandaag ? 'border-primary/50' : ''"
                    >
                        <div class="flex items-center justify-between border-b border-border px-3 py-2 sm:flex-col sm:items-start sm:gap-0.5">
                            <p class="text-sm font-medium first-letter:uppercase">{{ korteDag.format(dag.datum) }}</p>
                            <span
                                v-if="dag.isVandaag"
                                class="rounded-full bg-primary px-2 py-0.5 text-[10px] font-semibold text-primary-foreground"
                            >
                                vandaag
                            </span>
                        </div>

                        <div class="space-y-2 p-2">
                            <Link
                                v-for="t in perDag[dag.iso] ?? []"
                                :key="t.id"
                                :href="'/trainings/' + t.id"
                                class="block rounded-lg border p-2.5 transition hover:border-primary"
                                :class="t.has_passed ? 'border-border bg-secondary/40' : 'border-primary/30 bg-primary/5'"
                            >
                                <p class="tabular text-xs font-semibold" :class="t.has_passed ? 'text-muted-foreground' : 'text-primary'">
                                    {{ t.starts_at }} – {{ t.ends_at }}
                                </p>
                                <p class="mt-0.5 text-sm font-medium leading-tight">{{ t.group }}</p>
                                <p v-if="t.trainers.length" class="mt-1 flex items-start gap-1 text-xs text-muted-foreground">
                                    <UserCog class="mt-0.5 size-3 shrink-0" />
                                    <span>{{ t.trainers.join(', ') }}</span>
                                </p>
                                <p v-if="t.location" class="mt-0.5 flex items-start gap-1 text-xs text-muted-foreground">
                                    <MapPin class="mt-0.5 size-3 shrink-0" />
                                    <span>{{ t.location }}</span>
                                </p>
                            </Link>

                            <p v-if="!perDag[dag.iso]?.length" class="px-1 py-2 text-center text-xs text-muted-foreground/70 sm:py-6">—</p>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
