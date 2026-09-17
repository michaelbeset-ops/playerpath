<script setup lang="ts">
import CardGlow from '@/components/CardGlow.vue';
import CardPersonalise from '@/components/CardPersonalise.vue';
import CardShareActions from '@/components/CardShareActions.vue';
import GoalList, { type Doel } from '@/components/GoalList.vue';
import LevelProgress from '@/components/LevelProgress.vue';
import PlayerCardVisual, { type Kaart } from '@/components/PlayerCardVisual.vue';
import ReportCelebration, { type RapportWijziging } from '@/components/ReportCelebration.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { groeiSticker } from '@/lib/cardImage';
import { strooiConfetti } from '@/lib/confetti';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import QRCode from 'qrcode';
import { Check, ChevronDown, Copy, Layers, Link2, Lock, Send, Smile, TrendingUp, Trophy } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

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
    /** Mag deze kijker de foto zetten: eigenaar, ouder van dit kind, het kind zelf. */
    canPhoto: boolean;
    reportCount: number;
    lastReport: { reported_on: string; trainer: string | null; note: string | null } | null;
    canReport: boolean;
    badges: { key: string; label: string; description: string; earned: boolean }[];
    share: { can: boolean; url: string | null };
    /** De kind-link: de kaart voor het kind zelf, zonder inlog. */
    childLink: { can: boolean; url: string | null };
    goals: Doel[];
}>();

// Bewust alleen de speler zelf: een kruimel naar /reports zou voor een ouder
// een dode link zijn.
// Wie voor de school werkt komt hier via de speler; een ouder of het kind
// zelf via het dashboard. Het pad zegt waar je terugkomt.
const breadcrumbs: BreadcrumbItem[] = props.canReport
    ? [
          { title: 'Spelers', href: '/clients' },
          { title: props.player.name, href: '/players/' + props.player.id },
          { title: 'Spelerskaart', href: '/players/' + props.player.id + '/card' },
      ]
    : [
          { title: 'Dashboard', href: '/dashboard' },
          { title: props.player.name, href: '/players/' + props.player.id + '/card' },
      ];

const behaald = computed(() => props.badges.filter((b) => b.earned));
const nogTeGaan = computed(() => props.badges.filter((b) => !b.earned));

// De viering na een zojuist opgeslagen rapport. Komt als flash mee, dus na een
// verversing is hij weg: hij hoort bij die ene opslag, niet bij de pagina.
const page = usePage();
const viering = ref<RapportWijziging | null>((page.props.flash as { reportResult?: RapportWijziging } | undefined)?.reportResult ?? null);

// Bij een level-up blijft het oude frame even staan, zodat je de kaart ziet
// upgraden in plaats van dat hij al klaar was toen de pagina laadde.
const vorigLevel = ref<string | null>(viering.value?.level.up ? viering.value.level.from.key : null);
const flits = ref(false);
const levelUpZichtbaar = ref(false);

// Foto en rugnummer: het vak onder de kaart. De knop op de kaart en de link
// "Foto toevoegen" op het dashboard (#foto) komen hier allebei uit.
const personaliseer = ref<InstanceType<typeof CardPersonalise> | null>(null);
const naarFoto = () => personaliseer.value?.open();

// Tijdens het uitsnijden beweegt de kaart mee: wat je schuift zie je meteen
// op de echte kaart, niet pas na het opslaan.
const voorbeeldFoto = ref<string | null>(null);

const kaartMetVoorbeeld = computed(() => (voorbeeldFoto.value ? { ...props.card, photo: voorbeeldFoto.value } : props.card));

