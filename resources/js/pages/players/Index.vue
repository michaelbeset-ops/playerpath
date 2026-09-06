<script setup lang="ts">
import FlashMessage from '@/components/FlashMessage.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Search } from 'lucide-vue-next';
import { reactive, watch } from 'vue';

interface SpelerRij {
    id: number;
    name: string;
    position: string;
    position_value: string;
    age: number | null;
    is_active: boolean;
    overall_rating: number | null;
    groups: string[];
}

const props = defineProps<{
    players: SpelerRij[];
    filters: { search: string; position: string; group: number | null; status: string };
    positions: Record<string, string>;
    groups: { id: number; name: string }[];
    canManage: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Spelers', href: '/players' }];

const filters = reactive({ ...props.filters });

let wachten: ReturnType<typeof setTimeout> | undefined;

// Filteren gebeurt server-side, zodat het ook klopt als een school honderden
// spelers heeft. Even wachten met typen voorkomt een request per toetsaanslag.
watch(
    filters,
    () => {
        clearTimeout(wachten);
        wachten = setTimeout(() => {
            router.get('/players', { ...filters, group: filters.group ?? '' }, { preserveState: true, replace: true });
        }, 300);
    },
    { deep: true },
);

const leegmaken = () => {
    filters.search = '';
    filters.position = '';
    filters.group = null;
    filters.status = 'active';
};
</script>

<template>
    <Head title="Spelers" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-5xl p-4">
            <FlashMessage />

            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Spelers</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        <span class="tabular">{{ players.length }}</span>
                        {{ players.length === 1 ? 'speler' : 'spelers' }} gevonden
                    </p>
                </div>

                <Link
                    v-if="canManage"
                    href="/players/create"
                    class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                >
                    <Plus class="size-4" />
                    Speler toevoegen
                </Link>
            </div>

            <!-- Filters -->
            <div class="mt-6 grid gap-3 grid-cols-2 lg:grid-cols-4">
                <div class="relative">
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

            <!-- Overzicht -->
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
                            <span v-if="!speler.is_active" class="rounded bg-secondary px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground">
                                niet actief
                            </span>
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
                <button type="button" class="mt-3 text-sm font-medium text-primary underline underline-offset-4" @click="leegmaken">
                    Filters wissen
                </button>
            </div>
        </div>
    </AppLayout>
</template>
