<script setup lang="ts">
import RatingExplanation from '@/components/RatingExplanation.vue';
import { Link } from '@inertiajs/vue3';
import {
    Award,
    CalendarCheck,
    Camera,
    CircleHelp,
    ClipboardCheck,
    Hand,
    Medal,
    Rocket,
    Share2,
    Shirt,
    Star,
    Target,
    TrendingUp,
    Trophy,
    UserRound,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

/**
 * De spelerskaart als verzamelkaart.
 *
 * Eén component voor de kaartpagina, het gezinsdashboard en de publieke
 * deel-link; de gegevens komen uit PlayerCardPresenter, zodat die drie nooit
 * uit elkaar lopen.
 *
 * Het level zie je aan het frame, niet aan een pilletje: koper voor brons,
 * chroom voor zilver, een warme gloed voor goud en een iriserend, langzaam
 * bewegend frame voor elite. Eigen identiteit, geen FUT-kopie: geen schild,
 * geen vlag, geen clublogo. Wel een grote foto, een groot cijfer en de zes
 * categorieën voluit — een kind van acht hoort geen "INZ" te hoeven raden.
 *
 * Mobiel-first: maximaal 22.5rem breed en daaronder schaalt alles mee.
 */
export interface Kaart {
    first_name: string;
    last_name: string;
    name: string;
    photo: string | null;
    position: string;
    position_key: 'keeper' | 'field';
    age_category: { key: string; label: string } | null;
    moved_up: boolean;
    overall: number | null;
    categories: { category: string; label: string; hint?: string; rating: number | null }[];
    report_count: number;
    level: {
        key: string;
        label: string;
        xp: number;
        next: { key: string; label: string; xp: number; remaining: number } | null;
        progress: number;
    };
    levels: { key: string; label: string; xp: number }[];
    badges: { key: string; label: string; description: string }[];
    season: string;
    school: string | null;
}

const props = withDefaults(
    defineProps<{
        card: Kaart;
        /** Waar je een foto toevoegt; leeg als deze kijker dat niet mag. */
        photoHref?: string | null;
        /** Voor wie de uitleg is: het gezin, of de trainer die de cijfers geeft. */
        audience?: 'gezin' | 'trainer';
        /** De deel-knop; op de gedeelde pagina zelf is die zinloos. */
        shareable?: boolean;
        /**
         * Toon tijdelijk een ander level dan de speler heeft.
         *
         * Alleen voor de viering na een rapport: dan blijft het oude frame
         * even staan zodat je de upgrade ziet gebeuren in plaats van dat hij
         * er al was toen de pagina laadde.
         */
        displayLevel?: string | null;
        /** Speel de upgrade-flits af. */
        flash?: boolean;
    }>(),
    { photoHref: null, audience: 'gezin', shareable: true, displayLevel: null, flash: false },
);

const emit = defineEmits<{ share: [] }>();

const uitlegOpen = ref(false);

// Zonder rapport is er nog geen level: dan een neutraal, stalen frame.
const tier = computed(() => {
    if (props.displayLevel) {
        return props.displayLevel;
    }

    return props.card.overall === null ? 'geen' : props.card.level.key;
});

// Elke mijlpaal zijn eigen icoon, zodat drie badges naast elkaar van elkaar
// te onderscheiden zijn zonder het label te lezen.
const badgeIcoon: Record<string, unknown> = {
    eerste_rapport: ClipboardCheck,
    vijf_rapporten: Star,
    groei: TrendingUp,
    sterke_groei: Rocket,
    uitblinker: Award,
    compleet: Medal,
    doel_gehaald: Target,
    aanwezig_vijf: CalendarCheck,
    aanwezig_tien: Trophy,
};

const icoonVoor = (key: string) => badgeIcoon[key] ?? Trophy;

// De drie meest recente mijlpalen, en "nieuwe categorie" gaat daar bewust
// vóór: een speler die net een jaargang omhoog is gegaan verdient dat te zien.
const badges = computed(() => {
    const lijst = [...props.card.badges].reverse().slice(0, 3);

    if (props.card.moved_up && props.card.age_category) {
        lijst.unshift({ key: 'nieuwe_categorie', label: props.card.age_category.key, description: 'Nieuwe leeftijdscategorie' });
    }

    return lijst.slice(0, 3);
});

const balk = (rating: number | null) => (rating === null ? '0%' : rating + '%');

const upgradeTekst = computed(() => {
    const next = props.card.level.next;

    if (props.card.overall === null) {
        return 'Je eerste rapport zet de kaart aan';
    }

    if (next === null) {
        return 'Het hoogste level bereikt';
    }

    return `Nog ${next.remaining} ${next.remaining === 1 ? 'punt' : 'punten'} tot je volgende upgrade`;
});
</script>

<template>
    <div class="pp-wrap" :class="['pp-tier-' + tier, 'pp-' + card.position_key, { 'pp-puls': flash }]">
        <div class="pp-frame">
            <div class="pp-frame-glans" aria-hidden="true"></div>
            <div v-if="flash" class="pp-flits" aria-hidden="true"></div>

            <div class="pp-binnen">
                <!-- Boven: de foto met daaroverheen het cijfer en de badges -->
                <div class="pp-foto-vak">
                    <img v-if="card.photo" :src="card.photo" :alt="card.name" class="pp-foto" />

                    <div v-else class="pp-silhouet">
                        <UserRound class="pp-silhouet-icoon" aria-hidden="true" />
                        <Link v-if="photoHref" :href="photoHref" class="pp-foto-knop inline-flex min-h-11 items-center">
                            <Camera class="size-3.5" />
                            Foto toevoegen
                        </Link>
                    </div>

                    <div class="pp-foto-fade" aria-hidden="true"></div>

                    <p class="pp-merk">PlayerPath</p>

                    <div class="pp-overall-blok">
                        <p class="pp-overall tabular">{{ card.overall ?? '—' }}</p>
                        <p class="pp-positie">
                            <Hand v-if="card.position_key === 'keeper'" class="size-3.5" aria-hidden="true" />
                            <Shirt v-else class="size-3.5" aria-hidden="true" />
                            {{ card.position }}
                        </p>
                        <p v-if="card.age_category" class="pp-categorie" :title="card.age_category.label">{{ card.age_category.key }}</p>
                    </div>

                    <ul v-if="badges.length" class="pp-badges" aria-label="Mijlpalen">
                        <li v-for="badge in badges" :key="badge.key" class="pp-badge" :title="badge.description">
                            <span class="pp-badge-icoon">
                                <component :is="icoonVoor(badge.key)" class="size-3.5" aria-hidden="true" />
                            </span>
                            <span class="pp-badge-label">{{ badge.label }}</span>
                        </li>
                    </ul>
                </div>

                <!-- Onder: naam, cijfers, XP en de voet -->
                <div class="pp-body">
                    <div class="pp-patroon" aria-hidden="true"></div>

                    <div class="pp-naam">
                        <p class="pp-voornaam">{{ card.first_name }}</p>
                        <p class="pp-achternaam">{{ card.last_name }}</p>
                    </div>

                    <div v-if="card.overall !== null" class="pp-stats">
                        <div v-for="c in card.categories" :key="c.category" class="pp-stat">
                            <div class="pp-stat-regel">
                                <span class="pp-stat-label">{{ c.label }}</span>
                                <span class="pp-stat-cijfer tabular">{{ c.rating ?? '—' }}</span>
                            </div>
                            <div class="pp-balk">
                                <div class="pp-balk-vulling" :style="{ width: balk(c.rating) }"></div>
                            </div>
                        </div>
                    </div>

                    <p v-else class="pp-leeg">Zodra het eerste rapport binnen is, komt deze kaart tot leven.</p>

                    <div class="pp-xp">
                        <div class="pp-xp-regel">
                            <span class="pp-xp-label">XP</span>
                            <span class="pp-xp-cijfer tabular">{{ card.level.xp }}</span>
                        </div>
                        <div class="pp-balk pp-balk-xp">
                            <div class="pp-balk-vulling" :style="{ width: card.level.progress + '%' }"></div>
                        </div>
                        <p class="pp-xp-tekst">{{ upgradeTekst }}</p>
                    </div>

                    <div class="pp-voet">
                        <p class="pp-voet-regel">
                            Seizoen {{ card.season }}
                            <template v-if="card.overall !== null"> &middot; Level {{ card.level.label }}</template>
                        </p>
                        <p v-if="card.school" class="pp-school">{{ card.school }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Onder de kaart: uitleg en delen -->
        <div class="pp-acties">
            <button type="button" class="pp-actie" @click="uitlegOpen = true">
                <CircleHelp class="size-4" aria-hidden="true" />
                Hoe werkt mijn rating?
            </button>
            <button v-if="shareable" type="button" class="pp-actie" @click="emit('share')">
                <Share2 class="size-4" aria-hidden="true" />
                Delen
            </button>
        </div>

        <RatingExplanation v-model:open="uitlegOpen" :card="card" :audience="audience" />
    </div>
</template>

<style scoped>
/* ---------- Basis: de maten en de kleuren die elk level deelt ---------- */
.pp-wrap {
    --pp-facet: 1rem;
    --pp-rand: 0.4rem;
    --pp-surface-1: #131c30;
    --pp-surface-2: #0a0f1c;
    --pp-accent: #22e06b;
    --pp-tekst: #f1f5f9;
    --pp-tekst-zacht: #94a3b8;
    /* Per level overschreven: het metaal van het frame, de tint en de gloed. */
    --pp-tier: #94a3b8;
    --pp-tier-zacht: rgba(148, 163, 184, 0.18);
    --pp-gloed: rgba(148, 163, 184, 0.25);
    --pp-metaal: linear-gradient(135deg, #cfd6de 0%, #8c96a3 20%, #4b5563 38%, #d9dfe6 52%, #7b8592 68%, #3f4650 84%, #b5bdc7 100%);

    width: 100%;
    max-width: 22.5rem;
    margin-inline: auto;
    color: var(--pp-tekst);
    font-variant-numeric: tabular-nums;
}

/*
 * Het frame: metaal met facet-hoeken. De hoeken komen van een clip-path, en
 * omdat die ook de schaduw wegknipt zit de gloed als drop-shadow op de wrap.
 */
.pp-frame {
    position: relative;
    padding: var(--pp-rand);
    background: var(--pp-metaal);
    clip-path: polygon(
        var(--pp-facet) 0,
        calc(100% - var(--pp-facet)) 0,
        100% var(--pp-facet),
        100% calc(100% - var(--pp-facet)),
        calc(100% - var(--pp-facet)) 100%,
        var(--pp-facet) 100%,
        0 calc(100% - var(--pp-facet)),
        0 var(--pp-facet)
    );
    filter: drop-shadow(0 18px 30px rgba(0, 0, 0, 0.55)) drop-shadow(0 0 22px var(--pp-gloed));
}

/* De glans over het metaal: een lichtval linksboven en een diagonale streep. */
.pp-frame-glans {
    position: absolute;
    inset: 0;
    pointer-events: none;
    background:
        linear-gradient(115deg, transparent 32%, rgba(255, 255, 255, 0.55) 44%, rgba(255, 255, 255, 0.1) 50%, transparent 58%),
        radial-gradient(70% 40% at 10% 0%, rgba(255, 255, 255, 0.5), transparent 70%);
    mix-blend-mode: soft-light;
}

.pp-binnen {
    position: relative;
    overflow: hidden;
    background: linear-gradient(180deg, var(--pp-surface-1), var(--pp-surface-2));
    clip-path: polygon(
        calc(var(--pp-facet) - var(--pp-rand)) 0,
        calc(100% - var(--pp-facet) + var(--pp-rand)) 0,
        100% calc(var(--pp-facet) - var(--pp-rand)),
        100% calc(100% - var(--pp-facet) + var(--pp-rand)),
        calc(100% - var(--pp-facet) + var(--pp-rand)) 100%,
        calc(var(--pp-facet) - var(--pp-rand)) 100%,
        0 calc(100% - var(--pp-facet) + var(--pp-rand)),
        0 calc(var(--pp-facet) - var(--pp-rand))
    );
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.08);
}

/* ---------- De foto ---------- */
.pp-foto-vak {
    position: relative;
    aspect-ratio: 3 / 2;
    background: radial-gradient(80% 60% at 50% 30%, var(--pp-tier-zacht), transparent 70%), var(--pp-surface-1);
}

.pp-foto {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center top;
}

/* Zonder foto: een net silhouet, en voor wie het mag een knop. */
.pp-silhouet {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-end;
    gap: 0.5rem;
    height: 100%;
    padding-bottom: 2.75rem;
}

.pp-silhouet-icoon {
    width: 5.5rem;
    height: 5.5rem;
    color: var(--pp-tier);
    opacity: 0.35;
}

.pp-foto-knop {
    position: relative;
    z-index: 2;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.4rem 0.8rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--pp-tekst);
    background: rgba(255, 255, 255, 0.1);
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.2);
}

