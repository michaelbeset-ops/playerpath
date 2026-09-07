<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import CardGlow from '@/components/CardGlow.vue';
import PlayerCardVisual, { type Kaart } from '@/components/PlayerCardVisual.vue';
import { Head } from '@inertiajs/vue3';

/**
 * De publiek gedeelde kaart. Geen navigatie, geen inlog, geen app-schil —
 * alleen de kaart. Bewust zonder achternaam, leeftijd, school of groep.
 */
defineProps<{ card: Kaart }>();
</script>

<template>
    <Head :title="'Spelerskaart - ' + card.name">
        <!-- Deze pagina hoort niet in Google. Delen is delen met wie je de link geeft. -->
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <div class="theme-donker flex min-h-svh flex-col items-center justify-center bg-background p-4 text-foreground">
        <div class="w-full max-w-sm">
            <CardGlow :level="card.overall === null ? 'geen' : card.level.key">
                <PlayerCardVisual :card="card" :shareable="false" />
            </CardGlow>

            <div class="mt-6 flex items-center justify-center gap-2 text-xs text-muted-foreground">
                <AppLogoIcon class="size-4 rounded-sm" />
                Spelerskaart van PlayerPath
            </div>
        </div>
    </div>
</template>
