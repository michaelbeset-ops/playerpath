<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { FileCheck2, Printer } from 'lucide-vue-next';
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
    };
    school: { name: string };
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
        <div class="p-4">
            <div class="flex items-center gap-3 print:hidden">
                <FileCheck2 class="size-5 text-primary" />
                <div>
                    <h1 class="text-xl font-semibold">Verantwoording</h1>
                    <p class="text-sm text-muted-foreground">
                        Wat je school in een periode heeft gedaan, in cijfers die je kunt laten zien aan ouders, een vereniging of de gemeente.
                    </p>
                </div>
            </div>

            <!-- Periode kiezen -->
            <form
                class="mt-4 flex flex-wrap items-end gap-3 rounded-xl border border-border bg-card p-4 shadow-sm print:hidden"
                @submit.prevent="toon"
            >
                <div class="grid gap-2">
                    <Label for="from">Van</Label>
                    <Input id="from" v-model="periode.from" type="date" class="w-44" />
                </div>
                <div class="grid gap-2">
                    <Label for="to">Tot en met</Label>
                    <Input id="to" v-model="periode.to" type="date" class="w-44" />
                </div>
                <Button type="submit" variant="secondary">Toon periode</Button>
                <button
                    type="button"
                    class="inline-flex min-h-11 items-center rounded-lg border border-border px-3 text-sm font-medium hover:border-primary"
                    @click="afdrukken"
                >
                    <Printer class="mr-2 size-4" />
                    Afdrukken
                </button>
            </form>

            <!-- Het overzicht zelf: ook los te printen -->
            <div class="mt-4 rounded-xl border border-border bg-card p-6 shadow-sm">
                <header class="border-b border-border pb-4">
                    <p class="text-lg font-semibold">{{ school.name }}</p>
                    <p class="text-sm text-muted-foreground">Overzicht over {{ report.period.label }}</p>
                </header>

                <div class="mt-5 grid grid-cols-2 gap-4 sm:gap-5 lg:grid-cols-3">
                    <div>
                        <p class="tabular text-2xl font-semibold sm:text-3xl">{{ report.players }}</p>
                        <p class="text-sm text-muted-foreground">actieve spelers</p>
                    </div>

                    <div>
                        <p class="tabular text-2xl font-semibold sm:text-3xl">
                            {{ report.playersWithReport }}
                            <span v-if="report.coverage !== null" class="text-base font-normal text-muted-foreground">
                                ({{ report.coverage }}%)
                            </span>
                        </p>
                        <p class="text-sm text-muted-foreground">spelers met een rapport in deze periode</p>
                    </div>

                    <div>
                        <p class="tabular text-2xl font-semibold sm:text-3xl">{{ report.reports }}</p>
                        <p class="text-sm text-muted-foreground">rapporten geschreven</p>
                    </div>

                    <div>
                        <p class="tabular text-2xl font-semibold sm:text-3xl">
                            {{ report.playersWithGoal }}
                            <span v-if="report.goalCoverage !== null" class="text-base font-normal text-muted-foreground">
                                ({{ report.goalCoverage }}%)
                            </span>
                        </p>
                        <p class="text-sm text-muted-foreground">spelers met een actief ontwikkelingsdoel</p>
                    </div>

                    <div>
                        <p class="tabular text-2xl font-semibold sm:text-3xl">{{ report.goalsAchieved }}</p>
                        <p class="text-sm text-muted-foreground">doelen gehaald in deze periode</p>
                    </div>

                    <div>
                        <p class="tabular text-2xl font-semibold sm:text-3xl">{{ report.trainings }}</p>
                        <p class="text-sm text-muted-foreground">
                            trainingen gegeven<span v-if="report.cancelled > 0">, {{ report.cancelled }} afgezegd</span>
                        </p>
                    </div>

                    <div>
                        <p class="tabular text-2xl font-semibold sm:text-3xl">
                            {{ report.attendance.percentage !== null ? report.attendance.percentage + '%' : '—' }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            <template v-if="report.attendance.recorded > 0">
                                opkomst — {{ report.attendance.present }} van {{ report.attendance.recorded }} afgevinkt
                            </template>
                            <template v-else>opkomst: er is nog niets afgevinkt</template>
                        </p>
                    </div>

                    <div class="col-span-2">
                        <p class="tabular text-2xl font-semibold sm:text-3xl">
                            <template v-if="report.development.average !== null">
                                {{ report.development.average > 0 ? '+' : '' }}{{ report.development.average }}
                            </template>
                            <template v-else>—</template>
                        </p>
                        <p class="text-sm text-muted-foreground">
                            <template v-if="report.development.measured > 0">
                                gemiddelde ontwikkeling op de kaart, gemeten over {{ report.development.measured }}
                                {{ report.development.measured === 1 ? 'speler' : 'spelers' }} met minstens twee rapporten. Daarvan gingen er
                                {{ report.development.improved }} vooruit.
                            </template>
                            <template v-else> Nog niet te meten: ontwikkeling vraagt minstens twee rapporten per speler in deze periode. </template>
                        </p>
                    </div>
                </div>

                <p class="mt-6 border-t border-border pt-4 text-xs text-muted-foreground">
                    Alle cijfers gaan over de school als geheel. Er staan geen gegevens van individuele kinderen in en er wordt nergens tussen spelers
                    vergeleken. Opkomst telt alleen trainingen die een trainer daadwerkelijk heeft afgevinkt; niet-afgevinkt is geen afwezigheid.
                </p>
            </div>
        </div>
    </AppLayout>
</template>

<style>
@media print {
    aside,
    header[class*='sticky'] {
        display: none !important;
    }
}
</style>
