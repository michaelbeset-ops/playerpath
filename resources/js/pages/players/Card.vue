<script setup lang="ts">
import FlashMessage from '@/components/FlashMessage.vue';
import GoalList, { type Doel } from '@/components/GoalList.vue';
import PlayerCardVisual, { type Kaart } from '@/components/PlayerCardVisual.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Check, Copy, Link2, Lock, TrendingUp, Trophy } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    player: {
        id: number;
        name: string;
        photo: string | null;
        position: string;
        position_key: 'keeper' | 'field';
        age: number | null;
        overall_rating: number | null;
        rated_at: string | null;
    };
    card: Kaart;
    photoHref: string | null;
    reportCount: number;
    lastReport: { reported_on: string; trainer: string | null; note: string | null } | null;
    canReport: boolean;
    badges: { key: string; label: string; description: string; earned: boolean }[];
    share: { can: boolean; url: string | null };
    goals: Doel[];
}>();

// Bewust alleen de speler zelf: een kruimel naar /reports zou voor een ouder
// een dode link zijn.
const breadcrumbs: BreadcrumbItem[] = [{ title: props.player.name, href: '/players/' + props.player.id + '/card' }];

const behaald = computed(() => props.badges.filter((b) => b.earned));
const nogTeGaan = computed(() => props.badges.filter((b) => !b.earned));

const gekopieerd = ref(false);

// De deel-knop op de kaart brengt je naar het deel-vak hieronder.
const deelVak = ref<HTMLElement | null>(null);
const naarDelen = () => deelVak.value?.scrollIntoView({ behavior: 'smooth', block: 'center' });

const deelAan = () => router.post('/players/' + props.player.id + '/share', {}, { preserveScroll: true });

const deelUit = () => {
    if (confirm('De deel-link uitzetten? Wie de link heeft, kan de kaart daarna niet meer bekijken.')) {
        router.delete('/players/' + props.player.id + '/share', { preserveScroll: true });
    }
};

const kopieer = async () => {
    if (!props.share.url) {
        return;
    }

    await navigator.clipboard.writeText(props.share.url);
    gekopieerd.value = true;
    setTimeout(() => (gekopieerd.value = false), 2000);
};
</script>

<template>
    <Head :title="'Spelerskaart - ' + player.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-3xl p-4">
            <FlashMessage />

            <!--
                De kaart is het pronkstuk en bewust donker: dit is de
                speler/ouder-kant van het merk. Zie CLAUDE.md hoofdstuk 4.
            -->
            <div class="theme-donker rounded-3xl bg-background p-4 text-foreground sm:p-8">
                <PlayerCardVisual
                    :card="card"
                    :photo-href="photoHref"
                    :audience="canReport ? 'trainer' : 'gezin'"
                    :shareable="share.can && player.overall_rating !== null"
                    @share="naarDelen"
                />

                <p v-if="player.rated_at" class="mt-4 text-center text-xs text-muted-foreground">
                    Bijgewerkt op {{ player.rated_at }} &middot; gemiddelde van de laatste 3 rapporten
                </p>
            </div>

            <div v-if="goals.length" class="mt-4 rounded-xl border border-border bg-card p-5 shadow-sm">
                <p class="font-medium">Doelen</p>
                <p class="mt-1 text-xs text-muted-foreground">Waar de trainer met {{ player.name.split(' ')[0] }} naartoe werkt.</p>
                <GoalList class="mt-3" :goals="goals" />
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-3">
                <Link
                    :href="'/players/' + player.id + '/progress'"
                    class="inline-flex items-center gap-2 rounded-xl border border-border bg-card px-5 py-2.5 text-sm font-semibold shadow-sm transition hover:border-primary"
                >
                    <TrendingUp class="size-4" />
                    Bekijk de voortgang
                </Link>

                <Link
                    v-if="canReport"
                    :href="'/players/' + player.id + '/reports/create'"
                    class="inline-flex items-center rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                >
                    Nieuw rapport invullen
                </Link>
            </div>

            <!-- Mijlpalen voluit: wat is behaald, en wat is de volgende -->
            <div v-if="player.overall_rating" class="mt-4 rounded-xl border border-border bg-card p-5 shadow-sm">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <p class="font-medium">Mijlpalen</p>
                    <p class="tabular text-xs text-muted-foreground">{{ behaald.length }} van {{ badges.length }} behaald</p>
                </div>

                <div class="mt-4 grid gap-2 sm:grid-cols-2">
                    <div v-for="badge in behaald" :key="badge.key" class="flex items-start gap-3 rounded-xl border border-gold/30 bg-gold/10 p-3">
                        <Trophy class="mt-0.5 size-4 shrink-0 text-gold" />
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gold">{{ badge.label }}</p>
                            <p class="text-xs text-muted-foreground">{{ badge.description }}</p>
                        </div>
                    </div>

                    <div v-for="badge in nogTeGaan" :key="badge.key" class="flex items-start gap-3 rounded-xl border border-border p-3 opacity-70">
                        <Lock class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                        <div class="min-w-0">
                            <p class="text-sm font-medium">{{ badge.label }}</p>
                            <p class="text-xs text-muted-foreground">{{ badge.description }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="lastReport" class="mt-4 rounded-xl border border-border bg-card p-4 shadow-sm">
                <p class="text-sm font-medium">Laatste rapport</p>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ lastReport.reported_on }}<span v-if="lastReport.trainer"> door {{ lastReport.trainer }}</span>
                </p>
                <p v-if="lastReport.note" class="mt-3 text-sm">{{ lastReport.note }}</p>
            </div>

            <!-- Delen: standaard uit, en met de gevolgen erbij -->
            <div v-if="share.can && player.overall_rating" ref="deelVak" class="mt-4 rounded-xl border border-border bg-card p-5 shadow-sm">
                <p class="font-medium">Kaart delen</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    Maak een link waarmee iemand zonder account deze kaart kan bekijken. Op die pagina staan alleen de voornaam met initiaal, de
                    positie en de cijfers — geen achternaam, leeftijd, school of trainersnotities.
                </p>

                <template v-if="share.url">
                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <input
                            :value="share.url"
                            readonly
                            class="min-w-0 flex-1 rounded-lg border border-input bg-background px-3 py-2 text-xs text-muted-foreground"
                            @focus="($event.target as HTMLInputElement).select()"
                        />
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-lg border border-border px-3 py-2 text-sm font-medium transition hover:border-primary"
                            @click="kopieer"
                        >
                            <Check v-if="gekopieerd" class="size-4 text-primary" />
                            <Copy v-else class="size-4" />
                            {{ gekopieerd ? 'Gekopieerd' : 'Kopieer' }}
                        </button>
                    </div>

                    <button type="button" class="mt-3 text-sm font-medium text-destructive underline underline-offset-4" @click="deelUit">
                        Delen stoppen
                    </button>
                </template>

                <button
                    v-else
                    type="button"
                    class="mt-4 inline-flex items-center gap-2 rounded-xl border border-border px-4 py-2 text-sm font-medium transition hover:border-primary"
                    @click="deelAan"
                >
                    <Link2 class="size-4" />
                    Deel-link aanmaken
                </button>
            </div>
        </div>
    </AppLayout>
</template>