.pp-foto-knop:hover {
    background: rgba(255, 255, 255, 0.18);
}

/* Boven donkerder voor het cijfer, onder een fade naar het vlak eronder. */
.pp-foto-fade {
    position: absolute;
    inset: 0;
    pointer-events: none;
    background: linear-gradient(180deg, rgba(5, 8, 16, 0.55) 0%, transparent 40%), linear-gradient(180deg, transparent 50%, var(--pp-surface-1) 100%);
}

.pp-merk {
    position: absolute;
    top: 0.55rem;
    left: 0;
    right: 0;
    text-align: center;
    font-size: 0.5625rem;
    font-weight: 700;
    letter-spacing: 0.32em;
    text-transform: uppercase;
    color: var(--pp-tier);
    opacity: 0.9;
}

.pp-overall-blok {
    position: absolute;
    top: 1.5rem;
    left: 1rem;
    line-height: 1;
}

.pp-overall {
    font-size: 3.75rem;
    font-weight: 800;
    letter-spacing: -0.05em;
    line-height: 0.9;
    color: var(--pp-accent);
    text-shadow:
        0 2px 12px rgba(0, 0, 0, 0.6),
        0 0 28px rgba(34, 224, 107, 0.35);
}

.pp-positie {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    margin-top: 0.4rem;
    font-size: 0.6875rem;
    font-weight: 700;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: var(--pp-tier);
    text-shadow: 0 1px 6px rgba(0, 0, 0, 0.7);
}

