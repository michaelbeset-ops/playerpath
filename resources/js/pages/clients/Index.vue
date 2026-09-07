<script setup lang="ts">
import Avatar from '@/components/Avatar.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronDown, KeyRound, Mail, Plus, Search, Users } from 'lucide-vue-next';
import { reactive, ref, watch } from 'vue';

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
    overall_rating: number | null;
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
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Klanten', href: '/clients' }];

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
</script>

<template>
    <Head title="Klanten" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-4xl p-4">
            <FlashMessage />

            <h1 class="text-2xl font-semibold tracking-tight">Klanten</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                <span class="tabular">{{ counts.players }}</span> actieve
                {{ counts.players === 1 ? 'speler' : 'spelers' }} en
                <span class="tabular">{{ counts.guardians }}</span>
                {{ counts.guardians === 1 ? 'ouder' : 'ouders' }}. Klap een speler uit om te zien wie je belt.
            </p>

            <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-muted-foreground">
                    <span class="tabular">{{ players.length }}</span>
                    {{ players.length === 1 ? 'speler' : 'spelers' }} gevonden
                </p>

                <Link
                    v-if="can.managePlayers"
                    href="/players/create"
                    class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                >
                    <Plus class="size-4" />
                    Speler toevoegen
                </Link>
            </div>

            <div class="mt-3 grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div class="relative col-span-2 lg:col-span-1">
                    <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <input
                        v-model="filters.search"
                        type="search"
                        placeholder="Zoek op speler of ouder..."
                        class="w-full rounded-lg border border-input bg-card py-2 pl-9 pr-3 text-sm outline-none focus:border-primary"
                    />
                </div>

                <select v-model="filters.position" class="min-w-0 rounded-lg border border-input bg-card px-3 py-2 text-sm outline-none focus:border-primary">
                    <option value="">Alle posities</option>
                    <option v-for="(label, waarde) in positions" :key="waarde" :value="waarde">{{ label }}</option>
                </select>

                <select v-model="filters.group" class="min-w-0 rounded-lg border border-input bg-card px-3 py-2 text-sm outline-none focus:border-primary">
                    <option :value="null">Alle groepen</option>
                    <option v-for="groep in groups" :key="groep.id" :value="groep.id">{{ groep.name }}</option>
                </select>

                <select v-model="filters.status" class="col-span-2 min-w-0 rounded-lg border border-input bg-card px-3 py-2 text-sm outline-none focus:border-primary lg:col-span-1">
                    <option value="active">Actieve spelers</option>
                    <option value="inactive">Niet-actieve spelers</option>
                    <option value="all">Alle spelers</option>
                </select>
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
                            >
                                {{ speler.overall_rating ?? '—' }}
                            </span>

                            <span class="min-w-0 flex-1">
                                <span class="flex flex-wrap items-center gap-x-2 gap-y-1 font-medium">
                                    {{ speler.name }}
                                    <KeyRound
                                        v-if="speler.has_login"
                                        class="size-3.5 shrink-0 text-muted-foreground"
                                        title="Heeft een eigen inlog"
                                    />
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
                            </span>
                        </Link>

                        <button
                            type="button"
                            class="flex h-9 shrink-0 items-center gap-1 rounded-lg border border-border px-2 text-xs font-medium transition hover:border-primary"
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
