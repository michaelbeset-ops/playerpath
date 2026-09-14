<script setup lang="ts">
import RatingExplanation from '@/components/RatingExplanation.vue';
import { niveauVoor, STANDAARD_NIVEAUS } from '@/lib/grade';
import { Link } from '@inertiajs/vue3';
import {
    Award,
    CalendarCheck,
    Camera,
    CircleHelp,
    ClipboardCheck,
    Crown,
    Ear,
    Flame,
    Hand,
    Medal,
    Rocket,
    RotateCw,
    Share2,
    Shirt,
    Sparkles,
    Star,
    Target,
    TrendingUp,
    Trophy,
    UserRound,
    Zap,
} from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

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
 * categorieën voluit - een kind van acht hoort geen "INZ" te hoeven raden.
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
    /** Het rugnummer, groot op de foto; null als er geen is. */
    shirt_number?: number | null;
    /** Het kaartnummer, zoals op een verzamelkaart: "#0042". */
    card_number?: string;
    age_category: { key: string; label: string } | null;
    moved_up: boolean;
    overall: number | null;
    /**
     * Kleuren of cijfers (Support\Rating\Grade). In kleuren staat er nergens
     * een getal: het grote cijfer wordt een kleur, de categorieën ook.
     */
    grading?: 'kleuren' | 'cijfers';
    grade?: { key: string; label: string } | null;
    /**
     * Welke kaart: de prestatiekaart met ratings, of de inzetkaart. Bij de
     * inzetkaart zijn `overall` en `categories` leeg en staat `effort` erop.
     */
    card_mode?: 'prestatie' | 'inzet';
    effort?: {
        points: number;
        trainings: number;
        standouts: number;
        standout_label: string | null;
        rules?: { attendance: number; effort: { key: string; label: string; points: number }[]; attitude: { key: string; label: string; points: number }[] };
    } | null;
    /** De achterkant van de inzetkaart: de laatste trainingen. Publiek null. */
    recent_trainings?: { date: string; label: string; effort: string | null; attitude: string | null; points: number; note: string | null }[] | null;
    /** `delta` is wat het laatste rapport aan deze categorie veranderde; null zonder vorige stand. */
    categories: { category: string; label: string; hint?: string; rating: number | null; delta?: number | null }[];
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
    /** "Najaar 2026" of "Seizoen 2026/27": wat er onderaan de kaart staat. */
    season_label?: string;
    /** Wanneer het seizoen eindigt (d-m-Y), of null zonder ingesteld seizoen. */
    season_ends?: string | null;
    /** "week 3 van 12" tijdens een lopend seizoen. */
    season_week?: string | null;
    school: string | null;
    /** Het logo van de school, voor op de deel-afbeelding; publiek null. */
    school_logo?: string | null;
    /** De achterkant: de laatste rapporten. Publiek zonder trainer en toelichting. */
    recent_reports?: { date: string; overall: number | null; trainer: string | null; note: string | null }[] | null;
    /** Een bewaarde seizoenskaart: de stand van toen, er groeit niets meer. */
    archived?: boolean;
    /** Het lopende doel, of null (en publiek altijd null). */
    goal?: { label: string; target_grade: string | null; current_grade: string | null; progress: number | null; track_label: string | null; due: string | null } | null;
}

