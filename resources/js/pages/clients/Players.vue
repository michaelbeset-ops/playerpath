<script setup lang="ts">
import ClientTabs from '@/components/ClientTabs.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { KeyRound, Plus, Search } from 'lucide-vue-next';
import { reactive, watch } from 'vue';

interface SpelerRij {
    id: number;
    name: string;
    position: string;
    age: number | null;
    is_active: boolean;
    overall_rating: number | null;
    groups: string[];
    has_login: boolean;
    email: string | null;
}

const props = defineProps<{
    counts: { players: number; guardians: number };
    can: { managePlayers: boolean };
    players: SpelerRij[];
    filters: { search: string; position: string; group: number | null; status: string };
    positions: Record<string, string>;
    groups: { id: number; name: string }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Klanten', href: '/clients' },
    { title: 'Spelers', href: '/clients' },
];

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
</script>

<template>
    <Head title="Spelers" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-4xl p-4">
            <FlashMessage />

            <h1 class="text-2xl font-semibold tracking-tight">Klanten</h1>
            <p class="mt-1 text-sm text-muted-foreground">De spelers van je school en de ouders die erbij horen.</p>

            <ClientTabs actief="players" :counts="counts" />

            <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
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
                <div class="relative sm:col-span-2 lg:col-span-1">
                    <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <input
                        v-model="filters.search"
                        type="search"
                        placeholder="Zoek op naam..."
                        class="w-full rounded-lg border border-input bg-card py-2 pl-9 pr-3 text-sm outline-none focus:border-primary"
                    />
                </div>

                <select v-model="filters.position" class="rounded-lg border border-input bg-card px-3 py-2 text-sm outline-none focus:border-primary">
                    <option value="">Alle posities</option>
                    <option v-for="(label, waarde) in positions" :key="waarde" :value="waarde">{{ label }}</option>
                </select>

                <select v-model="filters.group" class="rounded-lg border border-input bg-card px-3 py-2 text-sm outline-none focus:border-primary">
                    <option :value="null">Alle groepen</option>
                    <option v-for="groep in groups" :key="groep.id" :value="groep.id">{{ groep.name }}</option>
                </select>

                <select v-model="filters.status" class="rounded-lg border border-input bg-card px-3 py-2 text-sm outline-none focus:border-primary">
                    <option value="active">Actieve spelers</option>
                    <option value="inactive">Niet-actieve spelers</option>
                    <option value="all">Alle spelers</option>
                </select>
            </div>

            <div v-if="players.length" class="mt-4 overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <Link
                    v-for="(speler, index) in players"
                    :key="speler.id"
                    :href="'/players/' + speler.id"
                    class="flex items-center gap-4 p-4 transition hover:bg-secondary/60"
                    :class="index > 0 ? 'border-t border-border' : ''"
                >
                    <div
                        class="tabular flex size-11 shrink-0 items-center justify-center rounded-lg text-base font-bold"
                        :class="speler.overall_rating ? 'bg-primary/10 text-primary' : 'bg-secondary text-muted-foreground'"
                    >
                        {{ speler.overall_rating ?? '—' }}
                    </div>

                    <div class="min-w-0 flex-1">
                        <p class="flex items-center gap-2 truncate font-medium">
                            {{ speler.name }}
                            <span v-if="!speler.is_active" class="rounded bg-secondary px-1.5 py-0.5 text-[10px] text-muted-foreground">
                                niet actief
                            </span>
                            <!-- Heeft dit kind zelf een inlog, of loopt alles via de ouder? -->
                            <KeyRound v-if="speler.has_login" class="size-3.5 shrink-0 text-muted-foreground" title="Heeft een eigen inlog" />
                        </p>
                        <p class="truncate text-xs text-muted-foreground">
                            {{ speler.position }}<span v-if="speler.age"> &middot; {{ speler.age }} jaar</span>
                            <span v-if="speler.groups.length"> &middot; {{ speler.groups.join(', ') }}</span>
                            <span v-else> &middot; nog geen groep</span>
                        </p>
                    </div>
                </Link>
            </div>

            <div v-else class="mt-4 rounded-2xl border border-dashed border-border bg-card/50 p-10 text-center">
                <p class="font-medium">Geen spelers gevonden</p>
                <p class="mt-1 text-sm text-muted-foreground">Pas je filters aan of voeg een speler toe.</p>
            </div>
        </div>
    </AppLayout>
</template>
