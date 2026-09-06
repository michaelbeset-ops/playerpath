<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Hand, Shirt, Trophy } from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * De spelerskaart als verzamelkaart.
 *
 * Eigen identiteit, bewust geen FUT-kopie: geen schild-silhouet, geen
 * landvlag, geen clublogo. Wel: een groot cijfer, de stats prominent, een
 * niveau dat meegroeit (brons > zilver > goud > elite) en een eigen
 * uitstraling voor keepers (handschoen-strepen) en veldspelers (veldlijnen).
 *
 * Mobiel-first: de kaart is maximaal 360px breed en schaalt daaronder mee.
 * Alle kleuren staan als CSS-variabelen per niveau, zodat een nieuw niveau
 * later één blokje CSS is.
 */
const props = withDefaults(
    defineProps<{
        name: string;
        position: string;
        positionKey: 'keeper' | 'field';
        age?: number | null;
        overall: number | null;
        categories: { category: string; label: string; rating: number | null }[];
        level: { key: string; label: string };
        badges: { key: string; label: string }[];
        reportCount?: number;
        /** De pasfoto. Zonder foto blijft het medaillon de initialen tonen. */
        photo?: string | null;
    }>(),
    { age: null, reportCount: 0, photo: null },
);

const initialen = computed(() =>
    props.name
        .split(' ')
        .filter(Boolean)
        .map((deel) => deel[0])
        .slice(0, 2)
        .join('')
        .toUpperCase(),
);

// Drieletterige afkortingen, zoals op een echte kaart. De volledige naam
// staat in de title, voor wie erop hangt.
const afkorting: Record<string, string> = {
    reflexen: 'REF',
    uitkomen: 'UIT',
    voetenwerk: 'VOE',
    een_tegen_een: '1v1',
    hoge_ballen: 'HOG',
    communicatie: 'COM',
    techniek: 'TEC',
    inzicht: 'INZ',
    passing: 'PAS',
    afwerking: 'AFW',
    snelheid: 'SNE',
    mentaliteit: 'MEN',
};

const kort = (categorie: string, label: string) => afkorting[categorie] ?? label.slice(0, 3).toUpperCase();

const tier = computed(() => (props.overall === null ? 'geen' : props.level.key));

const balk = (rating: number | null) => (rating === null ? '0%' : rating + '%');
</script>