const props = withDefaults(
    defineProps<{
        card: Kaart;
        /** Waar je een foto toevoegt; leeg als deze kijker dat niet mag. */
        photoHref?: string | null;
        /** Of de knop "Foto toevoegen" hier op de pagina zelf iets doet (emit `photo`). */
        photoAction?: boolean;
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
    { photoHref: null, photoAction: false, audience: 'gezin', shareable: true, displayLevel: null, flash: false },
);

const emit = defineEmits<{ share: []; photo: [] }>();

const uitlegOpen = ref(false);

/*
 * De kaart kantelt mee: met de muis op een laptop, met de gyroscoop op een
 * Android-telefoon (iOS vraagt daar eerst toestemming voor, en dat is een
 * pop-up die niemand wil). Een glans loopt met de kanteling mee over het
 * metaal. Alles uit bij "minder beweging".
 */
const minderBeweging = typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const rx = ref(0);
const ry = ref(0);
const gx = ref(30);
const gy = ref(20);
const licht = ref(0);

const kantel = (e: PointerEvent) => {
    if (minderBeweging || e.pointerType !== 'mouse') {
        return;
    }

    const vak = (e.currentTarget as HTMLElement).getBoundingClientRect();
    const px = (e.clientX - vak.left) / vak.width;
    const py = (e.clientY - vak.top) / vak.height;
    ry.value = (px - 0.5) * 14;
    rx.value = -(py - 0.5) * 12;
    gx.value = px * 100;
    gy.value = py * 100;
    licht.value = 1;
};

const rechtop = () => {
    rx.value = 0;
    ry.value = 0;
    licht.value = 0;
};

let orientatieGepland = false;

const opGyroscoop = (e: DeviceOrientationEvent) => {
    if (orientatieGepland || e.gamma === null || e.beta === null) {
        return;
    }

    orientatieGepland = true;
    const gamma = e.gamma;
    const beta = e.beta;

    requestAnimationFrame(() => {
        orientatieGepland = false;
        // Links/rechts kantelen draait om de verticale as; naar je toe of van
        // je af om de horizontale. Rond de gewone leeshouding (±45°) is nul.
        ry.value = Math.max(-10, Math.min(10, gamma / 4));
        rx.value = Math.max(-10, Math.min(10, (45 - beta) / 4));
        gx.value = 50 + ry.value * 4;
        gy.value = 50 - rx.value * 4;
        licht.value = 1;
    });
};

onMounted(() => {
    const zonderToestemming =
        typeof DeviceOrientationEvent !== 'undefined' &&
        typeof (DeviceOrientationEvent as unknown as { requestPermission?: unknown }).requestPermission !== 'function';

    if (!minderBeweging && navigator.maxTouchPoints > 0 && zonderToestemming) {
        window.addEventListener('deviceorientation', opGyroscoop);
    }
});

onBeforeUnmount(() => window.removeEventListener('deviceorientation', opGyroscoop));

/*
 * Omdraaien. Geen echte 3D-achterkant (die verdraagt zich slecht met de
 * clip-path en de gloed van het frame) maar hetzelfde frame dat tot 90°
 * draait, van inhoud wisselt en terugdraait. Voor het oog is dat een kaart
 * die omgaat; voor de browser is het één element.
 */
const kant = ref<'voor' | 'achter'>('voor');
const flip = ref(0);
const zonderOvergang = ref(false);
const binnen = ref<HTMLElement | null>(null);
const voorHoogte = ref(0);
let bezigDraaien = false;

const heeftAchterkant = computed(
    () => (props.card.recent_reports !== undefined && props.card.recent_reports !== null) || (props.card.recent_trainings !== undefined && props.card.recent_trainings !== null),
);

const wacht = (ms: number) => new Promise((ok) => setTimeout(ok, ms));

const draai = async () => {
    if (bezigDraaien) {
        return;
    }

    bezigDraaien = true;

    if (kant.value === 'voor' && binnen.value) {
        voorHoogte.value = binnen.value.offsetHeight;
    }

    if (minderBeweging) {
        kant.value = kant.value === 'voor' ? 'achter' : 'voor';
        bezigDraaien = false;
        return;
    }

    flip.value = 90;
    await wacht(280);
    kant.value = kant.value === 'voor' ? 'achter' : 'voor';
    zonderOvergang.value = true;
    flip.value = -90;
    await nextTick();
    // Eén reflow, anders veegt de browser de sprong naar -90 en de overgang
    // naar 0 samen en zie je de achterkant achterstevoren binnenkomen.
    void binnen.value?.offsetHeight;
    zonderOvergang.value = false;
    flip.value = 0;
    await wacht(280);
    bezigDraaien = false;
};

const kaartStijl = computed(() => ({
    '--pp-rx': rx.value.toFixed(2) + 'deg',
    '--pp-ry': ry.value.toFixed(2) + 'deg',
    '--pp-flip': flip.value + 'deg',
    '--pp-gx': gx.value.toFixed(1) + '%',
    '--pp-gy': gy.value.toFixed(1) + '%',
    '--pp-licht': String(licht.value),
}));

/*
 * Kleuren in plaats van cijfers. De FIFA-kaart blijft, maar zonder getal: het
 * grote cijfer wordt de kleur van het kind, elke categorie een kleur met vier
 * blokjes, en een pijltje zegt of het omhoog ging - zonder "+3". Een kleur
 * vergelijk je minder snel met je teamgenoot dan een 74 met een 81.
 */
const kleuren = computed(() => props.card.grading === 'kleuren');

// De inzetkaart: level, punten en trainingen in plaats van cijfers.
const inzet = computed(() => props.card.card_mode === 'inzet');

// Op de donkere kaart vaste tinten; de tokens van de werkvloer zijn te donker.
const KAARTKLEUR: Record<string, string> = { rood: '#f87171', oranje: '#fb923c', groen: '#22e06b', blauw: '#60a5fa' };

const kleurVoor = (rating: number | null | undefined) => KAARTKLEUR[niveauVoor(rating)?.key ?? ''] ?? '#94a3b8';
const niveauIndex = (rating: number | null | undefined) => STANDAARD_NIVEAUS.findIndex((n) => n.key === niveauVoor(rating)?.key);

// Waar het kind het sterkst in is: iets om trots op te zijn, niets om mee te vergelijken.
const sterkste = computed(() => {
    const gevuld = props.card.categories.filter((c) => c.rating !== null);

    return gevuld.length ? gevuld.reduce((a, b) => ((b.rating ?? 0) > (a.rating ?? 0) ? b : a)).label : null;
});

const deltaTekst = (delta: number) => (kleuren.value ? (delta > 0 ? '▲' : '▼') : delta > 0 ? '▲' + delta : '▼' + Math.abs(delta));

// Zonder rapport is er nog geen level: dan een neutraal, stalen frame.
const tier = computed(() => {
    if (props.displayLevel) {
        return props.displayLevel;
    }

    // De inzetkaart heeft altijd een level: iedereen begint op brons.
    return props.card.overall === null && props.card.card_mode !== 'inzet' ? 'geen' : props.card.level.key;
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
    eerste_inzet: Sparkles,
    doorzetter: Flame,
    luisteraar: Ear,
    topinzet: Zap,
    zilveren_kaart: Medal,
    gouden_kaart: Crown,
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

    if (props.card.archived) {
        return 'Eindstand van dit seizoen';
    }

    if (props.card.overall === null && !inzet.value) {
        return 'Je eerste rapport zet de kaart aan';
    }

    if (next === null) {
        return 'Het hoogste level bereikt';
    }

    return inzet.value
        ? `Nog ${next.remaining} ${next.remaining === 1 ? 'punt' : 'punten'} tot ${next.label}`
        : `Nog ${next.remaining} ${next.remaining === 1 ? 'punt' : 'punten'} tot je volgende upgrade`;
});
</script>

<template>
    <div
        class="pp-wrap"
        :class="['pp-tier-' + tier, 'pp-' + card.position_key, { 'pp-puls': flash }]"
        :style="kaartStijl"
        @pointermove="kantel"
        @pointerleave="rechtop"
    >
        <div class="pp-frame" :class="{ 'pp-geen-overgang': zonderOvergang }">
            <div class="pp-frame-glans" aria-hidden="true"></div>
            <div class="pp-frame-licht" aria-hidden="true"></div>
            <div v-if="flash" class="pp-flits" aria-hidden="true"></div>

            <!-- ===== Achterkant: de laatste rapporten, mijlpalen en het doel ===== -->
            <div v-if="kant === 'achter'" ref="binnen" class="pp-binnen pp-achter" :style="{ minHeight: voorHoogte + 'px' }">
                <div class="pp-patroon" aria-hidden="true"></div>

                <div class="pp-achter-inhoud">
                    <p class="pp-achter-naam">{{ card.first_name }} {{ card.last_name }}</p>

                    <template v-if="inzet">
                        <p class="pp-achter-kop">Laatste trainingen</p>
                        <ul v-if="card.recent_trainings?.length" class="pp-rapporten">
                            <li v-for="t in card.recent_trainings" :key="t.date + t.label" class="pp-rapport">
                                <div class="pp-rapport-regel">
                                    <span class="tabular">{{ t.date }}</span>
                                    <span class="pp-rapport-trainer">{{ t.label }}</span>
                                    <span class="pp-rapport-cijfer tabular">+{{ t.points }}</span>
                                </div>
                                <p v-if="t.effort || t.attitude" class="pp-rapport-noot">{{ [t.effort, t.attitude].filter(Boolean).join(' · ') }}</p>
                                <p v-if="t.note" class="pp-rapport-noot">{{ t.note }}</p>
                            </li>
                        </ul>
                        <p v-else class="pp-achter-leeg">Na je eerste training staat hier wat je verdiende.</p>
                    </template>

                    <template v-else>
                    <p class="pp-achter-kop">Laatste rapporten</p>
                    <ul v-if="card.recent_reports?.length" class="pp-rapporten">
                        <li v-for="r in card.recent_reports" :key="r.date + (r.trainer ?? '')" class="pp-rapport">
                            <div class="pp-rapport-regel">
                                <span class="tabular">{{ r.date }}</span>
                                <span v-if="r.trainer" class="pp-rapport-trainer">{{ r.trainer }}</span>
                                <span v-if="kleuren" class="pp-rapport-kleur" :style="{ color: kleurVoor(r.overall) }">{{
                                    niveauVoor(r.overall)?.label ?? '-'
                                }}</span>
                                <span v-else class="pp-rapport-cijfer tabular">{{ r.overall ?? '-' }}</span>
                            </div>
                            <p v-if="r.note" class="pp-rapport-noot">{{ r.note }}</p>
                        </li>
                    </ul>
                    <p v-else class="pp-achter-leeg">Nog geen rapport. Na de eerste training komt hier de eerste.</p>
                    </template>

                    <template v-if="card.goal">
                        <p class="pp-achter-kop">Waar we aan werken</p>
                        <div class="pp-doel">
                            <div class="pp-rapport-regel">
                                <span>{{ card.goal.label }}<template v-if="card.goal.target_grade"> naar {{ card.goal.target_grade }}</template></span>
                                <span v-if="card.goal.track_label" class="pp-doel-status">{{ card.goal.track_label }}</span>
                            </div>
                            <div v-if="card.goal.progress !== null" class="pp-balk">
                                <div class="pp-balk-vulling" :style="{ width: Math.max(0, Math.min(100, card.goal.progress)) + '%' }"></div>
                            </div>
                            <p class="pp-achter-leeg">
                                <template v-if="card.goal.current_grade">Nu {{ card.goal.current_grade }}</template>
                                <template v-if="card.goal.due"> · tot {{ card.goal.due }}</template>
                            </p>
                        </div>
                    </template>

                    <template v-if="card.badges.length">
                        <p class="pp-achter-kop">Mijlpalen</p>
                        <ul class="pp-achter-badges" aria-label="Behaalde mijlpalen">
                            <li v-for="badge in card.badges" :key="badge.key" class="pp-badge" :title="badge.description">
                                <span class="pp-badge-icoon">
                                    <component :is="icoonVoor(badge.key)" class="size-3.5" aria-hidden="true" />
                                </span>
                                <span class="pp-badge-label">{{ badge.label }}</span>
                            </li>
                        </ul>
                    </template>

                    <div class="pp-voet pp-achter-voet">
                        <p class="pp-voet-regel">{{ card.season_label ?? 'Seizoen ' + card.season }}</p>
                        <p v-if="card.school" class="pp-school">{{ card.school }}</p>
                    </div>
                </div>
            </div>

            <div v-else ref="binnen" class="pp-binnen">
                <!-- Boven: de foto met daaroverheen het cijfer en de badges -->
                <div class="pp-foto-vak">
                    <img v-if="card.photo" :src="card.photo" :alt="card.name" class="pp-foto" />

                    <div v-else class="pp-silhouet">
                        <UserRound class="pp-silhouet-icoon" aria-hidden="true" />
                        <Link v-if="photoHref" :href="photoHref" class="pp-foto-knop inline-flex min-h-11 items-center">
                            <Camera class="size-3.5" />
                            Foto toevoegen
                        </Link>
                        <button v-else-if="photoAction" type="button" class="pp-foto-knop inline-flex min-h-11 items-center" @click="emit('photo')">
                            <Camera class="size-3.5" />
                            Foto toevoegen
                        </button>
                    </div>

                    <div class="pp-foto-fade" aria-hidden="true"></div>

                    <p class="pp-merk">PlayerPath</p>

                    <div class="pp-overall-blok">
                        <!-- Inzetkaart: het level groot, in het metaal van het frame. Geen cijfer. -->
                        <template v-if="inzet">
                            <p class="pp-level-groot">{{ card.level.label }}</p>
                        </template>
                        <template v-else-if="kleuren">
                            <p class="pp-overall-kleur" :style="{ '--pp-kleur': kleurVoor(card.overall) }">
                                {{ card.overall === null ? 'Nieuw' : niveauVoor(card.overall)?.label }}
                            </p>
                            <p v-if="sterkste" class="pp-sterk">Sterk in {{ sterkste }}</p>
                        </template>
                        <p v-else class="pp-overall tabular">{{ card.overall ?? '-' }}</p>
                        <p class="pp-positie">
                            <Hand v-if="card.position_key === 'keeper'" class="size-3.5" aria-hidden="true" />
                            <Shirt v-else class="size-3.5" aria-hidden="true" />
                            {{ card.position }}
                        </p>
                        <p v-if="card.age_category" class="pp-categorie" :title="card.age_category.label">{{ card.age_category.key }}</p>
                    </div>

                    <p v-if="card.shirt_number" class="pp-rugnummer tabular" aria-label="Rugnummer">{{ card.shirt_number }}</p>

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

                    <!-- Inzetkaart: groot en centraal het level en de balk, en wat het kind
                         verzamelde. Geen categorieën, niets om naast een ander te leggen. -->
                    <div v-if="inzet" class="pp-inzet">
                        <div class="pp-inzet-level">
                            <div class="pp-xp-regel">
                                <span class="pp-xp-label">LEVEL {{ card.level.label.toUpperCase() }}</span>
                                <span class="pp-xp-cijfer tabular">{{ card.level.xp }} XP</span>
                            </div>
                            <div class="pp-balk pp-balk-inzet">
                                <div class="pp-balk-vulling" :style="{ width: card.level.progress + '%' }"></div>
                            </div>
                            <p class="pp-inzet-tekst">{{ upgradeTekst }}</p>
                        </div>

                        <div class="pp-inzet-stats">
                            <div class="pp-inzet-stat">
                                <Zap class="size-3.5" aria-hidden="true" />
                                <span class="pp-inzet-getal tabular">{{ card.effort?.points ?? card.level.xp }}</span>
                                <span class="pp-inzet-label">inzetpunten</span>
                            </div>
                            <div class="pp-inzet-stat">
                                <CalendarCheck class="size-3.5" aria-hidden="true" />
                                <span class="pp-inzet-getal tabular">{{ card.effort?.trainings ?? 0 }}</span>
                                <span class="pp-inzet-label">trainingen</span>
                            </div>
                            <div class="pp-inzet-stat">
                                <Flame class="size-3.5" aria-hidden="true" />
                                <span class="pp-inzet-getal tabular">{{ card.effort?.standouts ?? 0 }}</span>
                                <span class="pp-inzet-label">{{ (card.effort?.standout_label ?? 'Uitblinker').toLowerCase() }}</span>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="card.overall !== null" class="pp-stats">
                        <div v-for="c in card.categories" :key="c.category" class="pp-stat">
                            <div class="pp-stat-regel">
                                <span class="pp-stat-label">{{ c.label }}</span>
                                <!-- Wat het laatste rapport deed: groei voelbaar, niet alleen een stand. -->
                                <span
                                    v-if="c.delta"
                                    class="pp-delta tabular"
                                    :class="c.delta > 0 ? 'pp-delta-op' : 'pp-delta-af'"
                                    :title="'Sinds het vorige rapport'"
                                    >{{ deltaTekst(c.delta) }}</span
                                >
                                <span v-if="!kleuren" class="pp-stat-cijfer tabular">{{ c.rating ?? '-' }}</span>
                            </div>
                            <!-- In kleuren vier blokjes met de kleur erachter, onder het label:
                                 naast het label paste "Werkpunt" niet in een halve kaart. -->
                            <div v-if="kleuren" class="pp-blokjes" :aria-label="niveauVoor(c.rating)?.label ?? 'Nog geen'">
                                <span
                                    v-for="(n, i) in STANDAARD_NIVEAUS"
                                    :key="n.key"
                                    class="pp-blokje"
                                    :style="i <= niveauIndex(c.rating) ? { background: kleurVoor(c.rating) } : undefined"
                                ></span>
                                <span class="pp-stat-kleur" :style="{ color: kleurVoor(c.rating) }">{{ niveauVoor(c.rating)?.label ?? '-' }}</span>
                            </div>
                            <div v-else class="pp-balk">
                                <div class="pp-balk-vulling" :style="{ width: balk(c.rating) }"></div>
                            </div>
                        </div>
                    </div>

                    <p v-else class="pp-leeg">Zodra het eerste rapport binnen is, komt deze kaart tot leven.</p>

                    <div v-if="!inzet" class="pp-xp">
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
                            {{ card.season_label ?? 'Seizoen ' + card.season }}
                            <template v-if="card.overall !== null || inzet"> &middot; Level {{ card.level.label }}</template>
                        </p>
                        <p v-if="card.school || card.card_number" class="pp-school">
                            <template v-if="card.school">{{ card.school }}</template>
                            <template v-if="card.school && card.card_number"> &middot; </template>
                            <span v-if="card.card_number" class="tabular">{{ card.card_number }}</span>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Onder de kaart: uitleg en delen -->
        <div class="pp-acties">
            <button type="button" class="pp-actie" @click="uitlegOpen = true">
                <CircleHelp class="size-4" aria-hidden="true" />
                {{ kleuren || inzet ? 'Hoe werkt mijn kaart?' : 'Hoe werkt mijn rating?' }}
            </button>
            <button v-if="heeftAchterkant" type="button" class="pp-actie" :aria-pressed="kant === 'achter'" @click="draai">
                <RotateCw class="size-4" aria-hidden="true" />
                {{ kant === 'voor' ? 'Draai om' : 'Terug' }}
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
    /* Kantelen (muis of gyroscoop) en omdraaien zitten in dezelfde transform. */
    transform: perspective(900px) rotateX(var(--pp-rx, 0deg)) rotateY(calc(var(--pp-ry, 0deg) + var(--pp-flip, 0deg)));
    transition: transform 0.28s ease;
    will-change: transform;
}

