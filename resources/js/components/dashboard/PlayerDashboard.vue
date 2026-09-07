<script setup lang="ts">
import CardGlow from '@/components/CardGlow.vue';
import PlayerCardVisual from '@/components/PlayerCardVisual.vue';
import type { SpelerDashboardData, SpelerTrend } from '@/types/player-dashboard';
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight, Award, CalendarDays, ChevronRight, MapPin, Minus, Sparkles, Target, TrendingDown, TrendingUp } from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * Het dashboard van een speler: mijn kaart, hoe ga ik vooruit, wanneer is de
 * volgende training. Meer niet.
 *
 * Het staat op de **donkere kant** van het merk, net als de kaart zelf: dit is
 * het scherm van het kind, niet de werkvloer van de school. Speels betekent
 * hier: grote cijfers, één zin die zegt hoe het gaat, een level en een badge
 * om naartoe te werken, en één ding om aan te werken — geen lijst met zes
 * verbeterpunten, want die leest als kritiek en niemand begint eraan.
 *
 * Inschrijven en betalen staan er bewust niet: dat doen de ouders.
 */
const props = defineProps<SpelerDashboardData>();

/*
 * De kop, in gewone taal. Dezelfde grenzen en toon als op de voortgangspagina:
 * groei wordt gevierd, een mindere periode niet weggepoetst, maar wel warm.
 */
const kop = computed(() => {
    const groei = props.quarter.growth;

    if (!props.hasEnoughData || groei === null) {
        return {
            toon: 'rustig',
            tekst: props.card.report_count === 0 ? 'Je kaart wacht op je eerste rapport' : 'Nog één rapport en je ziet je groei',
            sub: 'Elke training telt: je trainer vult na de training in hoe het ging.',
        };
    }

    if (groei > 0) {
        return {
            toon: 'goed',
            tekst: `+${groei} gegroeid in drie maanden 🎉`,
            sub: 'Lekker bezig. Zo ga je omhoog.',
        };
    }

    if (groei === 0) {
        return { toon: 'rustig', tekst: 'Stabiel de laatste drie maanden', sub: 'Je niveau vasthouden is ook knap.' };
    }

    return {
        toon: 'aandacht',
        tekst: `${groei} sinds drie maanden geleden`,
        sub: 'Dat hoort bij leren. Een paar goede trainingen en de lijn draait weer.',
    };
});

const trendKleur = (trend: SpelerTrend | null) => {
    if (trend === null) {
        return 'text-muted-foreground';
    }

    return trend.key === 'aandacht' ? 'text-warning' : trend.key === 'stabiel' ? 'text-muted-foreground' : 'text-primary';
};

const trendIcoon = (trend: SpelerTrend | null) => {
    if (trend === null || trend.key === 'stabiel') {
        return Minus;
    }

    return trend.key === 'aandacht' ? TrendingDown : TrendingUp;
};

const deltaTekst = (delta: number | null) => (delta === null ? '' : delta > 0 ? `+${delta}` : `${delta}`);

const kaartHref = computed(() => '/players/' + props.player.id + '/card');
const voortgangHref = computed(() => '/players/' + props.player.id + '/progress');
</script>

