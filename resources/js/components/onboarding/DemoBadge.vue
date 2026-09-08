<script setup lang="ts">
/**
 * Het label "voorbeeld".
 *
 * Staat overal waar voorbeelddata in beeld komt: in het ledenbestand, in de
 * agenda, bij de groepen, bij het aanbod en bij de berichten. Zonder dit label
 * is voorbeelddata gewoon data, en dan staat er over een half jaar een
 * verzonnen kind in een echt ledenbestand.
 *
 * Klein en grijs met opzet: het is een bijschrift, geen waarschuwing. De knop
 * om alles op te ruimen staat op het dashboard, niet bij elke rij.
 */
defineProps<{ small?: boolean }>();
</script>

<template>
    <span
        class="inline-flex shrink-0 items-center rounded bg-secondary font-medium uppercase tracking-wide text-muted-foreground"
        :class="small ? 'px-1 py-0.5 text-[9px]' : 'px-1.5 py-0.5 text-[10px]'"
        title="Dit is voorbeelddata. Je ruimt het op vanaf je dashboard."
    >
        voorbeeld
    </span>
</template>