onMounted(() => {
    if (window.location.hash === '#foto') {
        document.getElementById('foto')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    if (!viering.value?.level.up) {
        return;
    }

    setTimeout(() => {
        vorigLevel.value = null;
        flits.value = true;
        levelUpZichtbaar.value = true;
        strooiConfetti();
        setTimeout(() => (flits.value = false), 1200);
    }, 900);
});

// De openbare deel-link klapt uit: dicht is hij één regel met zijn stand.
// De kind-link staat altijd open: dat is de weg voor het kind zelf.
const deelOpen = ref(false);

// De deel-knop op de kaart brengt je naar het deel-vak hieronder.
const deelVak = ref<HTMLElement | null>(null);
const naarDelen = () => deelVak.value?.scrollIntoView({ behavior: 'smooth', block: 'center' });

const deelAan = () => router.post('/players/' + props.player.id + '/share', {}, { preserveScroll: true });

const deelUit = () => {
    if (confirm('De deel-link uitzetten? Wie de link heeft, kan de kaart daarna niet meer bekijken.')) {
        router.delete('/players/' + props.player.id + '/share', { preserveScroll: true });
    }
};

// Kopiëren, voor de deel-link en de kind-link. Eén teller: je kopieert er
// maar een tegelijk.
const gekopieerd = ref<string | null>(null);
const kopieer = async (welke: string, url: string | null) => {
    if (!url) {
        return;
    }
    await navigator.clipboard.writeText(url);
    gekopieerd.value = welke;
    setTimeout(() => (gekopieerd.value = null), 2000);
};

// De kind-link: aanmaken (of vernieuwen) en intrekken. Een QR-code erbij,
// want een kind zonder telefoon scant hem van het scherm van zijn ouder.
const kindLinkAan = () => router.post('/players/' + props.player.id + '/kind-link', {}, { preserveScroll: true });

const kindLinkVernieuw = () => {
    if (confirm('Een nieuwe link maken? De oude link werkt daarna niet meer.')) {
        router.post('/players/' + props.player.id + '/kind-link', {}, { preserveScroll: true });
    }
};

const kindLinkUit = () => {
    if (confirm('De link van ' + props.card.first_name + ' uitzetten? De kaart is daarna via die link niet meer te bekijken.')) {
        router.delete('/players/' + props.player.id + '/kind-link', { preserveScroll: true });
    }
};

// Sturen naar het kind: via het deelmenu van de telefoon (WhatsApp, sms,
// AirDrop), anders kopiëren. "Deel dit met je kind" moet een knop zijn,
// geen opdracht om zelf een link over te typen.
const stuurNaarKind = async () => {
    const url = props.childLink.url;

    if (!url) {
        return;
    }

    const tekst = 'Hoi ' + props.card.first_name + '! Dit is jouw spelerskaart. Open de link en zet hem op je beginscherm.';

    if (navigator.share) {
        try {
            await navigator.share({ title: 'Kaart van ' + props.card.first_name, text: tekst, url });
            return;
        } catch {
            // Geannuleerd of niet gelukt: dan kopiëren we hem.
        }
    }

    await kopieer('kind', url);
};

const qr = ref<string | null>(null);
const tekenQr = async () => {
    qr.value = props.childLink.url ? await QRCode.toDataURL(props.childLink.url, { width: 320, margin: 1 }) : null;
};
watch(() => props.childLink.url, tekenQr, { immediate: true });

// Net een level erbij? Dan zegt de sticker op de deel-afbeelding dat;
// anders de groei van het laatste rapport, als die de moeite waard is.
const sticker = computed(() => (viering.value?.level.up ? 'NIEUW LEVEL' : groeiSticker(props.card)));
</script>

<template>
    <Head :title="'Spelerskaart - ' + player.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <ReportCelebration v-if="viering" :result="viering" :level-up-visible="levelUpZichtbaar" @close="viering = null" />

        <div class="mx-auto w-full max-w-3xl p-4">

            <!--
                De kaart is het pronkstuk en bewust donker: dit is de
                speler/ouder-kant van het merk. Zie CLAUDE.md hoofdstuk 4.
            -->
            <div class="theme-donker overflow-hidden rounded-3xl bg-background p-4 text-foreground sm:p-8" data-tour="player-card">
                <CardGlow :level="vorigLevel ?? (player.overall_rating === null && card.card_mode !== 'inzet' ? 'geen' : card.level.key)">
                    <PlayerCardVisual
                        :card="kaartMetVoorbeeld"
                        :photo-action="canPhoto && !player.photo && !voorbeeldFoto"
                        :audience="canReport ? 'trainer' : 'gezin'"
                        :shareable="share.can && (player.overall_rating !== null || card.card_mode === 'inzet')"
                        :display-level="vorigLevel"
                        :flash="flits"
                        @share="naarDelen"
                        @photo="naarFoto"
                    />
                </CardGlow>

                <p v-if="player.rated_at && card.card_mode !== 'inzet'" class="mt-4 text-center text-xs text-muted-foreground">
                    Bijgewerkt op {{ player.rated_at }} &middot; gemiddelde van de laatste rapporten
                </p>
            </div>

            <!-- Waar je staat tussen brons en goud, met "Hoe werkt dit?" -->
            <LevelProgress class="mt-3" :card="card" />

            <!-- Foto en rugnummer: valt op tot het af is, daarna één regel -->
            <CardPersonalise
                v-if="canPhoto"
                ref="personaliseer"
                class="mt-4"
                :player="{ id: player.id, name: player.name, photo: player.photo }"
                :card="card"
                @preview="voorbeeldFoto = $event"
                @uploaded="(eerste) => eerste && strooiConfetti()"
            />

            <div v-if="goals.length" class="mt-4 rounded-xl border border-border bg-card p-5 shadow-sm">
                <p class="font-medium">Doelen</p>
                <p class="mt-1 text-xs text-muted-foreground">Waar de trainer met {{ player.name.split(' ')[0] }} naartoe werkt.</p>
                <GoalList class="mt-3" :goals="goals" />
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-3">
                <Link
                    :href="'/players/' + player.id + '/progress'"
                    class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-border bg-card px-5 py-2.5 text-sm font-semibold shadow-sm transition hover:border-primary"
                >
                    <TrendingUp class="size-4" />
                    Bekijk de voortgang
                </Link>

                <Link
                    :href="'/players/' + player.id + '/kaarten'"
                    class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-border bg-card px-5 py-2.5 text-sm font-semibold shadow-sm transition hover:border-primary"
                >
                    <Layers class="size-4" />
                    Mijn kaarten
                </Link>

                <Link
                    v-if="canReport && card.card_mode !== 'inzet'"
                    :href="'/players/' + player.id + '/reports/create'"
                    class="inline-flex min-h-11 items-center rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                >
                    Nieuw rapport invullen
                </Link>
            </div>

            <!-- Mijlpalen voluit: wat is behaald, en wat is de volgende -->
            <div v-if="badges.length" class="mt-4 rounded-xl border border-border bg-card p-5 shadow-sm">
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

            <!-- Delen: één vak. Bovenaan de knop; daaronder de twee links als
                 regels die uitklappen, zodat de pagina niet drie keer "delen" zegt. -->
            <div v-if="player.overall_rating || card.card_mode === 'inzet' || childLink.can" ref="deelVak" class="mt-4 overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <div v-if="player.overall_rating || card.card_mode === 'inzet'" class="p-5">
                    <p class="font-medium">Kaart delen</p>
                    <p class="mt-1 text-sm text-muted-foreground">Een plaatje van de kaart in story-formaat, met het logo van de school.</p>
                    <CardShareActions class="mt-3" :card="card" :link="share.url" :sticker="sticker" />
                </div>

                <!-- De kind-link: de weg voor het kind zelf. Altijd open en in
                     drie stappen, want "deel dit met je kind en zet het op het
                     beginscherm" moet je niet hoeven uitzoeken. -->
                <div v-if="childLink.can" class="bg-primary/5 p-5" :class="{ 'border-t border-border': player.overall_rating }">
                    <div class="flex items-start gap-3">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/15 text-primary"><Smile class="size-5" /></span>
                        <div class="min-w-0">
                            <p class="font-semibold">Deel de kaart met {{ card.first_name }}</p>
                            <p class="mt-0.5 text-sm text-muted-foreground">
                                Een eigen link voor de telefoon of tablet van je kind. Daarmee kan {{ card.first_name }} de kaart altijd bekijken, zonder
                                wachtwoord, en ziet het na elke training wat er veranderd is.
                            </p>
                        </div>
                    </div>

                    <ol class="mt-5 space-y-4">
                        <!-- 1. Sturen -->
                        <li class="flex gap-3">
                            <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground">1</span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium">Stuur de link naar {{ card.first_name }}</p>

                                <template v-if="childLink.url">
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <button
                                            type="button"
                                            class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                                            @click="stuurNaarKind"
                                        >
                                            <Send class="size-4" />
                                            Stuur naar {{ card.first_name }}
                                        </button>
                                        <button
                                            type="button"
                                            class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-border bg-card px-3 text-sm font-medium transition hover:border-primary"
                                            @click="kopieer('kind', childLink.url)"
                                        >
                                            <Check v-if="gekopieerd === 'kind'" class="size-4 text-primary" />
                                            <Copy v-else class="size-4" />
                                            {{ gekopieerd === 'kind' ? 'Gekopieerd' : 'Kopiëren' }}
                                        </button>
                                    </div>

                                    <div v-if="qr" class="mt-3 flex items-center gap-3 rounded-xl border border-border bg-card p-3">
                                        <img :src="qr" alt="QR-code van de link" class="size-24 shrink-0 rounded-lg bg-white p-1" />
                                        <p class="text-xs text-muted-foreground">
                                            Staat {{ card.first_name }} naast je? Laat de camera van de telefoon of tablet deze code scannen.
                                        </p>
                                    </div>
                                </template>

                                <button
                                    v-else
                                    type="button"
                                    class="mt-2 inline-flex min-h-11 items-center gap-2 rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                                    @click="kindLinkAan"
                                >
                                    <Link2 class="size-4" />
                                    Link voor {{ card.first_name }} maken
                                </button>
                            </div>
                        </li>

                        <!-- 2. Openen -->
                        <li class="flex gap-3">
                            <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground">2</span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium">{{ card.first_name }} opent de link</p>
                                <p class="mt-0.5 text-sm text-muted-foreground">
                                    Meteen op het eigen account, en het blijft ingelogd. De kaart, de voortgang en Mijn kaarten zijn daarna altijd te
                                    bekijken.
                                </p>
                            </div>
                        </li>

                        <!-- 3. Beginscherm -->
                        <li class="flex gap-3">
                            <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground">3</span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium">Zet de app op het beginscherm</p>
                                <p class="mt-0.5 text-sm text-muted-foreground">
                                    Dan staat de kaart als app tussen de andere apps en is hij met één tik open. De app vraagt het zelf zodra de link
                                    geopend is.
                                </p>
                                <ul class="mt-2 space-y-1 text-xs text-muted-foreground">
                                    <li><span class="font-medium text-foreground">iPhone of iPad:</span> tik op Deel, kies Zet op beginscherm</li>
                                    <li><span class="font-medium text-foreground">Android:</span> tik op de drie puntjes, kies App installeren</li>
                                </ul>
                            </div>
                        </li>
                    </ol>

                    <div v-if="childLink.url" class="mt-4 flex flex-wrap gap-4 border-t border-border pt-3">
                        <button type="button" class="inline-flex min-h-11 items-center text-xs font-medium text-muted-foreground underline underline-offset-4 hover:text-foreground" @click="kindLinkVernieuw">
                            Nieuwe link maken
                        </button>
                        <button type="button" class="inline-flex min-h-11 items-center text-xs font-medium text-destructive underline underline-offset-4" @click="kindLinkUit">
                            Link uitzetten
                        </button>
                    </div>
                </div>

                <!-- De deel-link -->
                <div v-if="share.can && (player.overall_rating || card.card_mode === 'inzet')" class="border-t border-border">
                    <button type="button" class="flex min-h-16 w-full items-center gap-3 px-5 py-3 text-left transition hover:bg-secondary/40" @click="deelOpen = !deelOpen">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-secondary text-muted-foreground"><Link2 class="size-4" /></span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-medium">Openbare deel-link</span>
                            <span class="block text-xs text-muted-foreground">Voornaam met initiaal en {{ card.card_mode === 'inzet' ? 'de punten' : 'de cijfers' }}, voor iedereen met de link</span>
                        </span>
                        <span
                            class="shrink-0 rounded-full px-2 py-0.5 text-xs font-semibold"
                            :class="share.url ? 'bg-primary/10 text-primary' : 'bg-secondary text-muted-foreground'"
                            >{{ share.url ? 'Aan' : 'Uit' }}</span
                        >
                        <ChevronDown class="size-4 shrink-0 text-muted-foreground transition" :class="{ 'rotate-180': deelOpen }" />
                    </button>

                    <div v-if="deelOpen" class="px-5 pb-5">
                        <p class="text-sm text-muted-foreground">
                            Iedereen met deze link ziet de kaart zonder account. Er staan alleen de voornaam met initiaal, de positie en {{ card.card_mode === 'inzet' ? 'het level en de punten' : 'de cijfers' }} op: geen
                            achternaam, leeftijd, school of notities van de trainer.
                        </p>

                        <template v-if="share.url">
                            <div class="mt-4 flex flex-wrap items-center gap-2">
                                <input
                                    :value="share.url"
                                    readonly
                                    class="min-h-11 min-w-0 flex-1 rounded-lg border border-input bg-background px-3 py-2 text-xs text-muted-foreground"
                                    @focus="($event.target as HTMLInputElement).select()"
                                />
                                <button
                                    type="button"
                                    class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-border px-3 py-2 text-sm font-medium transition hover:border-primary"
                                    @click="kopieer('deel', share.url)"
                                >
                                    <Check v-if="gekopieerd === 'deel'" class="size-4 text-primary" />
                                    <Copy v-else class="size-4" />
                                    {{ gekopieerd === 'deel' ? 'Gekopieerd' : 'Kopieer' }}
                                </button>
                            </div>
                            <button type="button" class="mt-2 inline-flex min-h-11 items-center text-sm font-medium text-destructive underline underline-offset-4" @click="deelUit">
                                Uitzetten
                            </button>
                        </template>

                        <button
                            v-else
                            type="button"
                            class="mt-4 inline-flex min-h-11 items-center gap-2 rounded-xl border border-border px-4 text-sm font-medium transition hover:border-primary"
                            @click="deelAan"
                        >
                            <Link2 class="size-4" />
                            Deel-link aanmaken
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