<template>
    <div class="pp-kaart" :class="['pp-tier-' + tier, 'pp-' + positionKey]">
        <!-- Achtergrondlagen: patroon, glans en diepte. Puur decoratief. -->
        <div class="pp-patroon" aria-hidden="true"></div>
        <div class="pp-glans" aria-hidden="true"></div>
        <div v-if="tier === 'elite'" class="pp-schittering" aria-hidden="true"></div>

        <div class="relative flex h-full flex-col p-5">
            <!-- Kop: overall groot links, positie en niveau rechts -->
            <div class="flex items-start justify-between gap-3">
                <div class="leading-none">
                    <p class="pp-overall tabular">{{ overall ?? '—' }}</p>
                    <p class="pp-label mt-1">Overall</p>
                </div>

                <div class="flex flex-col items-end gap-1.5 pt-1">
                    <span class="pp-chip">
                        <Hand v-if="positionKey === 'keeper'" class="size-3" />
                        <Shirt v-else class="size-3" />
                        {{ position }}
                    </span>
                    <span v-if="overall !== null" class="pp-chip pp-chip-tier">{{ level.label }}</span>
                </div>
            </div>

            <!-- Medaillon met initialen: het "portret" -->
            <div class="my-5 flex flex-col items-center">
                <div class="pp-medaillon">
                    <!-- De foto vult het medaillon; zonder foto de initialen.
                         De foto is bij het uploaden al vierkant gemaakt, dus
                         hier is object-fit genoeg en snijdt er niets scheef af. -->
                    <img v-if="photo" :src="photo" :alt="name" class="pp-foto" />
                    <span v-else class="pp-initialen">{{ initialen }}</span>
                </div>
                <p class="pp-naam mt-3">{{ name }}</p>
                <p class="pp-sub">
                    <template v-if="age">{{ age }} jaar</template>
                    <template v-if="age && reportCount"> &middot; </template>
                    <template v-if="reportCount">{{ reportCount }} {{ reportCount === 1 ? 'rapport' : 'rapporten' }}</template>
                </p>
            </div>

            <!-- Stats: zes vakken, groot leesbaar, met een dun balkje -->
            <div v-if="overall !== null" class="grid grid-cols-3 gap-x-3 gap-y-3">
                <div v-for="c in categories" :key="c.category" :title="c.label">
                    <div class="flex items-baseline justify-between">
                        <span class="pp-stat-label">{{ kort(c.category, c.label) }}</span>
                        <span class="pp-stat tabular">{{ c.rating ?? '—' }}</span>
                    </div>
                    <div class="pp-balk mt-1">
                        <div class="pp-balk-vulling" :style="{ width: balk(c.rating) }"></div>
                    </div>
                </div>
            </div>

            <p v-else class="pp-sub mx-auto max-w-[14rem] text-center">Zodra het eerste rapport binnen is, komt deze kaart tot leven.</p>

            <!-- Voet: mijlpalen en het merk -->
            <div class="mt-auto flex items-end justify-between gap-3 pt-5">
                <div class="flex min-w-0 flex-wrap gap-1.5">
                    <span v-for="badge in badges.slice(0, 3)" :key="badge.key" class="pp-badge" :title="badge.label">
                        <Trophy class="size-3" />
                        <span class="truncate">{{ badge.label }}</span>
                    </span>
                    <span v-if="badges.length > 3" class="pp-badge">+{{ badges.length - 3 }}</span>
                </div>

                <div class="pp-merk shrink-0" title="PlayerPath">
                    <AppLogoIcon class="size-4" />
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* ---------- Basis ---------- */
.pp-kaart {
    --pp-bg-1: #0f1a30;
    --pp-bg-2: #0a0f1c;
    --pp-rand: rgba(255, 255, 255, 0.1);
    --pp-accent: #22e06b;
    --pp-accent-zacht: rgba(34, 224, 107, 0.18);
    --pp-tekst: #f1f5f9;
    --pp-tekst-zacht: #94a3b8;
    --pp-tier: #94a3b8;
    --pp-tier-zacht: rgba(148, 163, 184, 0.16);
    --pp-gloed: rgba(148, 163, 184, 0.16);

    position: relative;
    width: 100%;
    max-width: 22.5rem;
    aspect-ratio: 5 / 7;
    margin-inline: auto;
    overflow: hidden;
    border-radius: 1.5rem;
    color: var(--pp-tekst);
    background:
        radial-gradient(120% 70% at 20% -10%, var(--pp-gloed), transparent 60%),
        radial-gradient(90% 60% at 100% 110%, var(--pp-accent-zacht), transparent 60%), linear-gradient(160deg, var(--pp-bg-1), var(--pp-bg-2));
    box-shadow:
        inset 0 0 0 1px var(--pp-rand),
        inset 0 1px 0 rgba(255, 255, 255, 0.14),
        0 24px 48px -20px rgba(0, 0, 0, 0.7),
        0 0 0 1px rgba(0, 0, 0, 0.35);
    font-variant-numeric: tabular-nums;
}

/* Keepers: diagonale handschoen-strepen. Veldspelers: veldlijnen. Subtiel. */
.pp-patroon {
    position: absolute;
    inset: 0;
    opacity: 0.55;
    pointer-events: none;
}

.pp-keeper .pp-patroon {
    background: repeating-linear-gradient(135deg, rgba(255, 255, 255, 0.045) 0 2px, transparent 2px 18px);
    mask-image: linear-gradient(180deg, rgba(0, 0, 0, 0.9), transparent 70%);
    -webkit-mask-image: linear-gradient(180deg, rgba(0, 0, 0, 0.9), transparent 70%);
}

.pp-field .pp-patroon {
    background:
        radial-gradient(circle at 50% 42%, transparent 27%, rgba(255, 255, 255, 0.06) 27.5%, transparent 28.5%),
        linear-gradient(180deg, transparent calc(42% - 1px), rgba(255, 255, 255, 0.06) 42%, transparent calc(42% + 1px)),
        repeating-linear-gradient(90deg, rgba(255, 255, 255, 0.02) 0 1px, transparent 1px 44px);
}

/* Glans: een zachte lichtval linksboven en een diagonale sheen. */
.pp-glans {
    position: absolute;
    inset: 0;
    pointer-events: none;
    background:
        radial-gradient(60% 40% at 15% 0%, rgba(255, 255, 255, 0.14), transparent 70%),
        linear-gradient(115deg, transparent 35%, rgba(255, 255, 255, 0.05) 48%, transparent 52%);
}

/* Elite: een langzaam voorbijtrekkende schittering. */
.pp-schittering {
    position: absolute;
    inset: -40%;
    pointer-events: none;
    background: linear-gradient(115deg, transparent 40%, rgba(255, 255, 255, 0.22) 50%, transparent 60%);
    animation: pp-sweep 6s ease-in-out infinite;
}

@keyframes pp-sweep {
    0%,
    55% {
        transform: translateX(-60%);
    }
    100% {
        transform: translateX(60%);
    }
}