.pp-geen-overgang {
    transition: none;
}

/* De lichtval die met het kantelen meeloopt over het metaal. */
.pp-frame-licht {
    position: absolute;
    inset: 0;
    pointer-events: none;
    opacity: var(--pp-licht, 0);
    background: radial-gradient(45% 45% at var(--pp-gx, 30%) var(--pp-gy, 20%), rgba(255, 255, 255, 0.45), transparent 70%);
    mix-blend-mode: soft-light;
    transition: opacity 0.4s ease;
}

/* Het rugnummer: groot en in het metaal van het level, linksonder op de foto
   (rechts staan de mijlpalen). */
.pp-rugnummer {
    position: absolute;
    left: 0.9rem;
    bottom: 0.35rem;
    z-index: 2;
    font-size: 2.6rem;
    font-weight: 900;
    line-height: 1;
    letter-spacing: -0.03em;
    color: var(--pp-tier);
    text-shadow: 0 2px 10px rgba(0, 0, 0, 0.6);
}

/* ---------- De achterkant ---------- */
.pp-achter-inhoud {
    position: relative;
    padding: 1rem 1.1rem 0.9rem;
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.pp-achter-naam {
    font-size: 1.05rem;
    font-weight: 800;
    letter-spacing: -0.01em;
}

.pp-achter-kop {
    margin-top: 0.6rem;
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--pp-tier);
}

