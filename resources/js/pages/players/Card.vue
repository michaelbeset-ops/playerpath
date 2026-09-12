<script setup lang="ts">
import CardGlow from '@/components/CardGlow.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import GoalList, { type Doel } from '@/components/GoalList.vue';
import PhotoUpload from '@/components/PhotoUpload.vue';
import PlayerCardVisual, { type Kaart } from '@/components/PlayerCardVisual.vue';
import ReportCelebration, { type RapportWijziging } from '@/components/ReportCelebration.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { deelKaartAlsAfbeelding, groeiSticker } from '@/lib/cardImage';
import { strooiConfetti } from '@/lib/confetti';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Camera, Check, Copy, ImageDown, Layers, Link2, Lock, Share2, TrendingUp, Trophy } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

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

// De foto: het vak onder de kaart. De knop op de kaart en de link
// "Foto toevoegen" op het dashboard (#foto) komen hier allebei uit.
const fotoBlok = ref<HTMLElement | null>(null);
const fotoKiezer = ref<InstanceType<typeof PhotoUpload> | null>(null);

const naarFoto = () => {
    fotoBlok.value?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    fotoKiezer.value?.open();
};

// Tijdens het uitsnijden beweegt de kaart mee: wat je schuift zie je meteen
// op de echte kaart, niet pas na het opslaan.
const voorbeeldFoto = ref<string | null>(null);

// Het rugnummer: versiering van de eigen kaart, dus hier bij de foto en niet
// alleen in het spelersformulier van de school.
const rugnummer = useForm({ shirt_number: props.card.shirt_number ?? ('' as number | '') });
const bewaarRugnummer = () => rugnummer.patch('/players/' + props.player.id + '/rugnummer', { preserveScroll: true });
const kaartMetVoorbeeld = computed(() => (voorbeeldFoto.value ? { ...props.card, photo: voorbeeldFoto.value } : props.card));

