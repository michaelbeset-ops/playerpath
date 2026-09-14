<script setup lang="ts">
import { useGrading } from '@/lib/grade';
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Sparkles, Trophy, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * Wat er zojuist veranderde, meteen na het opslaan van een rapport.
 *
 * Dit is het moment waar een trainer het voor doet: hij ziet dat zijn werk
 * aankwam. Drie keuzes die daarbij horen:
 *
 * - **Het is een balk onderaan, geen dekkend scherm.** De kaart blijft
 *   zichtbaar, want dáár gaat het over; bij een level-up zie je het frame
 *   erboven wisselen terwijl deze balk het uitlegt.
 * - **Het sluit niet vanzelf.** Een teller die de knop "Volgende speler"
 *   weghaalt terwijl je nog leest is erger dan één tik extra.
 * - **Alleen wat veranderde.** Een categorie die gelijk bleef is geen nieuws.
 */
export interface RapportWijziging {
    player: { id: number; first_name: string };
    overall: { from: number | null; to: number | null; delta: number | null };
    categories: { category: string; label: string; from: number; to: number; delta: number }[];
    xp: { from: number; to: number; gained: number };
    level: { from: { key: string; label: string }; to: { key: string; label: string }; up: boolean };
    badges: { key: string; label: string; description: string }[];
    next: { id: number; name: string } | null;
}

const props = defineProps<{
    result: RapportWijziging;
    /** De parent zet dit zodra het frame van de kaart omslaat, zodat de tekst en de kaart gelijk lopen. */
    levelUpVisible: boolean;
}>();

const emit = defineEmits<{ close: [] }>();

const rustig = typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// Hooguit drie categorieën: dit is een flits, geen rapport.
const categorieen = computed(() => props.result.categories.slice(0, 3));
const rest = computed(() => Math.max(0, props.result.categories.length - 3));

// Het overall-cijfer telt op naar de nieuwe waarde. Bij prefers-reduced-motion
// staat het er meteen goed.
const geteld = ref(props.result.overall.from ?? props.result.overall.to ?? 0);

const tel = () => {
    const van = props.result.overall.from;
    const tot = props.result.overall.to;

    if (tot === null) {
        return;
    }

    if (van === null || rustig || van === tot) {
        geteld.value = tot;

        return;
    }

    const start = performance.now();
    const duur = 800;

    const stap = (nu: number) => {
        const deel = Math.min(1, (nu - start) / duur);
        geteld.value = Math.round(van + (tot - van) * deel);

        if (deel < 1) {
            requestAnimationFrame(stap);
        }
    };

    requestAnimationFrame(stap);
};

const opToets = (event: KeyboardEvent) => {
    if (event.key === 'Escape') {
        emit('close');
    }
};

onMounted(() => {
    setTimeout(tel, rustig ? 0 : 250);
    window.addEventListener('keydown', opToets);
});

onBeforeUnmount(() => window.removeEventListener('keydown', opToets));

const teken = (waarde: number) => (waarde > 0 ? '+' + waarde : String(waarde));

// In kleuren: "Op weg → Goed" in plaats van "+3".
const { kleuren, niveauVoor } = useGrading();
const kleurStap = (c: { from: number; to: number; delta: number }) => {
    const van = niveauVoor(c.from)?.label;
    const tot = niveauVoor(c.to)?.label;

    return van === tot ? (c.delta > 0 ? '▲ ' : '▼ ') + tot : van + ' → ' + tot;
};
</script>