.pp-rapporten {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.pp-rapport {
    padding: 0.5rem 0.65rem;
    border-radius: 0.6rem;
    background: rgba(255, 255, 255, 0.05);
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.08);
}

.pp-rapport-regel {
    display: flex;
    align-items: baseline;
    gap: 0.5rem;
    font-size: 0.8rem;
}

.pp-rapport-trainer {
    min-width: 0;
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: var(--pp-tekst-zacht);
}

.pp-rapport-cijfer {
    margin-left: auto;
    font-size: 1.05rem;
    font-weight: 800;
}

.pp-rapport-noot {
    margin-top: 0.2rem;
    font-size: 0.75rem;
    line-height: 1.35;
    color: var(--pp-tekst-zacht);
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.pp-achter-leeg {
    font-size: 0.78rem;
    color: var(--pp-tekst-zacht);
}

.pp-doel {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    padding: 0.5rem 0.65rem;
    border-radius: 0.6rem;
    background: rgba(34, 224, 107, 0.08);
    box-shadow: inset 0 0 0 1px rgba(34, 224, 107, 0.25);
}

.pp-doel-status {
    margin-left: auto;
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--pp-accent);
}

.pp-achter-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}

.pp-achter-voet {
    margin-top: auto;
    padding-top: 0.8rem;
}

