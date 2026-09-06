<script setup lang="ts">
import PlatformLayout from '@/layouts/PlatformLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { AlertTriangle, Eye } from 'lucide-vue-next';

defineProps<{
    stats: {
        schools: number;
        activeSchools: number;
        players: number;
        trainers: number;
        owners: number;
        guardians: number;
        reportsThisMonth: number;
    };
    attention: { id: number; name: string; players_count: number; reason: string }[];
    recentImpersonations: { id: number; user_email: string; school: string | null; started_at: string; ended_at: string | null }[];
}>();
</script>

<template>
    <Head title="Platformbeheer" />

    <PlatformLayout>
        <h1 class="text-2xl font-semibold tracking-tight">Overzicht</h1>
        <p class="text-sm text-muted-foreground">Alle scholen bij elkaar.</p>

        <div class="mt-4 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
            <Link href="/beheer/scholen" class="rounded-xl border border-border bg-card p-4 shadow-sm transition hover:border-primary">
                <p class="tabular text-2xl font-bold text-primary sm:text-3xl">{{ stats.activeSchools }}</p>
                <p class="text-xs text-muted-foreground">actieve scholen van {{ stats.schools }}</p>
            </Link>

            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
                <p class="tabular text-2xl font-bold text-primary sm:text-3xl">{{ stats.players }}</p>
                <p class="text-xs text-muted-foreground">actieve spelers</p>
            </div>

            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
                <p class="tabular text-2xl font-bold sm:text-3xl">{{ stats.trainers }}</p>
                <p class="text-xs text-muted-foreground">trainers</p>
            </div>

            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
                <p class="tabular text-2xl font-bold sm:text-3xl">{{ stats.reportsThisMonth }}</p>
                <p class="text-xs text-muted-foreground">rapporten deze maand</p>
            </div>
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            <div class="rounded-xl border border-border bg-card p-5 shadow-sm">
                <p class="font-medium">Vraagt om aandacht</p>
                <p class="mt-1 text-xs text-muted-foreground">Een school die stilvalt zegt je meer dan een school die het goed doet.</p>

                <div v-if="attention.length" class="mt-3 space-y-2">
                    <Link
                        v-for="school in attention"
                        :key="school.id"
                        :href="'/beheer/scholen/' + school.id"
                        class="flex items-center gap-3 rounded-lg border border-warning/30 bg-warning/5 p-3 transition hover:border-warning"
                    >
                        <AlertTriangle class="size-4 shrink-0 text-warning" />
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-medium">{{ school.name }}</span>
                            <span class="block text-xs text-muted-foreground">{{ school.reason }}</span>
                        </span>
                    </Link>
                </div>

                <p v-else class="mt-3 rounded-lg border border-primary/25 bg-primary/5 p-3 text-sm">
                    Alle actieve scholen hebben spelers. Niets aan de hand.
                </p>
            </div>

            <div class="rounded-xl border border-border bg-card p-5 shadow-sm">
                <p class="font-medium">Laatst bekeken als</p>
                <p class="mt-1 text-xs text-muted-foreground">Elke keer dat je in de omgeving van een school keek, wordt vastgelegd.</p>

                <div v-if="recentImpersonations.length" class="mt-3 space-y-2">
                    <div v-for="log in recentImpersonations" :key="log.id" class="flex items-center gap-3 rounded-lg border border-border p-3">
                        <Eye class="size-4 shrink-0 text-muted-foreground" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm">{{ log.user_email }}</p>
                            <p class="tabular truncate text-xs text-muted-foreground">
                                {{ log.school ?? 'school verwijderd' }} &middot; {{ log.started_at }}
                                <template v-if="log.ended_at"> tot {{ log.ended_at }}</template>
                                <template v-else> &middot; nog bezig</template>
                            </p>
                        </div>
                    </div>
                </div>

                <p v-else class="mt-3 text-sm text-muted-foreground">Je hebt nog nooit als iemand anders gekeken.</p>
            </div>
        </div>

        <p class="mt-4 text-xs text-muted-foreground">
            Omzetcijfers staan hier bewust nog niet: die kloppen pas als er over alle scholen heen echt geld binnenkomt.
        </p>
    </PlatformLayout>
</template>
