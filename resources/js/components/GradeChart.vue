<script setup lang="ts">
import { kleurVan, useGrading } from '@/lib/grade';
import { computed } from 'vue';

/**
 * De groei in kleuren: van werkpunt (onder) naar top (boven).
 *
 * Vier banden, één kolom per rapport, en in elke kolom een stip in de kleur
 * van dat rapport. Zo zie je in één oogopslag of de stippen omhoog klimmen,
 * zonder dat er ergens een getal staat. Een lijn tussen de stippen is er
 * bewust niet: tussen twee kleuren ligt geen tussenstand.
 *
 * Hooguit de laatste twaalf rapporten: meer kolommen worden op een telefoon
 * streepjes. De datum staat in de title van elke stip, en onder de grafiek
 * staan begin en eind.
 */
const props = withDefaults(
    defineProps<{
        /** Kaartwaarden per rapport (0-100), null waar niets is ingevuld. */
        series: (number | null)[];
        labels: string[];
        compact?: boolean;
    }>(),
    { compact: false },
);

const { niveaus, niveauVoor } = useGrading();

const MAX = 12;

const punten = computed(() =>
    props.series
        .map((waarde, i) => ({ waarde, label: props.labels[i] ?? '' }))
        .slice(-MAX)
        .map((p) => ({ ...p, niveau: niveauVoor(p.waarde) })),
);

// Van boven naar beneden: top bovenaan.
const banden = computed(() => [...niveaus.value].reverse());

const rijVan = (key: string | undefined) => banden.value.findIndex((b) => b.key === key) + 1;
</script>

<template>
    <div class="flex gap-2">
        <!-- De labels van de banden -->
        <div v-if="!compact" class="grid shrink-0 text-[10px] font-medium" :style="{ gridTemplateRows: `repeat(${banden.length}, minmax(0, 1fr))` }">
            <span v-for="band in banden" :key="band.key" class="flex items-center" :style="{ color: kleurVan(band.key) }">{{ band.label }}</span>
        </div>

        <div class="relative min-w-0 flex-1">
            <!-- De banden zelf, zacht getint -->
            <div class="absolute inset-0 grid" :style="{ gridTemplateRows: `repeat(${banden.length}, minmax(0, 1fr))` }" aria-hidden="true">
                <span
                    v-for="band in banden"
                    :key="band.key"
                    class="border-b border-background/60 first:rounded-t-md last:rounded-b-md last:border-0"
                    :style="{ backgroundColor: kleurVan(band.key, 0.08) }"
                ></span>
            </div>

            <!-- Eén kolom per rapport, met de stip in zijn band -->
            <div
                class="relative grid"
                :class="compact ? 'h-16' : 'h-28'"
                :style="{
                    gridTemplateColumns: `repeat(${Math.max(punten.length, 1)}, minmax(0, 1fr))`,
                    gridTemplateRows: `repeat(${banden.length}, minmax(0, 1fr))`,
                }"
                role="img"
                :aria-label="punten.map((p) => p.label + ': ' + (p.niveau?.label ?? 'geen')).join(', ')"
            >
                <template v-for="(punt, i) in punten" :key="i">
                    <span
                        v-if="punt.niveau"
                        class="flex items-center justify-center"
                        :style="{ gridColumn: i + 1, gridRow: rijVan(punt.niveau.key) }"
                        :title="punt.label + ': ' + punt.niveau.label"
                    >
                        <span
                            class="rounded-full ring-2 ring-card"
                            :class="[compact ? 'size-2.5' : 'size-3.5', i === punten.length - 1 ? 'scale-125' : '']"
                            :style="{ backgroundColor: kleurVan(punt.niveau.key) }"
                        ></span>
                    </span>
                </template>
            </div>
        </div>
    </div>
</template>
