<script setup lang="ts">
import PlatformLayout from '@/layouts/PlatformLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Eye, ScrollText } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{
    logs: {
        id: number;
        action: string;
        summary: string;
        school: string | null;
        school_id: number | null;
        admin: string;
        when: string;
        details: Record<string, unknown> | null;
    }[];
    impersonations: {
        id: number;
        user_email: string;
        admin: string;
        school: string | null;
        started_at: string;
        ended_at: string | null;
    }[];
    schools: { id: number; name: string }[];
    filters: { school: number | null };
}>();

const school = ref<string>(props.filters.school ? String(props.filters.school) : '');

watch(school, (waarde) => router.get('/beheer/logboek', waarde ? { school: waarde } : {}, { preserveState: true, replace: true }));

// Verwijderen en uitzetten springen eruit; de rest is routine.
const kleurVoor = (actie: string) => {
    if (actie === 'school.deleted') {
        return 'bg-destructive/10 text-destructive';
    }

    if (actie === 'school.deactivated' || actie === 'user.deactivated' || actie === 'school.features') {
        return 'bg-warning/10 text-warning';
    }

    return 'bg-secondary text-muted-foreground';
};
</script>

<template>
    <Head title="Logboek" />

    <PlatformLayout>
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Logboek</h1>
                <p class="text-sm text-muted-foreground">Wat je als beheerder hebt gedaan, en wanneer je in een school keek.</p>
            </div>

            <select
                v-model="school"
                class="h-10 rounded-lg border border-input bg-card px-3 text-base outline-none focus:border-primary sm:text-sm"
                aria-label="Filter op school"
            >
                <option value="">Alle scholen</option>
                <option v-for="s in schools" :key="s.id" :value="String(s.id)">{{ s.name }}</option>
            </select>
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            <div class="rounded-xl border border-border bg-card p-5 shadow-sm">
                <p class="flex items-center gap-2 font-medium">
                    <ScrollText class="size-4 text-muted-foreground" />
                    Beheeracties
                </p>

                <div v-if="logs.length" class="mt-3 space-y-2">
                    <div v-for="log in logs" :key="log.id" class="rounded-lg border border-border p-3">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <p class="min-w-0 text-sm font-medium">{{ log.summary }}</p>
                            <span class="shrink-0 rounded px-1.5 py-0.5 text-[10px] font-medium" :class="kleurVoor(log.action)">
                                {{ log.action }}
                            </span>
                        </div>

                        <p class="tabular mt-1 text-xs text-muted-foreground">
                            <Link v-if="log.school_id" :href="'/beheer/scholen/' + log.school_id" class="underline underline-offset-4">
                                {{ log.school }}
                            </Link>
                            <span v-else-if="log.school">{{ log.school }} (verwijderd)</span>
                            <span v-else>platformbreed</span>
                            &middot; {{ log.when }} &middot; {{ log.admin }}
                        </p>
                    </div>
                </div>

                <p v-else class="mt-3 text-sm text-muted-foreground">Nog niets gedaan om te loggen.</p>
            </div>

            <div class="rounded-xl border border-border bg-card p-5 shadow-sm">
                <p class="flex items-center gap-2 font-medium">
                    <Eye class="size-4 text-muted-foreground" />
                    Bekeken als
                </p>

                <div v-if="impersonations.length" class="mt-3 space-y-2">
                    <div v-for="log in impersonations" :key="log.id" class="rounded-lg border border-border p-3">
                        <p class="truncate text-sm">{{ log.user_email }}</p>
                        <p class="tabular truncate text-xs text-muted-foreground">
                            {{ log.school ?? 'school verwijderd' }} &middot; {{ log.started_at }}
                            <template v-if="log.ended_at"> tot {{ log.ended_at }}</template>
                            <template v-else> &middot; nog bezig</template>
                        </p>
                    </div>
                </div>

                <p v-else class="mt-3 text-sm text-muted-foreground">Je hebt nog nooit als iemand anders gekeken.</p>
            </div>
        </div>
    </PlatformLayout>
</template>
