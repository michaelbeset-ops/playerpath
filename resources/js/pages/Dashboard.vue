<script setup lang="ts">
import StatCard from '@/components/StatCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { CalendarDays, ClipboardList, Users } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    stats: { players: number; reportsThisWeek: number; trainingsThisWeek: number };
}>();

const page = usePage<SharedData>();
const school = computed(() => page.props.school);
const rollen = computed(() => page.props.auth.roles ?? []);

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

// Alleen cijfers die nu echt bestaan krijgen een waarde. Trainingen komt in
// fase 4 en blijft tot die tijd bewust grijs.
const kaarten = computed(() => [
    {
        label: 'Spelers',
        value: props.stats.players,
        hint: props.stats.players === 1 ? 'actieve speler' : 'actieve spelers',
        icon: Users,
    },
    {
        label: 'Rapporten deze week',
        value: props.stats.reportsThisWeek,
        hint: props.stats.reportsThisWeek === 0 ? 'Nog niemand beoordeeld deze week' : 'ingevuld door je trainers',
        icon: ClipboardList,
        href: '/reports',
    },
    {
        label: 'Trainingen deze week',
        value: props.stats.trainingsThisWeek,
        hint: props.stats.trainingsThisWeek === 0 ? 'Niets ingepland deze week' : 'ingepland',
        icon: CalendarDays,
        href: '/trainings',
    },
]);
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-5xl p-4">
            <div class="flex flex-wrap items-baseline gap-x-3">
                <h1 class="text-2xl font-semibold tracking-tight">Dashboard</h1>
                <p class="text-sm text-muted-foreground">
                    <template v-if="school">{{ school.name }}</template>
                    <span v-if="rollen.length" class="text-muted-foreground/70"> &middot; {{ rollen.join(', ') }}</span>
                </p>
            </div>

            <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <StatCard
                    v-for="kaart in kaarten"
                    :key="kaart.label"
                    :label="kaart.label"
                    :value="kaart.value"
                    :hint="kaart.hint"
                    :icon="kaart.icon"
                    :href="kaart.href"
                />
            </div>

            <!-- Eén duidelijke volgende stap in plaats van een leeg vlak -->
            <div class="mt-4 rounded-xl border border-border bg-card p-6 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="min-w-0">
                        <p class="font-medium">Aan de slag</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Vul een rapport in en de spelerskaart is meteen bijgewerkt. Spelers, groepen en trainingen volgen in de
                            volgende fases.
                        </p>
                    </div>

                    <Link
                        href="/reports"
                        class="inline-flex shrink-0 items-center rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                    >
                        Rapport invullen
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
