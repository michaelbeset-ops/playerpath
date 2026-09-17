<script setup lang="ts">
import FilterSheet from '@/components/FilterSheet.vue';
import StatCard from '@/components/StatCard.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { toneStat, type Tone } from '@/lib/tone';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { CalendarDays, ClipboardList, FileCheck2, Printer, Target, TrendingUp, Users } from 'lucide-vue-next';
import { reactive } from 'vue';

const props = defineProps<{
    report: {
        period: { from: string; to: string; label: string };
        players: number;
        reports: number;
        playersWithReport: number;
        coverage: number | null;
        playersWithGoal: number;
        goalCoverage: number | null;
        goalsAchieved: number;
        trainings: number;
        cancelled: number;
        attendance: { percentage: number | null; present: number; recorded: number };
        development: { average: number | null; measured: number; improved: number };
        /** De kleur per cijfer, bepaald door Signal op de server (Support/Dashboard). */
        tones: { coverage: Tone; attendance: Tone; development: Tone };
    };
    schoolInfo: { name: string };
    range: { from: string; to: string };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Verantwoording', href: '/verantwoording' }];

const periode = reactive({ ...props.range });

const toon = () => router.get('/verantwoording', periode, { preserveState: true, preserveScroll: true });

const afdrukken = () => window.print();
</script>

<template>
    <Head title="Verantwoording" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-4xl p-4">
            <div class="flex flex-wrap items-start justify-between gap-3 print:hidden">
                <div class="flex min-w-0 items-start gap-3">
                    <FileCheck2 class="mt-1 size-5 shrink-0 text-primary" />
                    <div class="min-w-0">
                        <h1 class="text-2xl font-semibold tracking-tight">Verantwoording</h1>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Wat je school in een periode heeft gedaan, in cijfers die je kunt laten zien aan ouders, een vereniging of de gemeente.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Periode kiezen: op een groot scherm gewoon in beeld, op een
                 telefoon achter één knop - dezelfde als bij de agenda. -->
            <form class="mt-4 flex flex-wrap items-end gap-3 print:hidden" @submit.prevent="toon">
                <FilterSheet :count="0" label="Periode" title="Periode">
                    <div class="grid gap-2 sm:w-44">
                        <Label for="from">Van</Label>
                        <Input id="from" v-model="periode.from" type="date" />
                    </div>
                    <div class="grid gap-2 sm:w-44">
                        <Label for="to">Tot en met</Label>
                        <Input id="to" v-model="periode.to" type="date" />
                    </div>
                    <Button type="submit" variant="secondary" class="w-full sm:w-auto">Toon periode</Button>
                </FilterSheet>

                <button
                    type="button"
                    class="inline-flex min-h-11 items-center rounded-lg border border-border bg-card px-3 text-sm font-medium shadow-sm transition hover:border-primary"
                    @click="afdrukken"
                >
                    <Printer class="mr-2 size-4" />
                    Afdrukken
                </button>
            </form>

            <!-- Het overzicht zelf: ook los te printen. Dezelfde kerncijfer-kaarten
                 als op het dashboard en bij Betalingen, twee op een rij op een
                 telefoon. -->
            <div class="mt-4">
                <header class="rounded-xl border border-border bg-card p-4 shadow-sm">
                    <p class="text-lg font-semibold">{{ schoolInfo.name }}</p>
                    <p class="text-sm text-muted-foreground">Overzicht over {{ report.period.label }}</p>
                </header>

                <div class="mt-3 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-3">
                    <StatCard label="Actieve spelers" :value="report.players || null" hint="in deze periode" :icon="Users" />
                    <StatCard
                        label="Met een rapport"
                        :value="report.playersWithReport || null"
                        :hint="report.coverage !== null ? report.coverage + '% van de spelers' : 'nog geen rapport'"
                        :icon="ClipboardList"
                        :tone="toneStat[report.tones.coverage]"
                    />
                    <StatCard label="Rapporten" :value="report.reports || null" hint="geschreven in deze periode" :icon="ClipboardList" />
                    <StatCard
                        label="Met een doel"
                        :value="report.playersWithGoal || null"
                        :hint="report.goalCoverage !== null ? report.goalCoverage + '% heeft een actief ontwikkelingsdoel' : 'nog geen doel gesteld'"
                        :icon="Target"
                    />
                    <StatCard label="Doelen gehaald" :value="report.goalsAchieved || null" hint="in deze periode" :icon="Target" />
                    <StatCard
                        label="Trainingen"
                        :value="report.trainings || null"
                        :hint="report.cancelled > 0 ? report.cancelled + ' afgezegd' : 'gegeven, niets afgezegd'"
                        :icon="CalendarDays"
                    />
                    <StatCard
                        label="Opkomst"
                        :value="report.attendance.percentage !== null ? report.attendance.percentage + '%' : null"
                        :hint="
                            report.attendance.recorded > 0
                                ? report.attendance.present + ' van ' + report.attendance.recorded + ' afgevinkt'
                                : 'er is nog niets afgevinkt'
                        "
                        :icon="Users"
                        :tone="toneStat[report.tones.attendance]"
                    />
                    <StatCard
                        class="col-span-2 lg:col-span-1"
                        label="Ontwikkeling"
                        :value="report.development.average !== null ? (report.development.average > 0 ? '+' : '') + report.development.average : null"
                        :hint="
                            report.development.measured > 0
                                ? 'gemiddeld op de kaart, over ' +
                                  report.development.measured +
                                  (report.development.measured === 1 ? ' speler' : ' spelers') +
                                  ' met twee rapporten; ' +
                                  report.development.improved +
                                  ' vooruit'
                                : 'vraagt minstens twee rapporten per speler'
                        "
                        :icon="TrendingUp"
                        :tone="toneStat[report.tones.development]"
                    />
                </div>

                <p class="mt-4 rounded-xl border border-border bg-card p-4 text-xs text-muted-foreground shadow-sm">
                    Alle cijfers gaan over de school als geheel. Er staan geen gegevens van individuele kinderen in en er wordt nergens tussen spelers
                    vergeleken. Opkomst telt alleen trainingen die een trainer daadwerkelijk heeft afgevinkt; niet-afgevinkt is geen afwezigheid.
                </p>
            </div>
        </div>
    </AppLayout>
</template>

<style>
/* Afdrukken is voor een ouderavond of de gemeente: alleen de cijfers, geen
   balk, tabbalk, melding, rondleiding of bekijk-als-balk. */
@media print {
    aside,
    header.sticky,
    nav,
    [role='status'],
    [data-print-hidden] {
        display: none !important;
    }
}
</style>
