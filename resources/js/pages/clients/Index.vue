<script setup lang="ts">
import { kleurVan, useGrading } from '@/lib/grade';
import Avatar from '@/components/Avatar.vue';
import FilterSheet from '@/components/FilterSheet.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import DemoBadge from '@/components/onboarding/DemoBadge.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronDown, KeyRound, Mail, Plus, Search, Users } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';

interface OuderRij {
    id: number;
    name: string;
    photo: string | null;
    email: string;
    relationship: string | null;
}

interface SpelerRij {
    id: number;
    name: string;
    photo: string | null;
    position: string;
    age: number | null;
    is_active: boolean;
    is_demo?: boolean;
    overall_rating: number | null;
    days_since_report: number | null;
    groups: string[];
    has_login: boolean;
    email: string | null;
    has_overdue_payment: boolean;
    guardians: OuderRij[];
}

const props = defineProps<{
    counts: { players: number; guardians: number };
    can: { managePlayers: boolean };
    players: SpelerRij[];
    filters: { search: string; position: string; group: number | null; status: string };
    positions: Record<string, string>;
    groups: { id: number; name: string }[];
    /** Trainer zonder eigenaarsrol: dan heet dit Spelers en staan de ouders er niet bij. */
    isTrainer: boolean;
    staleAfterDays: number;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: props.isTrainer ? 'Spelers' : 'Klanten', href: '/clients' }];

// Recent beoordeeld is groen, langer dan dertig dagen (of nooit) oranje.
const beoordeling = (dagen: number | null) => {
    if (dagen === null) {
        return { tekst: 'nog geen rapport', kleur: 'text-warning' };
    }

    const tekst = dagen === 0 ? 'vandaag beoordeeld' : dagen === 1 ? 'gisteren beoordeeld' : dagen + ' dagen geleden';

    return { tekst, kleur: dagen <= props.staleAfterDays ? 'text-success' : 'text-warning' };
};

// Wat er in het bolletje op de filterknop staat: alles wat afwijkt van
// "alle actieve spelers". Zoeken telt niet mee: dat zie je in het veld zelf.
const actieveFilters = computed(() => (filters.position !== '' ? 1 : 0) + (filters.group !== null ? 1 : 0) + (filters.status !== 'active' ? 1 : 0));

const keuzeKlasse = 'mt-1.5 min-h-11 w-full min-w-0 rounded-lg border border-input bg-card px-3 py-2 text-sm outline-none focus:border-primary';

const filters = reactive({ ...props.filters });

let wachten: ReturnType<typeof setTimeout> | undefined;

watch(
    filters,
    () => {
        clearTimeout(wachten);
        wachten = setTimeout(() => {
            router.get('/clients', { ...filters, group: filters.group ?? '' }, { preserveState: true, replace: true });
        }, 300);
    },
    { deep: true },
);

// Welke rijen staan open. Meerdere tegelijk mag: je vergelijkt wel eens twee
// gezinnen, en dan is het irritant als de vorige dichtklapt.
const uitgeklapt = ref<number[]>([]);

const klap = (id: number) => {
    uitgeklapt.value = uitgeklapt.value.includes(id) ? uitgeklapt.value.filter((x) => x !== id) : [...uitgeklapt.value, id];
};

// In kleuren een gekleurde stip in plaats van het cijfer.
const { kleuren, niveauVoor } = useGrading();
</script>

