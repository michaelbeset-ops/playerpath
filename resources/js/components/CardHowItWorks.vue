<script setup lang="ts">
import type { Kaart } from '@/components/PlayerCardVisual.vue';
import { ClipboardList, Dumbbell, Sparkles, Trophy } from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * "Zo werkt jouw kaart": de uitleg voor een kind, met plaatjes.
 *
 * Drie stappen (trainen, rapport, kaart groeit), daarna de vier levels als
 * kleine kaartjes in hun eigen metaal, met de punten die je ervoor nodig
 * hebt. Dezelfde kleuren als het echte frame, zodat een kind het goud van
 * de uitleg herkent op de kaart. Geen vaktaal: punten, geen "XP-events".
 */
const props = defineProps<{ card: Kaart }>();

const stappen = [
    { icoon: Dumbbell, titel: 'Je traint', tekst: 'Elke keer dat je er bent, krijg je punten. Trouw komen telt.' },
    { icoon: ClipboardList, titel: 'De trainer vult een rapport in', tekst: 'Zes cijfers, net als op school. Daar komen je cijfers op de kaart vandaan.' },
    { icoon: Sparkles, titel: 'Je kaart groeit mee', tekst: 'Word je beter, dan gaan je cijfers omhoog. Kom je vaak, dan verandert je kaart van kleur.' },
];

const bijnaam: Record<string, string> = {
    brons: 'De start',
    zilver: 'Op weg',
    goud: 'De Gouden Kaart',
    elite: 'De allerhoogste',
};

const levels = computed(() =>
    props.card.levels.map((l, i, alle) => ({
        ...l,
        actief: props.card.overall !== null && props.card.level.key === l.key,
        gehaald: props.card.overall !== null && alle.findIndex((x) => x.key === props.card.level.key) > i,
        naam: bijnaam[l.key] ?? '',
    })),
);
</script>

<template>
    <section>
        <h2 class="flex items-center gap-2 font-semibold">
            <Sparkles class="size-4 text-primary" />
            Zo werkt jouw kaart
        </h2>

        <ol class="mt-3 grid gap-2">
            <li v-for="(stap, i) in stappen" :key="stap.titel" class="flex items-start gap-3 rounded-2xl border border-border bg-card p-3">
                <span class="relative flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary/15 text-primary">
                    <component :is="stap.icoon" class="size-5" />
                    <span
                        class="absolute -left-1 -top-1 flex size-5 items-center justify-center rounded-full bg-primary text-[10px] font-bold text-primary-foreground"
                        >{{ i + 1 }}</span
                    >
                </span>
                <div class="min-w-0">
                    <p class="text-sm font-semibold">{{ stap.titel }}</p>
                    <p class="text-xs text-muted-foreground">{{ stap.tekst }}</p>
                </div>
            </li>
        </ol>

        <h3 class="mt-6 flex items-center gap-2 font-semibold">
            <Trophy class="size-4 text-gold" />
            Van brons naar goud
        </h3>
        <p class="mt-1 text-xs text-muted-foreground">Hoe meer punten, hoe mooier je kaart. Dit zijn de vier kaarten die je kunt halen.</p>

        <div class="mt-3 grid grid-cols-2 gap-3">
            <div
                v-for="level in levels"
                :key="level.key"
                class="pp-mini"
                :class="['pp-mini-' + level.key, { 'pp-mini-actief': level.actief, 'pp-mini-gehaald': level.gehaald }]"
            >
                <div class="pp-mini-binnen">
                    <p class="pp-mini-label">{{ level.label }}</p>
                    <p class="pp-mini-naam">{{ level.naam }}</p>
                    <p class="pp-mini-xp tabular">{{ level.xp === 0 ? 'vanaf de start' : 'vanaf ' + level.xp + ' punten' }}</p>
                    <p v-if="level.actief" class="pp-mini-tag">jij bent hier</p>
                    <p v-else-if="level.gehaald" class="pp-mini-tag">gehaald</p>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
/* Kleine kaartjes in het metaal van hun level; zelfde tinten als het echte frame. */
.pp-mini {
    --pp-tier: #94a3b8;
    --pp-metaal: linear-gradient(135deg, #cfd6de 0%, #8c96a3 20%, #4b5563 38%, #d9dfe6 52%, #7b8592 68%, #3f4650 84%, #b5bdc7 100%);
    position: relative;
    padding: 3px;
    border-radius: 1rem;
    background: var(--pp-metaal);
    clip-path: polygon(0 0, calc(100% - 14px) 0, 100% 14px, 100% 100%, 14px 100%, 0 calc(100% - 14px));
    opacity: 0.85;
    transition: transform 0.2s ease;
}

.pp-mini-actief {
    opacity: 1;
    transform: scale(1.03);
}

.pp-mini-gehaald {
    opacity: 1;
}

.pp-mini-binnen {
    min-height: 7.5rem;
    border-radius: 0.85rem;
    padding: 0.85rem 0.75rem;
    background: radial-gradient(80% 60% at 50% 20%, color-mix(in srgb, var(--pp-tier) 22%, transparent), transparent 70%), hsl(var(--card));
    clip-path: polygon(0 0, calc(100% - 12px) 0, 100% 12px, 100% 100%, 12px 100%, 0 calc(100% - 12px));
}

.pp-mini-label {
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: var(--pp-tier);
}

.pp-mini-naam {
    margin-top: 0.25rem;
    font-size: 0.95rem;
    font-weight: 800;
    line-height: 1.15;
}

.pp-mini-xp {
    margin-top: 0.35rem;
    font-size: 0.7rem;
    color: hsl(var(--muted-foreground));
}

.pp-mini-tag {
    display: inline-block;
    margin-top: 0.5rem;
    border-radius: 999px;
    padding: 0.15rem 0.5rem;
    font-size: 0.65rem;
    font-weight: 700;
    background: color-mix(in srgb, var(--pp-tier) 25%, transparent);
    color: var(--pp-tier);
}

.pp-mini-brons {
    --pp-tier: #e0a370;
    --pp-metaal: linear-gradient(135deg, #f2b98a 0%, #b5651d 18%, #6b3a12 36%, #f0c39a 50%, #a9581a 66%, #5a2f0e 82%, #d98a4b 100%);
}

.pp-mini-zilver {
    --pp-tier: #e2e9f0;
    --pp-metaal: linear-gradient(135deg, #ffffff 0%, #b9c3ce 18%, #5b6672 36%, #f4f7fa 50%, #9aa6b3 66%, #4a535d 82%, #dfe6ec 100%);
}

.pp-mini-goud {
    --pp-tier: #f0cf6c;
    --pp-metaal: linear-gradient(135deg, #fbe28a 0%, #d4a72c 18%, #8a5c0a 36%, #fff0b3 50%, #c9961c 66%, #6e4a06 82%, #ecc95c 100%);
}

.pp-mini-elite {
    --pp-tier: #f3e2a2;
    --pp-metaal: linear-gradient(135deg, #f3e2a2 0%, #c9b6ff 20%, #7fe3d6 40%, #f6c1ff 60%, #a6d8ff 80%, #f3e2a2 100%);
}
</style>