@media (prefers-reduced-motion: reduce) {
    .pp-schittering {
        animation: none;
    }
}

/* ---------- Niveaus ---------- */
.pp-tier-brons {
    --pp-tier: #d69a5b;
    --pp-tier-zacht: rgba(214, 154, 91, 0.18);
    --pp-gloed: rgba(214, 154, 91, 0.22);
    --pp-rand: rgba(214, 154, 91, 0.35);
}

.pp-tier-zilver {
    --pp-tier: #dbe4ee;
    --pp-tier-zacht: rgba(219, 228, 238, 0.16);
    --pp-gloed: rgba(219, 228, 238, 0.24);
    --pp-rand: rgba(219, 228, 238, 0.4);
}

.pp-tier-goud {
    --pp-tier: #e8c766;
    --pp-tier-zacht: rgba(232, 199, 102, 0.2);
    --pp-gloed: rgba(232, 199, 102, 0.32);
    --pp-rand: rgba(232, 199, 102, 0.55);
    --pp-bg-1: #14203a;
}

.pp-tier-elite {
    --pp-tier: #f3d98b;
    --pp-tier-zacht: rgba(243, 217, 139, 0.22);
    --pp-gloed: rgba(243, 217, 139, 0.4);
    --pp-rand: rgba(243, 217, 139, 0.7);
    --pp-bg-1: #171432;
    --pp-bg-2: #06060d;
}

/* ---------- Onderdelen ---------- */
.pp-overall {
    font-size: 3.75rem;
    font-weight: 800;
    letter-spacing: -0.04em;
    line-height: 0.9;
    color: var(--pp-accent);
    text-shadow: 0 0 24px var(--pp-accent-zacht);
}

.pp-label {
    font-size: 0.625rem;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: var(--pp-tekst-zacht);
}

.pp-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.25rem 0.55rem;
    border-radius: 999px;
    font-size: 0.625rem;
    font-weight: 600;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--pp-tekst);
    background: rgba(255, 255, 255, 0.08);
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.12);
}

.pp-chip-tier {
    color: var(--pp-tier);
    background: var(--pp-tier-zacht);
    box-shadow: inset 0 0 0 1px var(--pp-rand);
}

.pp-foto {
    width: 100%;
    height: 100%;
    border-radius: 999px;
    object-fit: cover;
}

.pp-medaillon {
    display: grid;
    place-items: center;
    overflow: hidden;
    width: 5.5rem;
    height: 5.5rem;
    border-radius: 999px;
    background:
        radial-gradient(circle at 35% 30%, rgba(255, 255, 255, 0.16), transparent 55%),
        linear-gradient(160deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.02));
    box-shadow:
        0 0 0 2px var(--pp-tier),
        0 0 0 6px var(--pp-tier-zacht),
        0 12px 30px -10px rgba(0, 0, 0, 0.8);
}

.pp-initialen {
    font-size: 1.75rem;
    font-weight: 800;
    letter-spacing: 0.02em;
    color: var(--pp-tier);
}

.pp-naam {
    font-size: 1.375rem;
    font-weight: 800;
    letter-spacing: -0.01em;
    text-align: center;
    line-height: 1.1;
}

.pp-sub {
    margin-top: 0.25rem;
    font-size: 0.75rem;
    color: var(--pp-tekst-zacht);
}

.pp-stat-label {
    font-size: 0.6875rem;
    font-weight: 600;
    letter-spacing: 0.1em;
    color: var(--pp-tekst-zacht);
}

.pp-stat {
    font-size: 1.125rem;
    font-weight: 800;
    line-height: 1;
}

.pp-balk {
    height: 0.25rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.1);
    overflow: hidden;
}

.pp-balk-vulling {
    height: 100%;
    border-radius: 999px;
    background: linear-gradient(90deg, var(--pp-accent), var(--pp-tier));
    transition: width 0.6s ease;
}

.pp-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    max-width: 8.5rem;
    padding: 0.2rem 0.5rem;
    border-radius: 0.5rem;
    font-size: 0.625rem;
    font-weight: 600;
    color: var(--pp-tier);
    background: var(--pp-tier-zacht);
    box-shadow: inset 0 0 0 1px var(--pp-rand);
}

.pp-merk {
    display: grid;
    place-items: center;
    width: 1.75rem;
    height: 1.75rem;
    border-radius: 0.5rem;
    color: var(--pp-accent);
    background: rgba(255, 255, 255, 0.06);
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.1);
}

/* Op hele kleine schermen iets compacter, zodat de kaart in beeld past. */
@media (max-width: 360px) {
    .pp-overall {
        font-size: 3rem;
    }

    .pp-medaillon {
        width: 4.5rem;
        height: 4.5rem;
    }
}
</style>