<template>
    <!-- De donkere kant van het merk; zie CLAUDE.md hoofdstuk 4. Het thema
         staat op de pagina (Dashboard.vue), zodat ook de begroeting erboven
         donker is en er geen lichte strook tussen balk en inhoud zit. -->
    <div>
        <div class="mx-auto max-w-2xl space-y-6">
            <!-- 1. Mijn kaart. Dit is waar een kind voor komt. -->
            <section>
                <CardGlow :level="card.overall === null ? 'geen' : card.level.key">
                    <PlayerCardVisual :card="card" audience="gezin" :shareable="false" />
                </CardGlow>

                <div class="mt-4 grid grid-cols-2 gap-2">
                    <Link
                        :href="kaartHref"
                        class="inline-flex h-11 items-center justify-center gap-2 rounded-xl border border-border bg-card text-sm font-medium transition hover:border-primary"
                    >
                        Mijn kaart
                        <ChevronRight class="size-4" />
                    </Link>
                    <Link
                        :href="voortgangHref"
                        class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-primary text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                    >
                        <ArrowUpRight class="size-4" />
                        Mijn voortgang
                    </Link>
                </div>
            </section>

            <!-- 2. Hoe gaat het: één zin, dan de categorieën. -->
            <section
                class="overflow-hidden rounded-2xl border bg-card"
                :class="kop.toon === 'goed' ? 'border-primary/40' : kop.toon === 'aandacht' ? 'border-warning/40' : 'border-border'"
            >
                <div class="h-1" :class="kop.toon === 'goed' ? 'bg-primary' : kop.toon === 'aandacht' ? 'bg-warning' : 'bg-border'"></div>

                <div class="p-5">
                    <p
                        class="text-xl font-bold leading-tight sm:text-2xl"
                        :class="kop.toon === 'goed' ? 'text-primary' : kop.toon === 'aandacht' ? 'text-warning' : ''"
                    >
                        {{ kop.tekst }}
                    </p>
                    <p class="mt-1 text-sm text-muted-foreground">{{ kop.sub }}</p>

                    <template v-if="hasEnoughData">
                        <ul class="mt-4 grid grid-cols-2 gap-2">
                            <li
                                v-for="categorie in categories"
                                :key="categorie.category"
                                class="flex min-w-0 items-center gap-2 rounded-xl border border-border bg-background px-3 py-2"
                            >
                                <component :is="trendIcoon(categorie.trend)" class="size-4 shrink-0" :class="trendKleur(categorie.trend)" />
                                <span class="min-w-0 flex-1">
                                    <span class="block break-words text-xs leading-tight text-muted-foreground">{{ categorie.label }}</span>
                                    <span class="tabular block text-base font-bold leading-tight">
                                        {{ categorie.last ?? '—' }}
                                        <span v-if="categorie.delta" class="text-xs font-semibold" :class="trendKleur(categorie.trend)">
                                            {{ deltaTekst(categorie.delta) }}
                                        </span>
                                    </span>
                                </span>
                            </li>
                        </ul>

                        <div class="mt-4 grid grid-cols-2 gap-3">
                            <div>
                                <p class="tabular text-2xl font-bold leading-none">{{ quarter.trainings }}</p>
                                <p class="mt-1 text-xs text-muted-foreground">trainingen in drie maanden</p>
                            </div>
                            <div class="min-w-0">
                                <p class="flex items-center gap-1.5 text-base font-bold leading-tight">
                                    <Award class="size-5 shrink-0 text-gold" />
                                    <span class="min-w-0 break-words">{{ quarter.best ?? '—' }}</span>
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">je sterkste kant</p>
                            </div>
                        </div>
                    </template>
                </div>
            </section>

            <!-- 3. Eén ding om aan te werken, en iets om naartoe te werken. -->
            <section class="grid gap-3 sm:grid-cols-2">
                <div v-if="nextStep" class="rounded-2xl border border-primary/40 bg-card p-5">
                    <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-primary">
                        <Target class="size-4 shrink-0" />
                        {{ nextStep.type === 'goal' ? 'Jouw doel' : 'Hier kun je aan werken' }}
                    </p>
                    <p class="mt-2 text-lg font-bold leading-tight">{{ nextStep.label }}</p>
                    <p class="tabular mt-1 text-sm text-muted-foreground">
                        <template v-if="nextStep.from !== null">van {{ nextStep.from }} naar {{ nextStep.to }}</template>
                        <template v-else>naar {{ nextStep.to }}</template>
                        <template v-if="nextStep.type === 'goal' && nextStep.on_track !== null">
                            &middot;
                            <span :class="nextStep.on_track ? 'text-primary' : 'text-warning'">{{
                                nextStep.on_track ? 'op koers' : 'even doorzetten'
                            }}</span>
                        </template>
                    </p>
                    <p v-if="nextStep.hint" class="mt-2 text-sm text-muted-foreground">{{ nextStep.hint }}</p>
                </div>

                <div class="rounded-2xl border border-gold/40 bg-card p-5">
                    <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gold">
                        <Sparkles class="size-4 shrink-0" />
                        Volgende upgrade
                    </p>

                    <div class="mt-2 flex flex-wrap items-baseline justify-between gap-2">
                        <p class="text-lg font-bold leading-tight">Level {{ card.level.label }}</p>
                        <p class="tabular text-xs text-muted-foreground">{{ card.level.xp }} XP</p>
                    </div>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-secondary">
                        <div class="h-full rounded-full bg-gold transition-all" :style="{ width: card.level.progress + '%' }"></div>
                    </div>
                    <p class="mt-2 text-sm text-muted-foreground">
                        <template v-if="card.level.next">
                            Nog <span class="tabular font-semibold text-foreground">{{ card.level.next.remaining }} XP</span> tot
                            {{ card.level.next.label }}.
                        </template>
                        <template v-else>Je hebt het hoogste level. Respect.</template>
                    </p>

                    <p v-if="nextBadge" class="mt-3 border-t border-border pt-3 text-sm">
                        <span class="font-medium">Volgende badge: {{ nextBadge.label }}</span>
                        <span class="block text-xs text-muted-foreground">{{ nextBadge.description }}</span>
                    </p>
                </div>
            </section>

            <!-- 4. Wanneer is de volgende training. -->
            <section>
                <h2 class="font-semibold">Volgende training</h2>

                <Link
                    v-if="nextTraining"
                    :href="'/trainings/' + nextTraining.id"
                    class="mt-3 flex min-w-0 items-start gap-3 rounded-2xl border bg-card p-4 transition hover:border-primary"
                    :class="nextTraining.is_today ? 'border-primary/50' : 'border-border'"
                >
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary/15 text-primary">
                        <CalendarDays class="size-5" />
                    </span>

                    <span class="min-w-0 flex-1">
                        <span class="flex flex-wrap items-center gap-2">
                            <span class="font-semibold" :class="nextTraining.cancelled ? 'line-through' : ''">{{ nextTraining.label }}</span>
                            <span
                                v-if="nextTraining.is_today"
                                class="rounded-full bg-primary px-2 py-0.5 text-[10px] font-semibold text-primary-foreground"
                            >
                                vandaag
                            </span>
                            <span v-if="nextTraining.cancelled" class="rounded-full bg-warning/20 px-2 py-0.5 text-[10px] font-semibold text-warning">
                                gaat niet door
                            </span>
                        </span>
                        <span class="tabular mt-0.5 block text-sm first-letter:uppercase">{{ nextTraining.date }} · {{ nextTraining.time }}</span>
                        <span v-if="nextTraining.location" class="mt-1 flex items-start gap-1.5 text-xs text-muted-foreground">
                            <MapPin class="mt-0.5 size-3.5 shrink-0" />
                            <span>{{ nextTraining.location }}</span>
                        </span>
                    </span>

                    <ChevronRight class="mt-1 size-4 shrink-0 text-muted-foreground" />
                </Link>

                <p v-else class="mt-3 rounded-2xl border border-dashed border-border p-5 text-center text-sm text-muted-foreground">
                    Er staat nog geen training gepland.
                </p>
            </section>
        </div>
    </div>
</template>