onMounted(() => {
    if (window.location.hash === '#foto') {
        fotoBlok.value?.scrollIntoView({ behavior: 'smooth', block: 'center' });
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

// De kaart als afbeelding, in story-formaat: via het deelmenu van de
// telefoon, en anders als download. De link gaat mee als hij aanstaat.
const deelBezig = ref(false);
const deelMelding = ref<string | null>(null);

const deelAfbeelding = async () => {
    deelBezig.value = true;
    deelMelding.value = null;

    // Net een level erbij? Dan zegt de sticker dat; anders de groei van het
    // laatste rapport, als die de moeite waard is.
    const sticker = viering.value?.level.up ? 'NIEUW LEVEL' : groeiSticker(props.card);
    const uitkomst = await deelKaartAlsAfbeelding(props.card, props.share.url, sticker);

    deelBezig.value = false;
    deelMelding.value = {
        gedeeld: null,
        geannuleerd: null,
        gedownload: 'De afbeelding is gedownload. Deel hem vanuit je galerij of downloads.',
        mislukt: 'Het maken van de afbeelding is niet gelukt. Probeer het nog eens.',
    }[uitkomst];
};
</script>

<template>
    <Head :title="'Spelerskaart - ' + player.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <ReportCelebration v-if="viering" :result="viering" :level-up-visible="levelUpZichtbaar" @close="viering = null" />

        <div class="mx-auto w-full max-w-3xl p-4">
            <FlashMessage />

            <!--
                De kaart is het pronkstuk en bewust donker: dit is de
                speler/ouder-kant van het merk. Zie CLAUDE.md hoofdstuk 4.
            -->
            <div class="theme-donker overflow-hidden rounded-3xl bg-background p-4 text-foreground sm:p-8" data-tour="player-card">
                <CardGlow :level="vorigLevel ?? (player.overall_rating === null ? 'geen' : card.level.key)">
                    <PlayerCardVisual
                        :card="kaartMetVoorbeeld"
                        :photo-action="canPhoto && !player.photo && !voorbeeldFoto"
                        :audience="canReport ? 'trainer' : 'gezin'"
                        :shareable="share.can && player.overall_rating !== null"
                        :display-level="vorigLevel"
                        :flash="flits"
                        @share="naarDelen"
                        @photo="naarFoto"
                    />
                </CardGlow>

                <p v-if="player.rated_at" class="mt-4 text-center text-xs text-muted-foreground">
                    Bijgewerkt op {{ player.rated_at }} &middot; gemiddelde van de laatste 3 rapporten
                </p>
            </div>

            <!-- De foto. Zonder foto valt het vak op (groene rand): dat is de
                 blijvende herinnering. Met foto is het een rustig vak om hem
                 te vervangen. -->
            <div
                v-if="canPhoto"
                id="foto"
                ref="fotoBlok"
                class="mt-4 rounded-xl border bg-card p-5 shadow-sm"
                :class="player.photo ? 'border-border' : 'border-primary/50'"
            >
                <p class="flex items-center gap-2 font-medium">
                    <Camera class="size-4 text-primary" />
                    {{ player.photo ? 'Foto op de kaart' : 'Zet een foto op de kaart' }}
                </p>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{
                        player.photo
                            ? 'Deze foto staat op de kaart, ook op een gedeelde kaart.'
                            : 'Een kaart met een gezicht erop is de helft meer waard. Maak een foto of kies er een; je snijdt hem daarna uit.'
                    }}
                </p>
                <PhotoUpload
                    ref="fotoKiezer"
                    class="mt-3"
                    :name="player.name"
                    :photo="player.photo"
                    :action="'/players/' + player.id + '/photo'"
                    :kaart="{ first_name: card.first_name, last_name: card.last_name, overall: card.overall, position: card.position }"
                    @preview="voorbeeldFoto = $event"
                    @uploaded="(eerste) => eerste && strooiConfetti()"
                />

                <form class="mt-4 flex flex-wrap items-end gap-2 border-t border-border pt-4" @submit.prevent="bewaarRugnummer">
                    <div class="grid gap-1">
                        <label for="rugnummer" class="text-sm font-medium">Rugnummer</label>
                        <input
                            id="rugnummer"
                            v-model="rugnummer.shirt_number"
                            type="number"
                            inputmode="numeric"
                            min="1"
                            max="99"
                            placeholder="10"
                            class="tabular h-11 w-24 rounded-lg border border-input bg-background px-3 text-center text-lg font-semibold outline-none focus:border-primary"
                        />
                    </div>
                    <button
                        type="submit"
                        class="inline-flex h-11 items-center rounded-lg border border-border bg-card px-4 text-sm font-medium transition hover:border-primary disabled:opacity-60"
                        :disabled="rugnummer.processing"
                    >
                        Op de kaart zetten
                    </button>
                    <p class="basis-full text-xs text-muted-foreground">Staat groot op de foto, zoals op een shirt. Leeg laten mag.</p>
                    <p v-if="rugnummer.errors.shirt_number" class="basis-full text-sm text-destructive">{{ rugnummer.errors.shirt_number }}</p>
                </form>
            </div>

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
                    v-if="canReport"
                    :href="'/players/' + player.id + '/reports/create'"
                    class="inline-flex min-h-11 items-center rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
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

            <!-- Delen als afbeelding: voor iedereen die de kaart mag zien. Dit
                 is wat een kind in de groepsapp zet: een plaatje, geen link. -->
            <div v-if="player.overall_rating" ref="deelVak" class="mt-4 rounded-xl border border-border bg-card p-5 shadow-sm">
                <p class="font-medium">Kaart delen als afbeelding</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    Een plaatje van de kaart in story-formaat, voor WhatsApp of Instagram. Er staat op wat je hier ziet: naam, positie, cijfers en level.
                </p>

                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground transition hover:opacity-90 disabled:opacity-60"
                        :disabled="deelBezig"
                        @click="deelAfbeelding"
                    >
                        <Share2 v-if="!deelBezig" class="size-4" />
                        <ImageDown v-else class="size-4 animate-pulse" />
                        {{ deelBezig ? 'Afbeelding maken…' : 'Deel als afbeelding' }}
                    </button>

                    <button
                        v-if="share.url"
                        type="button"
                        class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-border px-4 text-sm font-medium transition hover:border-primary"
                        @click="kopieer"
                    >
                        <Check v-if="gekopieerd" class="size-4 text-primary" />
                        <Copy v-else class="size-4" />
                        {{ gekopieerd ? 'Link gekopieerd' : 'Link kopiëren' }}
                    </button>
                </div>

                <p v-if="deelMelding" class="mt-3 text-sm text-muted-foreground" role="status">{{ deelMelding }}</p>
            </div>

            <!-- De deel-link: standaard uit, en met de gevolgen erbij -->
            <div v-if="share.can && player.overall_rating" class="mt-4 rounded-xl border border-border bg-card p-5 shadow-sm">
                <p class="font-medium">Deel-link</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    Maak een link waarmee iemand zonder account deze kaart kan bekijken. Op die pagina staan alleen de voornaam met initiaal, de
                    positie en de cijfers — geen achternaam, leeftijd, school of trainersnotities.
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
                            @click="kopieer"
                        >
                            <Check v-if="gekopieerd" class="size-4 text-primary" />
                            <Copy v-else class="size-4" />
                            {{ gekopieerd ? 'Gekopieerd' : 'Kopieer' }}
                        </button>
                    </div>

                    <button
                        type="button"
                        class="mt-3 inline-flex min-h-11 items-center text-sm font-medium text-destructive underline underline-offset-4"
                        @click="deelUit"
                    >
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