<template>
    <div class="rc-laag" @click.self="emit('close')">
        <div class="rc-paneel theme-donker" role="status" aria-live="polite">
            <button type="button" class="rc-sluit" aria-label="Sluiten" @click="emit('close')">
                <X class="size-4" />
            </button>

            <!-- Level omhoog: verschijnt op het moment dat het frame wisselt -->
            <Transition name="rc-level">
                <div v-if="result.level.up && levelUpVisible" class="rc-level">
                    <Sparkles class="size-5 shrink-0" aria-hidden="true" />
                    <p class="min-w-0">
                        <span class="rc-level-kop">Level omhoog!</span>
                        <span class="rc-level-sub">{{ result.level.from.label }} &rarr; {{ result.level.to.label }}</span>
                    </p>
                </div>
            </Transition>

            <p class="rc-kop">{{ result.player.first_name }} staat bijgewerkt op de kaart</p>

            <ul class="rc-lijst">
                <li v-for="(c, i) in categorieen" :key="c.category" class="rc-regel" :style="{ '--rc-vertraging': i * 90 + 'ms' }">
                    <span class="rc-label">{{ c.label }}</span>
                    <span class="rc-delta" :class="c.delta > 0 ? 'rc-op' : 'rc-neer'">{{ kleuren ? kleurStap(c) : teken(c.delta) }}</span>
                </li>

                <li v-if="rest" class="rc-regel rc-stil" :style="{ '--rc-vertraging': '270ms' }">
                    <span class="rc-label">en nog {{ rest }} {{ rest === 1 ? 'categorie' : 'categorieën' }}</span>
                </li>

                <li v-if="!categorieen.length" class="rc-regel rc-stil" :style="{ '--rc-vertraging': '0ms' }">
                    <span class="rc-label">Cijfers gelijk gebleven</span>
                </li>
            </ul>

            <div class="rc-cijfers">
                <div class="rc-vak">
                    <p class="rc-vak-label">Overall</p>
                    <p v-if="kleuren" class="rc-vak-waarde">{{ niveauVoor(result.overall.to)?.label ?? '—' }}</p>
                    <p v-else class="rc-vak-waarde tabular">
                        <span v-if="result.overall.from !== null && result.overall.delta" class="rc-vorig">{{ result.overall.from }} &rarr; </span>
                        <template v-if="result.overall.to === null">&mdash;</template>
                        <template v-else>{{ geteld }}</template>
                    </p>
                </div>

                <div class="rc-vak">
                    <p class="rc-vak-label">XP</p>
                    <!-- teken(): XP loopt bij een rapport alleen op, maar "+-3"
                         is het soort tekst dat je nooit wilt zien staan. -->
                    <p class="rc-vak-waarde tabular" :class="result.xp.gained >= 0 ? 'rc-op' : 'rc-neer'">{{ teken(result.xp.gained) }}</p>
                </div>
            </div>

            <div v-if="result.badges.length" class="rc-badges">
                <p v-for="badge in result.badges" :key="badge.key" class="rc-badge">
                    <Trophy class="size-3.5 shrink-0" aria-hidden="true" />
                    <span class="min-w-0"
                        >Nieuwe mijlpaal: <strong>{{ badge.label }}</strong></span
                    >
                </p>
            </div>

            <div class="rc-knoppen">
                <Link v-if="result.next" :href="'/players/' + result.next.id + '/reports/create'" class="rc-knop rc-knop-primair">
                    Volgende: {{ result.next.name }}
                    <ArrowRight class="size-4 shrink-0" />
                </Link>

                <button type="button" class="rc-knop" @click="emit('close')">Klaar</button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.rc-laag {
    position: fixed;
    inset: 0;
    z-index: 50;
    display: flex;
    align-items: flex-end;
    justify-content: center;
    padding: 1rem;
    background: rgba(2, 6, 15, 0.5);
}

.rc-paneel {
    position: relative;
    width: 100%;
    max-width: 28rem;
    padding: 1.25rem;
    border-radius: 1.25rem;
    color: #f1f5f9;
    background: linear-gradient(180deg, #16213a, #0b1122);
    box-shadow:
        inset 0 0 0 1px rgba(255, 255, 255, 0.1),
        0 24px 48px -18px rgba(0, 0, 0, 0.8);
    animation: rc-in 0.35s cubic-bezier(0.22, 1, 0.36, 1);
}

@keyframes rc-in {
    from {
        opacity: 0;
        transform: translateY(1.5rem);
    }
}

.rc-sluit {
    position: absolute;
    top: 0.6rem;
    right: 0.6rem;
    display: grid;
    place-items: center;
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 999px;
    /* Eigen donkere achtergrond: hij staat soms op de gouden level-balk, en
       grijs op goud is niet te zien. */
    color: #f1f5f9;
    background: rgba(2, 6, 15, 0.55);
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.14);
}

