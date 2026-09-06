<script setup lang="ts">
import { computed } from 'vue';

/**
 * Een cijfer van 1 tot 10, in stappen van een tiende.
 *
 * Een trainer denkt in "een zeven, maar wel een goeie". Met hele cijfers moest
 * hij kiezen tussen 7 en 8, en dan verdwijnt precies het verschil dat hij zag.
 *
 * Drie dingen die dit op een telefoon bruikbaar houden:
 *
 * 1. **De grijper is 32 pixels.** Ruim boven de 24 die WCAG als ondergrens
 *    noemt, en groot genoeg om met een duim te raken zonder te kijken.
 * 2. **`touch-action: none` op de schuif.** Zonder dat scrollt de pagina mee
 *    zodra je verticaal afwijkt, en dan springt het cijfer terug.
 * 3. **De waarde staat groot naast de schuif**, ook tijdens het slepen. Onder
 *    je duim zie je de schuif zelf niet.
 */
const model = defineModel<number | null>({ required: true });

const props = defineProps<{
    label: string;
    /** Voor de kleur van de waarde: groen is goed, oranje vraagt aandacht. */
    id: string;
}>();

// Zonder waarde staat de schuif in het midden, maar het cijfer op een streepje:
// een 5,5 tonen die niemand heeft gegeven zou een cijfer verzinnen.
const positie = computed(() => model.value ?? 5.5);

const kleur = computed(() => {
    if (model.value === null) {
        return 'text-muted-foreground';
    }

    return model.value >= 6 ? 'text-primary' : 'text-warning';
});

// Nederlandse komma. "7.4" leest als een prijs, niet als een cijfer.
const getoond = computed(() => (model.value === null ? '—' : model.value.toFixed(1).replace('.', ',')));

const zet = (event: Event) => (model.value = Number((event.target as HTMLInputElement).value));

const stap = (verschil: number) => {
    const nieuw = Math.min(10, Math.max(1, Math.round(((model.value ?? 5.5) + verschil) * 10) / 10));

    model.value = nieuw;
};
</script>

<template>
    <div class="flex items-center gap-3">
        <!-- Fijnregelen zonder te slepen: op een telefoon is een tiende
             raken met een duim lastig, met een knop niet. -->
        <button
            type="button"
            class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-border bg-background text-lg leading-none text-muted-foreground transition hover:border-primary hover:text-foreground"
            :aria-label="props.label + ': een tiende lager'"
            @click.stop="stap(-0.1)"
        >
            &minus;
        </button>

        <input
            :id="props.id"
            type="range"
            min="1"
            max="10"
            step="0.1"
            :value="positie"
            class="pp-schuif h-11 min-w-0 flex-1 cursor-pointer touch-none appearance-none bg-transparent"
            :aria-label="props.label"
            :aria-valuetext="getoond"
            @input="zet"
            @click.stop
        />

        <button
            type="button"
            class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-border bg-background text-lg leading-none text-muted-foreground transition hover:border-primary hover:text-foreground"
            :aria-label="props.label + ': een tiende hoger'"
            @click.stop="stap(0.1)"
        >
            +
        </button>

        <!-- Vaste breedte, zodat de schuif niet verspringt bij 10,0 -->
        <p class="tabular w-14 shrink-0 text-right text-2xl font-bold leading-none" :class="kleur">{{ getoond }}</p>
    </div>
</template>

<style scoped>
/*
 * De schuif zelf. Alle kleuren via de tokens, zodat hij in beide thema's klopt.
 * Chrome en Firefox willen elk hun eigen selector; die kun je niet combineren,
 * want een browser die er één niet kent gooit de hele regel weg.
 */
.pp-schuif::-webkit-slider-runnable-track {
    height: 0.5rem;
    border-radius: 999px;
    background: hsl(var(--secondary));
}

.pp-schuif::-moz-range-track {
    height: 0.5rem;
    border-radius: 999px;
    background: hsl(var(--secondary));
}

.pp-schuif::-webkit-slider-thumb {
    appearance: none;
    margin-top: -0.75rem;
    width: 2rem;
    height: 2rem;
    border-radius: 999px;
    border: 2px solid hsl(var(--card));
    background: hsl(var(--primary));
    box-shadow: 0 1px 4px hsl(var(--foreground) / 0.25);
}

.pp-schuif::-moz-range-thumb {
    width: 2rem;
    height: 2rem;
    border-radius: 999px;
    border: 2px solid hsl(var(--card));
    background: hsl(var(--primary));
    box-shadow: 0 1px 4px hsl(var(--foreground) / 0.25);
}

.pp-schuif:focus-visible {
    outline: none;
}

.pp-schuif:focus-visible::-webkit-slider-thumb {
    box-shadow: 0 0 0 3px hsl(var(--primary) / 0.35);
}

.pp-schuif:focus-visible::-moz-range-thumb {
    box-shadow: 0 0 0 3px hsl(var(--primary) / 0.35);
}
</style>