/* ---------- Het pijltje per categorie ---------- */
.pp-delta {
    margin-left: auto;
    font-size: 0.68rem;
    font-weight: 800;
    padding: 0.05rem 0.3rem;
    border-radius: 999px;
    animation: pp-delta-in 0.6s ease-out both;
}

.pp-delta-op {
    color: #22e06b;
    background: rgba(34, 224, 107, 0.14);
}

.pp-delta-af {
    color: #f59e0b;
    background: rgba(245, 158, 11, 0.14);
}

@keyframes pp-delta-in {
    from {
        opacity: 0;
        transform: translateY(4px);
    }
    to {
        opacity: 1;
        transform: none;
    }
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

/* In kleuren: het label in plaats van het cijfer, en vier blokjes als balk. */
.pp-stat-kleur {
    font-size: 0.75rem;
    font-weight: 800;
    line-height: 1;
    white-space: nowrap;
}

.pp-blokjes {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr)) auto;
    align-items: center;
    gap: 0.15rem;
    margin-top: 0.3rem;
}

.pp-blokjes .pp-stat-kleur {
    margin-left: 0.3rem;
    font-size: 0.6875rem;
}

.pp-blokje {
    height: 0.2rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.09);
    transition: background 0.6s ease;
}

.pp-rapport-kleur {
    margin-left: auto;
    font-size: 0.8rem;
    font-weight: 800;
    white-space: nowrap;
}