.rc-sluit:hover {
    background: rgba(2, 6, 15, 0.8);
}

.rc-level {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    margin-bottom: 0.85rem;
    /* Rechts ruimte voor de sluitknop die erboven zweeft. */
    padding: 0.65rem 2.5rem 0.65rem 0.85rem;
    border-radius: 0.85rem;
    color: #0a0f1c;
    background: linear-gradient(120deg, #f7dd8a, #d4a72c 45%, #fff0b3 70%, #e0b83c);
    box-shadow: 0 8px 24px -8px rgba(212, 167, 44, 0.6);
}

.rc-level-kop {
    display: block;
    font-size: 1rem;
    font-weight: 800;
    line-height: 1.15;
}

.rc-level-sub {
    display: block;
    font-size: 0.8125rem;
    font-weight: 600;
    opacity: 0.8;
}

.rc-level-enter-active {
    transition:
        opacity 0.4s ease,
        transform 0.4s cubic-bezier(0.22, 1, 0.36, 1);
}

.rc-level-enter-from {
    opacity: 0;
    transform: scale(0.94) translateY(-0.4rem);
}

.rc-kop {
    padding-right: 2rem;
    font-size: 0.9375rem;
    font-weight: 600;
}

.rc-lijst {
    margin: 0.75rem 0 0;
    padding: 0;
    list-style: none;
}

.rc-regel {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.35rem 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.07);
    animation: rc-regel-in 0.4s ease-out backwards;
    animation-delay: var(--rc-vertraging, 0ms);
}

@keyframes rc-regel-in {
    from {
        opacity: 0;
        transform: translateX(-0.5rem);
    }
}

.rc-label {
    min-width: 0;
    font-size: 0.875rem;
    color: #cbd5e1;
}

.rc-stil .rc-label {
    color: #94a3b8;
    font-style: italic;
}

.rc-delta {
    font-size: 1rem;
    font-weight: 800;
}

.rc-op {
    color: #22e06b;
}

.rc-neer {
    color: #f0cf6c;
}

.rc-cijfers {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.6rem;
    margin-top: 0.85rem;
}

.rc-vak {
    padding: 0.6rem 0.75rem;
    border-radius: 0.75rem;
    background: rgba(255, 255, 255, 0.06);
}

.rc-vak-label {
    font-size: 0.625rem;
    font-weight: 700;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: #94a3b8;
}

.rc-vak-waarde {
    margin-top: 0.15rem;
    font-size: 1.375rem;
    font-weight: 800;
    line-height: 1.1;
}

.rc-vorig {
    font-size: 0.875rem;
    font-weight: 600;
    color: #94a3b8;
}

.rc-badges {
    margin-top: 0.75rem;
    display: grid;
    gap: 0.4rem;
}

.rc-badge {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.45rem 0.6rem;
    border-radius: 0.65rem;
    font-size: 0.8125rem;
    color: #f0cf6c;
    background: rgba(240, 207, 108, 0.12);
    box-shadow: inset 0 0 0 1px rgba(240, 207, 108, 0.3);
}

.rc-knoppen {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 1rem;
}

.rc-knop {
    display: inline-flex;
    flex: 1 1 8rem;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    min-height: 2.75rem;
    padding: 0.55rem 1rem;
    border-radius: 0.75rem;
    font-size: 0.875rem;
    font-weight: 600;
    color: #f1f5f9;
    background: rgba(255, 255, 255, 0.08);
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.12);
}

.rc-knop:hover {
    background: rgba(255, 255, 255, 0.14);
}

.rc-knop-primair {
    color: #06210f;
    background: #22e06b;
    box-shadow: none;
}

.rc-knop-primair:hover {
    background: #3ee881;
}

@media (prefers-reduced-motion: reduce) {
    .rc-paneel,
    .rc-regel {
        animation: none;
    }
}
</style>
