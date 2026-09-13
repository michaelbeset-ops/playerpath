<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import CardGlow from '@/components/CardGlow.vue';
import CardHowItWorks from '@/components/CardHowItWorks.vue';
import LevelProgress from '@/components/LevelProgress.vue';
import PlayerCardVisual, { type Kaart } from '@/components/PlayerCardVisual.vue';
import InstallSteps from '@/components/InstallSteps.vue';
import { useInstall } from '@/composables/useInstall';
import { strooiConfetti } from '@/lib/confetti';
import { Head } from '@inertiajs/vue3';
import { Download, Layers, Lock, Trophy } from 'lucide-vue-next';
import { computed, onMounted } from 'vue';

/**
 * De kaart voor het kind zelf, via de kind-link. Geen inlog, geen menu:
 * alleen wat van hem is. De hele kaart, de balk naar het volgende level,
 * de mijlpalen en de verzameling van vorige seizoenen.
 *
 * Wat er bewust niet staat: knoppen die het kind niet mag gebruiken (foto
 * wijzigen, delen, rugnummer) en wat een trainer over hem opschreef.
 */
const props = defineProps<{
    card: Kaart;
    seasons: Kaart[];
    badges: { key: string; label: string; description: string; earned: boolean }[];
    schoolName: string | null;
    schoolLogo: string | null;
    manifestUrl: string;
}>();

const behaald = computed(() => props.badges.filter((b) => b.earned));
const nogTeGaan = computed(() => props.badges.filter((b) => !b.earned));

// Een nieuw level sinds de vorige keer kijken? Dan confetti. Onthouden op
// dit apparaat, want dit is de tablet van het kind.
const LEVEL_SLEUTEL = 'pp.kind.level.' + props.card.card_number;

onMounted(() => {
    try {
        const vorige = localStorage.getItem(LEVEL_SLEUTEL);
        const nu = props.card.overall === null ? '' : props.card.level.key;

        if (vorige !== null && vorige !== '' && nu !== '' && vorige !== nu) {
            const volgorde = props.card.levels.map((l) => l.key);
            if (volgorde.indexOf(nu) > volgorde.indexOf(vorige)) {
                setTimeout(strooiConfetti, 600);
            }
        }

        localStorage.setItem(LEVEL_SLEUTEL, nu);
    } catch {
        // Geen opslag: dan geen confetti, meer niet.
    }
});

// "Zet op je beginscherm": hier meteen, want dit is precies de link die op
// de tablet als icoon hoort te staan. Als het al een app is, staat er niets.
const { kan } = useInstall();
</script>

<template>
    <Head :title="'Kaart van ' + card.first_name">
        <meta name="robots" content="noindex, nofollow" />
        <link rel="manifest" :href="manifestUrl" />
    </Head>

    <div class="theme-donker min-h-svh bg-background text-foreground">
        <div class="mx-auto w-full max-w-md px-4 py-6">
            <!-- De school van het kind: klein, bovenaan -->
            <div v-if="schoolName" class="flex items-center justify-center gap-2 text-xs text-muted-foreground">
                <img v-if="schoolLogo" :src="schoolLogo" alt="" class="size-6 rounded-md object-contain" />
                <span>{{ schoolName }}</span>
            </div>

            <div class="relative mt-3 text-center">
                <div class="pointer-events-none absolute inset-x-0 -top-10 mx-auto h-40 w-64 rounded-full bg-primary/20 blur-3xl" aria-hidden="true"></div>
                <h1 class="relative text-4xl font-extrabold tracking-tight">Hoi {{ card.first_name }}!</h1>
                <p class="relative mt-2 text-sm text-muted-foreground">
                    Dit is jouw eigen spelerskaart. Elke training en elk rapport telt mee, en je ziet hem hier groeien.
                </p>
            </div>

            <div class="mt-6">
                <CardGlow :level="card.overall === null ? 'geen' : card.level.key">
                    <PlayerCardVisual :card="card" :shareable="false" audience="gezin" />
                </CardGlow>
            </div>

            <p v-if="card.overall === null" class="mt-4 text-center text-sm text-muted-foreground">
                Na je eerste rapport komt je kaart tot leven: dan staan hier je cijfers.
            </p>

            <LevelProgress v-if="card.overall !== null" class="mt-4" :card="card" />

            <!-- Op het beginscherm: dit is de plek waar de link een icoon wordt -->
            <div v-if="kan" class="mt-4 rounded-2xl border border-border bg-card p-4">
                <p class="flex items-center gap-2 text-sm font-semibold">
                    <Download class="size-4 text-primary" />
                    Zet je kaart op je beginscherm
                </p>
                <p class="mt-1 text-xs text-muted-foreground">Dan staat hij als een app tussen je andere apps, met één tik open.</p>
                <InstallSteps class="mt-3" :naam="'je kaart'" />
            </div>

            <CardHowItWorks class="mt-8" :card="card" />

            <!-- Mijlpalen: wat je hebt, en wat je nog kunt halen -->
            <div v-if="card.overall !== null && badges.length" class="mt-4 rounded-2xl border border-border bg-card p-4">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <p class="font-semibold">Mijn mijlpalen</p>
                    <p class="tabular text-xs text-muted-foreground">{{ behaald.length }} van {{ badges.length }}</p>
                </div>

                <div class="mt-3 grid gap-2">
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

            <!-- Mijn kaarten: de verzameling -->
            <section class="mt-8">
                <h2 class="flex items-center gap-2 font-semibold">
                    <Layers class="size-4 text-primary" />
                    Mijn kaarten
                </h2>

                <div v-if="seasons.length" class="mt-3 grid gap-4">
                    <div v-for="kaart in seasons" :key="kaart.season + (kaart.age_category?.key ?? '')">
                        <p class="mb-2 text-center text-xs font-semibold uppercase tracking-widest text-muted-foreground">
                            Seizoen {{ kaart.season }}<template v-if="kaart.age_category"> · {{ kaart.age_category.key }}</template>
                        </p>
                        <CardGlow :level="kaart.overall === null ? 'geen' : kaart.level.key">
                            <PlayerCardVisual :card="kaart" :shareable="false" />
                        </CardGlow>
                    </div>
                </div>

                <div v-else class="mt-3 rounded-2xl border border-dashed border-border bg-card p-4 text-sm text-muted-foreground">
                    <p class="font-medium text-foreground">Nog geen oude kaarten</p>
                    <p class="mt-1">
                        Aan het eind van elk seizoen bewaren we je kaart zoals hij dan is: met je cijfers en je level. Daarna beginnen de punten
                        opnieuw en spaar je voor de volgende. Zo bouw je een echte verzameling op.
                    </p>
                </div>
            </section>

            <div class="mt-10 flex flex-col items-center gap-1 text-xs text-muted-foreground">
                <div class="flex items-center gap-2">
                    <AppLogoIcon class="size-4 rounded-sm" />
                    Spelerskaart van PlayerPath
                </div>
                <p>Bewaar deze pagina: je kaart is hier altijd te vinden.</p>
            </div>
        </div>
    </div>
</template>