/* ---------- De inzetkaart ---------- */
/* Het level groot linksboven, in het metaal van het frame: dat is wat groeit. */
.pp-level-groot {
    max-width: 10rem;
    font-size: 2.125rem;
    font-weight: 900;
    letter-spacing: -0.01em;
    line-height: 0.95;
    text-transform: uppercase;
    background: var(--pp-metaal);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
    filter: drop-shadow(0 2px 8px rgba(0, 0, 0, 0.8));
}

.pp-inzet {
    position: relative;
    margin-top: 0.85rem;
}

.pp-balk-inzet {
    height: 0.55rem;
    margin-top: 0.4rem;
    background: rgba(255, 255, 255, 0.1);
}

.pp-balk-inzet .pp-balk-vulling {
    background: var(--pp-metaal);
}

.pp-inzet-tekst {
    margin-top: 0.4rem;
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--pp-tekst);
}

.pp-inzet-stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.4rem;
    margin-top: 0.8rem;
}

.pp-inzet-stat {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.15rem;
    min-width: 0;
    padding: 0.5rem 0.25rem;
    border-radius: 0.6rem;
    background: rgba(255, 255, 255, 0.05);
    box-shadow: 0 0 0 1px var(--pp-tier-zacht) inset;
    color: var(--pp-tier);
}

.pp-inzet-getal {
    font-size: 1.375rem;
    font-weight: 800;
    line-height: 1;
    color: var(--pp-tekst);
}

.pp-inzet-label {
    max-width: 100%;
    font-size: 0.5625rem;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-align: center;
    text-transform: uppercase;
    color: var(--pp-tekst-zacht);
    overflow-wrap: anywhere;
}

/* Het grote cijfer als kleur: kleiner dan een getal, want het is een woord. */
.pp-overall-kleur {
    max-width: 9rem;
    font-size: 1.75rem;
    font-weight: 900;
    letter-spacing: -0.02em;
    line-height: 0.95;
    color: var(--pp-kleur);
    text-shadow:
        0 2px 12px rgba(0, 0, 0, 0.7),
        0 0 24px color-mix(in srgb, var(--pp-kleur) 45%, transparent);
}

.pp-sterk {
    margin-top: 0.3rem;
    max-width: 9rem;
    font-size: 0.625rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: #f1f5f9;
    text-shadow: 0 1px 6px rgba(0, 0, 0, 0.8);
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
    .pp-frame {
        transition: none;
        transform: none;
    }

    .pp-delta {
        animation: none;
    }

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