<template>
    <Head :title="isTrainer ? 'Spelers' : 'Klanten'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-4xl p-4" data-tour="clients">
            <FlashMessage />

            <h1 class="text-2xl font-semibold tracking-tight">{{ isTrainer ? 'Spelers' : 'Klanten' }}</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                <span class="tabular">{{ counts.players }}</span> actieve {{ counts.players === 1 ? 'speler' : 'spelers' }} en
                <span class="tabular">{{ counts.guardians }}</span>
                {{ counts.guardians === 1 ? 'ouder' : 'ouders' }}. Klap een speler uit om te zien wie je belt.
                <span v-if="players.length" class="hidden sm:inline">{{
                    kleuren ? 'De stip is de kleur op de spelerskaart.' : 'Het groene getal is de rating op de spelerskaart.'
                }}</span>
            </p>

            <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-muted-foreground">
                    <span class="tabular">{{ players.length }}</span>
                    {{ players.length === 1 ? 'speler' : 'spelers' }} gevonden
                </p>

                <Link
                    v-if="can.managePlayers"
                    href="/players/create"
                    class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                >
                    <Plus class="size-4" />
                    Speler toevoegen
                </Link>
            </div>

            <!-- Zoeken staat altijd in beeld; de keuzelijsten zitten op een
                 telefoon achter één knop met een teller. -->
            <div class="mt-3 flex gap-3 sm:grid sm:grid-cols-2 lg:grid-cols-4">
                <div class="relative min-w-0 flex-1 sm:col-span-2 lg:col-span-1">
                    <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <input
                        v-model="filters.search"
                        type="search"
                        placeholder="Zoek op speler of ouder..."
                        class="min-h-11 w-full rounded-lg border border-input bg-card py-2 pl-9 pr-3 text-sm outline-none focus:border-primary"
                    />
                </div>

                <FilterSheet :count="actieveFilters" :title="isTrainer ? 'Spelers' : 'Klanten'">
                    <label class="block min-w-0">
                        <span class="text-xs font-medium text-muted-foreground sm:sr-only">Positie</span>
                        <select v-model="filters.position" :class="keuzeKlasse" class="sm:mt-0">
                            <option value="">Alle posities</option>
                            <option v-for="(label, waarde) in positions" :key="waarde" :value="waarde">{{ label }}</option>
                        </select>
                    </label>

                    <label class="block min-w-0">
                        <span class="text-xs font-medium text-muted-foreground sm:sr-only">Groep</span>
                        <select v-model="filters.group" :class="keuzeKlasse" class="sm:mt-0">
                            <option :value="null">Alle groepen</option>
                            <option v-for="groep in groups" :key="groep.id" :value="groep.id">{{ groep.name }}</option>
                        </select>
                    </label>

                    <label class="block min-w-0 sm:col-span-2 lg:col-span-1">
                        <span class="text-xs font-medium text-muted-foreground sm:sr-only">Status</span>
                        <select v-model="filters.status" :class="keuzeKlasse" class="sm:mt-0">
                            <option value="active">Actieve spelers</option>
                            <option value="inactive">Niet-actieve spelers</option>
                            <option value="all">Alle spelers</option>
                        </select>
                    </label>
                </FilterSheet>
            </div>

            <div v-if="players.length" class="mt-4 overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <div v-for="(speler, index) in players" :key="speler.id" :class="index > 0 ? 'border-t border-border' : ''">
                    <div class="flex items-center gap-3 p-3 sm:gap-4 sm:p-4">
                        <!-- De naam en het medaillon zijn de weg naar de speler;
                             het uitklappen zit in een eigen knop ernaast. Een
                             link in een link kan niet, en een rij die twee
                             dingen tegelijk doet raad je nooit goed. -->
                        <Link :href="'/players/' + speler.id" class="flex min-w-0 flex-1 items-center gap-3 sm:gap-4">
                            <Avatar :name="speler.name" :photo="speler.photo" size="size-11" />

                            <span
                                class="tabular hidden size-11 shrink-0 items-center justify-center rounded-lg text-base font-bold sm:flex"
                                :class="speler.overall_rating ? 'bg-primary/10 text-primary' : 'bg-secondary text-muted-foreground'"
                                :title="speler.overall_rating ? 'Rating op de spelerskaart' : 'Nog geen rapport, dus nog geen rating'"
                            >
                                <template v-if="!kleuren">{{ speler.overall_rating ?? '-' }}</template><span v-else class="size-4 rounded-full" :style="{ backgroundColor: kleurVan(niveauVoor(speler.overall_rating)?.key, niveauVoor(speler.overall_rating) ? 1 : 0.25) }" :title="niveauVoor(speler.overall_rating)?.label ?? 'Nog geen rapport'"></span>
                            </span>

                            <span class="min-w-0 flex-1">
                                <span class="flex flex-wrap items-center gap-x-2 gap-y-1 font-medium">
                                    {{ speler.name }}
                                    <DemoBadge v-if="speler.is_demo" />
                                    <KeyRound v-if="speler.has_login" class="size-3.5 shrink-0 text-muted-foreground" title="Heeft een eigen inlog" />
                                    <span
                                        v-if="!speler.is_active"
                                        class="rounded bg-secondary px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground"
                                    >
                                        niet actief
                                    </span>
                                    <span
                                        v-if="speler.has_overdue_payment"
                                        class="rounded bg-warning/10 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-warning"
                                    >
                                        betaling openstaand
                                    </span>
                                </span>
                                <span class="mt-0.5 block text-xs text-muted-foreground">
                                    {{ speler.position }}<template v-if="speler.age"> &middot; {{ speler.age }} jaar</template>
                                    <template v-if="speler.groups.length"> &middot; {{ speler.groups.join(', ') }}</template>
                                    <template v-else> &middot; nog geen groep</template>
                                </span>
                                <!-- Wanneer voor het laatst beoordeeld: groen is recent,
                                     oranje vraagt aandacht. Dezelfde dertig dagen als overal. -->
                                <span v-if="isTrainer" class="mt-0.5 block text-xs font-medium" :class="beoordeling(speler.days_since_report).kleur">
                                    {{ beoordeling(speler.days_since_report).tekst }}
                                </span>
                            </span>

                            <!-- Op een telefoon staat het cijfer rechts, op een groot
                                 scherm links naast het medaillon. -->
                            <span
                                class="tabular flex size-11 shrink-0 items-center justify-center rounded-lg text-base font-bold sm:hidden"
                                :class="speler.overall_rating ? 'bg-primary/10 text-primary' : 'bg-secondary text-muted-foreground'"
                            >
                                <template v-if="!kleuren">{{ speler.overall_rating ?? '-' }}</template><span v-else class="size-4 rounded-full" :style="{ backgroundColor: kleurVan(niveauVoor(speler.overall_rating)?.key, niveauVoor(speler.overall_rating) ? 1 : 0.25) }" :title="niveauVoor(speler.overall_rating)?.label ?? 'Nog geen rapport'"></span>
                            </span>
                        </Link>

                        <button
                            v-if="!isTrainer"
                            type="button"
                            class="flex min-h-11 shrink-0 items-center gap-1 rounded-lg border border-border px-2 text-xs font-medium transition hover:border-primary"
                            :class="speler.guardians.length ? 'text-foreground' : 'text-muted-foreground'"
                            :aria-expanded="uitgeklapt.includes(speler.id)"
                            :aria-label="'Ouders van ' + speler.name"
                            @click="klap(speler.id)"
                        >
                            <Users class="size-3.5" />
                            <span class="tabular">{{ speler.guardians.length }}</span>
                            <ChevronDown class="size-3.5 transition" :class="uitgeklapt.includes(speler.id) ? 'rotate-180' : ''" />
                        </button>
                    </div>

                    <!-- De ouders bij dit kind. Geen aparte lijst meer: je zoekt
                         een ouder zelden zonder te weten van wie hij er een is. -->
                    <div v-if="uitgeklapt.includes(speler.id)" class="border-t border-border bg-secondary/40 px-3 py-3 sm:px-4">
                        <div v-if="speler.guardians.length" class="space-y-2">
                            <div v-for="ouder in speler.guardians" :key="ouder.id" class="flex items-center gap-3">
                                <Avatar :name="ouder.name" :photo="ouder.photo" size="size-9" />

                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium">
                                        {{ ouder.name }}
                                        <span v-if="ouder.relationship" class="font-normal text-muted-foreground">
                                            &middot; {{ ouder.relationship }}
                                        </span>
                                    </p>
                                    <a
                                        :href="'mailto:' + ouder.email"
                                        class="mt-0.5 flex items-center gap-1.5 text-xs text-muted-foreground hover:text-primary"
                                    >
                                        <Mail class="size-3.5 shrink-0" />
                                        <span class="truncate">{{ ouder.email }}</span>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <p v-else class="text-sm text-muted-foreground">
                            Nog geen ouder gekoppeld.
                            <Link :href="'/players/' + speler.id" class="font-medium text-primary hover:underline">Koppel er een</Link>
                            op de pagina van deze speler.
                        </p>
                    </div>
                </div>
            </div>

            <div v-else class="mt-4 rounded-2xl border border-dashed border-border bg-card/50 p-10 text-center">
                <p class="font-medium">Geen spelers gevonden</p>
                <p class="mt-1 text-sm text-muted-foreground">Pas je filters aan of voeg een speler toe.</p>
            </div>
        </div>
    </AppLayout>
</template>