.pp-categorie {
    margin-top: 0.3rem;
    font-size: 0.6875rem;
    font-weight: 600;
    letter-spacing: 0.14em;
    color: var(--pp-tekst);
    opacity: 0.85;
    text-shadow: 0 1px 6px rgba(0, 0, 0, 0.7);
}

/* Drie badges rechtsboven: rond icoon in de tint van het level, label eronder. */
.pp-badges {
    position: absolute;
    top: 1.5rem;
    right: 0.85rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin: 0;
    padding: 0;
    list-style: none;
}

.pp-badge {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.2rem;
    width: 3.75rem;
}

.pp-badge-icoon {
    display: grid;
    place-items: center;
    width: 1.9rem;
    height: 1.9rem;
    border-radius: 999px;
    color: var(--pp-surface-2);
    background: var(--pp-metaal);
    box-shadow:
        0 0 0 1px rgba(255, 255, 255, 0.35) inset,
        0 4px 10px rgba(0, 0, 0, 0.5);
}

.pp-badge-label {
    max-width: 100%;
    line-height: 1.15;
    font-size: 0.5625rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-align: center;
    color: var(--pp-tekst);
    text-shadow: 0 1px 4px rgba(0, 0, 0, 0.8);
}

/* ---------- Het onderste deel ---------- */
.pp-body {
    position: relative;
    padding: 0 1rem 0.9rem;
}

