<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import PlayerCardVisual from '@/components/PlayerCardVisual.vue';
import { Head } from '@inertiajs/vue3';

/**
 * De publiek gedeelde kaart. Geen navigatie, geen inlog, geen app-schil —
 * alleen de kaart. Bewust zonder achternaam, leeftijd, school of groep.
 */
defineProps<{
    player: { name: string; position: string; position_key: 'keeper' | 'field'; overall_rating: number | null };
    categories: { category: string; label: string; rating: number | null }[];
    level: { key: string; label: string; description: string };
    badges: { key: string; label: string; description: string }[];
}>();
</script>

<template>
    <Head :title="'Spelerskaart - ' + player.name">
        <!-- Deze pagina hoort niet in Google. Delen is delen met wie je de link geeft. -->
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <div class="theme-donker flex min-h-svh flex-col items-center justify-center bg-background p-4 text-foreground">
        <div class="w-full max-w-sm">
            <PlayerCardVisual
                :name="player.name"
                :position="player.position"
                :position-key="player.position_key"
                :overall="player.overall_rating"
                :categories="categories"
                :level="level"
                :badges="badges"
            />

            <div class="mt-6 flex items-center justify-center gap-2 text-xs text-muted-foreground">
                <AppLogoIcon class="size-4 text-primary" />
                Spelerskaart van PlayerPath
            </div>
        </div>
    </div>
</template>
