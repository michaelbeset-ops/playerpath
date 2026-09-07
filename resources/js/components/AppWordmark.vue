<script setup lang="ts">
/**
 * Het volledige logo: het merkteken met "playerpath" ernaast.
 *
 * Twee varianten, want een logo met donkere letters verdwijnt op een donkere
 * pagina. Welke er staat bepaalt de plek waar hij staat, niet het bestand.
 */
withDefaults(
    defineProps<{
        /** Op een donkere achtergrond de variant met witte letters. */
        donker?: boolean;
        size?: 'sm' | 'md' | 'lg';
    }>(),
    { donker: false, size: 'md' },
);

// De verhouding van het bestand is 1060 × 320, dus de hoogte bepaalt alles.
const hoogtes = { sm: 'h-8', md: 'h-10', lg: 'h-12' };
</script>

<template>
    <img :src="donker ? '/brand/logo-donker.png' : '/brand/logo.png'" alt="PlayerPath" class="w-auto object-contain" :class="hoogtes[size]" />
</template>