/* Keepers: diagonale handschoen-strepen. Veldspelers: veldlijnen. Subtiel. */
.pp-patroon {
    position: absolute;
    inset: 0;
    pointer-events: none;
    opacity: 0.5;
}

.pp-keeper .pp-patroon {
    background: repeating-linear-gradient(135deg, rgba(255, 255, 255, 0.04) 0 2px, transparent 2px 18px);
}

.pp-field .pp-patroon {
    background:
        radial-gradient(circle at 50% 30%, transparent 22%, rgba(255, 255, 255, 0.05) 22.5%, transparent 23.5%),
        repeating-linear-gradient(90deg, rgba(255, 255, 255, 0.02) 0 1px, transparent 1px 44px);
}

.pp-naam {
    position: relative;
    margin-top: -0.25rem;
    text-align: center;
    line-height: 1.05;
}

.pp-voornaam {
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.22em;
    text-transform: uppercase;
    color: var(--pp-tekst-zacht);
}

.pp-achternaam {
    margin-top: 0.15rem;
    font-size: 1.625rem;
    font-weight: 800;
    letter-spacing: -0.02em;
    overflow-wrap: anywhere;
}

.pp-stats {
    position: relative;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.6rem 1.1rem;
    margin-top: 0.85rem;
}

.pp-stat-regel {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 0.5rem;
}

