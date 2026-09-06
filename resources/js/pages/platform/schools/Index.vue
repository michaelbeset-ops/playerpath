<script setup lang="ts">
import PlatformLayout from '@/layouts/PlatformLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Building2, Plus, Search } from 'lucide-vue-next';
import { reactive, watch } from 'vue';

interface SchoolRij {
    id: number;
    name: string;
    slug: string;
    is_active: boolean;
    players_count: number;
    trainers_count: number;
    users_count: number;
    created_at: string | null;
}

const props = defineProps<{
    schools: SchoolRij[];
    filters: { search: string; status: string };
    totals: { schools: number; active: number };
}>();

const filters = reactive({ ...props.filters });

let wachten: ReturnType<typeof setTimeout> | undefined;

// Even wachten met zoeken: bij elke toetsaanslag opnieuw laden voelt traag en
// belast de server zonder dat iemand er iets aan heeft.
watch(filters, () => {
    clearTimeout(wachten);
    wachten = setTimeout(() => router.get('/beheer/scholen', filters, { preserveState: true, preserveScroll: true, replace: true }), 250);
});
</script>

<template>
    <Head title="Scholen" />

    <PlatformLayout>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Scholen</h1>
                <p class="tabular text-sm text-muted-foreground">{{ totals.active }} actief van {{ totals.schools }} in totaal</p>
            </div>

            <Link
                href="/beheer/scholen/nieuw"
                class="inline-flex h-10 items-center gap-2 rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
            >
                <Plus class="size-4" />
                Nieuwe school
            </Link>
        </div>

        <div class="mt-4 grid gap-2 sm:grid-cols-[1fr_auto]">
            <div class="relative">
                <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <input
                    v-model="filters.search"
                    type="search"
                    placeholder="Zoek op naam of adres..."
                    class="h-10 w-full rounded-lg border border-input bg-card py-2 pl-9 pr-3 text-base outline-none focus:border-primary sm:text-sm"
                />
            </div>

            <select
                v-model="filters.status"
                class="h-10 rounded-lg border border-input bg-card px-3 text-base outline-none focus:border-primary sm:text-sm"
                aria-label="Filter op status"
            >
                <option value="">Alle scholen</option>
                <option value="active">Alleen actief</option>
                <option value="inactive">Alleen inactief</option>
            </select>
        </div>

        <div v-if="schools.length" class="mt-4 overflow-hidden rounded-xl border border-border bg-card shadow-sm">
            <Link
                v-for="(school, index) in schools"
                :key="school.id"
                :href="'/beheer/scholen/' + school.id"
                class="flex flex-col gap-2 p-4 transition hover:bg-secondary/60 sm:flex-row sm:items-center sm:gap-4"
                :class="index > 0 ? 'border-t border-border' : ''"
            >
                <span
                    class="flex size-10 shrink-0 items-center justify-center rounded-lg"
                    :class="school.is_active ? 'bg-primary/10 text-primary' : 'bg-secondary text-muted-foreground'"
                >
                    <Building2 class="size-5" />
                </span>

                <div class="min-w-0 flex-1">
                    <p class="flex flex-wrap items-center gap-2 font-medium">
                        {{ school.name }}
                        <span v-if="!school.is_active" class="rounded bg-secondary px-1.5 py-0.5 text-[10px] text-muted-foreground"> inactief </span>
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ school.slug }}<span v-if="school.created_at"> &middot; sinds {{ school.created_at }}</span>
                    </p>
                </div>

                <div class="tabular flex shrink-0 gap-4 text-sm">
                    <span>
                        <span class="font-semibold">{{ school.players_count }}</span>
                        <span class="text-muted-foreground"> spelers</span>
                    </span>
                    <span>
                        <span class="font-semibold">{{ school.trainers_count }}</span>
                        <span class="text-muted-foreground"> trainers</span>
                    </span>
                </div>
            </Link>
        </div>

        <div v-else class="mt-4 rounded-2xl border border-dashed border-border bg-card/50 p-10 text-center">
            <p class="font-medium">Geen scholen gevonden</p>
            <p class="mt-1 text-sm text-muted-foreground">
                {{ totals.schools === 0 ? 'Maak de eerste school aan om te beginnen.' : 'Pas je zoekopdracht of filter aan.' }}
            </p>
        </div>
    </PlatformLayout>
</template>
