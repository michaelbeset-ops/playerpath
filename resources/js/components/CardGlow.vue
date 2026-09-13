<script setup lang="ts">
/**
 * De gloed achter de spelerskaart.
 *
 * De pagina eromheen is neutraal donker, zodat de kaart het enige is dat
 * kleur heeft. Zonder iets erachter zweeft hij dan los op het vlak; deze
 * zachte, radiale gloed in de levelkleur verbindt hem met de pagina en laat
 * hem stralen. De kaart zelf verandert niet: dit is een laag eronder.
 *
 * Per level dezelfde kleur als het frame (koper, chroom, goud, holografisch),
 * en staalgrijs zolang er nog geen rapport is.
 */
defineProps<{
    /** brons · zilver · goud · elite, of 'geen' zonder rapport. */
    level: string;
}>();
</script>

<template>
    <!-- overflow-hidden met wat lucht eromheen: de gloed mag buiten de kaart
         vallen, maar nooit buiten het scherm - anders scrolt een telefoon
         zijwaarts. -->
    <div class="relative -mx-4 overflow-hidden px-4 py-5" :class="'gloed-' + level">
        <div class="gloed pointer-events-none absolute inset-0 -z-0" aria-hidden="true"></div>
        <div class="relative">
            <slot />
        </div>
    </div>
</template>

<style scoped>
.gloed {
    --gloed-kleur: rgba(148, 163, 184, 0.16);
    --gloed-kleur-2: transparent;
    background:
        radial-gradient(60% 50% at 50% 42%, var(--gloed-kleur), transparent 72%),
        radial-gradient(45% 35% at 50% 80%, var(--gloed-kleur-2), transparent 70%);
    inset: 0;
    filter: blur(26px);
}

.gloed-brons .gloed {
    --gloed-kleur: rgba(214, 140, 80, 0.28);
}

.gloed-zilver .gloed {
    --gloed-kleur: rgba(219, 228, 238, 0.22);
}

.gloed-goud .gloed {
    --gloed-kleur: rgba(240, 200, 90, 0.34);
}

.gloed-elite .gloed {
    --gloed-kleur: rgba(190, 170, 255, 0.34);
    --gloed-kleur-2: rgba(90, 220, 200, 0.2);
}
</style>