.pp-stat-label {
    min-width: 0;
    font-size: 0.75rem;
    font-weight: 500;
    color: var(--pp-tekst-zacht);
}

.pp-stat-cijfer {
    font-size: 1.0625rem;
    font-weight: 800;
    line-height: 1;
}

.pp-balk {
    height: 0.2rem;
    margin-top: 0.3rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.09);
    overflow: hidden;
}

.pp-balk-vulling {
    height: 100%;
    border-radius: 999px;
    background: linear-gradient(90deg, var(--pp-accent), var(--pp-tier));
    transition: width 0.6s ease;
}

.pp-leeg {
    position: relative;
    margin: 1rem auto 0;
    max-width: 15rem;
    text-align: center;
    font-size: 0.8125rem;
    color: var(--pp-tekst-zacht);
}

/* De XP-balk: het stimuleringsdeel, in de tint van het level. */
.pp-xp {
    position: relative;
    margin-top: 0.9rem;
    padding-top: 0.7rem;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
}

.pp-xp-regel {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
}

.pp-xp-label {
    font-size: 0.625rem;
    font-weight: 700;
    letter-spacing: 0.22em;
    color: var(--pp-tier);
}

.pp-xp-cijfer {
    font-size: 0.8125rem;
    font-weight: 700;
    color: var(--pp-tier);
}

.pp-balk-xp {
    height: 0.3rem;
    background: rgba(255, 255, 255, 0.1);
}

.pp-balk-xp .pp-balk-vulling {
    background: var(--pp-metaal);
}

.pp-xp-tekst {
    margin-top: 0.35rem;
    font-size: 0.75rem;
    color: var(--pp-tekst-zacht);
}

.pp-voet {
    position: relative;
    margin-top: 0.75rem;
    text-align: center;
    line-height: 1.3;
}

.pp-voet-regel {
    font-size: 0.6875rem;
    font-weight: 600;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--pp-tier);
}

.pp-school {
    font-size: 0.625rem;
    color: var(--pp-tekst-zacht);
}

/* ---------- Onder de kaart ---------- */
.pp-acties {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 0.5rem;
    margin-top: 1rem;
}

.pp-actie {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    min-height: 2.75rem;
    padding: 0.5rem 1rem;
    border-radius: 999px;
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--pp-tekst);
    background: rgba(255, 255, 255, 0.06);
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.12);
    transition: background 0.15s ease;
}

.pp-actie:hover {
    background: rgba(255, 255, 255, 0.12);
}

/*
 * De upgrade-flits: een korte lichtstoot over het nieuwe frame.
 *
 * Een eigen laag en niet de filter van .pp-frame, want daar zit al de gloed op
 * en bij elite ook de holografische animatie; die zouden elkaar overschrijven.
 */
.pp-flits {
    position: absolute;
    inset: 0;
    z-index: 3;
    pointer-events: none;
    background: radial-gradient(60% 50% at 50% 45%, rgba(255, 255, 255, 0.95), rgba(255, 255, 255, 0.35) 45%, transparent 72%);
    animation: pp-flits 1.1s ease-out forwards;
}

@keyframes pp-flits {
    0% {
        opacity: 0;
    }
    18% {
        opacity: 1;
    }
    100% {
        opacity: 0;
    }
}

.pp-puls {
    animation: pp-puls 0.9s ease-out;
}

@keyframes pp-puls {
    0% {
        transform: scale(1);
    }
    30% {
        transform: scale(1.035);
    }
    100% {
        transform: scale(1);
    }
}

@media (prefers-reduced-motion: reduce) {
    .pp-flits,
    .pp-puls {
        animation: none;
    }

    .pp-flits {
        opacity: 0;
    }
}

/* ---------- Levels: het metaal van het frame ---------- */

/* Brons: koper. Warm, met een donkere schaduwzijde. */
.pp-tier-brons {
    --pp-tier: #e0a370;
    --pp-tier-zacht: rgba(214, 154, 91, 0.2);
    --pp-gloed: rgba(214, 140, 80, 0.3);
    --pp-metaal: linear-gradient(135deg, #f2b98a 0%, #b5651d 18%, #6b3a12 36%, #f0c39a 50%, #a9581a 66%, #5a2f0e 82%, #d98a4b 100%);
}

/* Zilver: chroom. Koel, hard licht, bijna wit in de spiegeling. */
.pp-tier-zilver {
    --pp-tier: #e2e9f0;
    --pp-tier-zacht: rgba(219, 228, 238, 0.18);
    --pp-gloed: rgba(219, 228, 238, 0.32);
    --pp-metaal: linear-gradient(135deg, #ffffff 0%, #b9c3ce 18%, #5b6672 36%, #f4f7fa 50%, #9aa6b3 66%, #4a535d 82%, #dfe6ec 100%);
}

/* Goud: warm, met een gloed die net buiten het frame valt. */
.pp-tier-goud {
    --pp-tier: #f0cf6c;
    --pp-tier-zacht: rgba(232, 199, 102, 0.22);
    --pp-gloed: rgba(240, 200, 90, 0.5);
    --pp-surface-1: #172038;
    --pp-metaal: linear-gradient(135deg, #fbe28a 0%, #d4a72c 18%, #8a5c0a 36%, #fff0b3 50%, #c9961c 66%, #6e4a06 82%, #ecc95c 100%);
}

/* Elite: holografisch. Het enige frame dat beweegt, langzaam. */
.pp-tier-elite {
    --pp-tier: #f3e2a2;
    --pp-tier-zacht: rgba(200, 190, 255, 0.22);
    --pp-gloed: rgba(190, 170, 255, 0.45);
    --pp-surface-1: #171432;
    --pp-surface-2: #07070f;
    /* Pasteltinten afgewisseld met donkere facetten; zonder die donkere stops
       leest het als een sticker in plaats van als metaal. */
    --pp-metaal: linear-gradient(
        120deg,
        #e9e6ff 0%,
        #8fd3ff 11%,
        #4f4a9c 21%,
        #c8a2ff 32%,
        #ffb3e6 43%,
        #6b3f7c 53%,
        #fff2a8 63%,
        #a8ffd8 74%,
        #3f6b96 85%,
        #e9e6ff 100%
    );
}

.pp-tier-elite .pp-frame {
    background-size: 300% 300%;
    animation: pp-holo 9s ease-in-out infinite;
}

@keyframes pp-holo {
    0%,
    100% {
        background-position: 0% 50%;
    }
    50% {
        background-position: 100% 50%;
    }
}

@media (prefers-reduced-motion: reduce) {
    .pp-tier-elite .pp-frame {
        animation: none;
    }
}

/* ---------- Kleine schermen ---------- */
@media (max-width: 360px) {
    .pp-overall {
        font-size: 3.125rem;
    }

    .pp-achternaam {
        font-size: 1.375rem;
    }
}
</style>
