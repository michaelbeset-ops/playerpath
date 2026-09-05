<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Head } from '@inertiajs/vue3';
import { Trophy } from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * De publiek gedeelde kaart. Geen navigatie, geen inlog, geen app-schil —
 * alleen de kaart. Bewust zonder achternaam, leeftijd, school of groep.
 */
const props = defineProps<{
    player: { name: string; position: string; overall_rating: number | null };
    categories: { label: string; rating: number | null }[];
    level: { key: string; label: string; description: string };
    badges: { key: string; label: string; description: string }[];
}>();

const initialen = computed(() =>
    props.player.name
        .split(' ')
        .filter(Boolean)
        .map((deel) => deel[0])
        .slice(0, 2)
        .join('')
        .toUpperCase(),
);

const balkBreedte = (rating: number | null) => (rating === null ? '0%' : rating + '%');
</script>

<template>
    <Head :title="'Spelerskaart - ' + player.name">
        <!-- Deze pagina hoort niet in Google. Delen is delen met wie je de link geeft. -->
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <div class="theme-donker flex min-h-svh flex-col items-center justify-center bg-background p-4 text-foreground">
        <div class="w-full max-w-md">
            <div class="overflow-hidden rounded-2xl border border-border bg-card">
                <div class="flex items-center gap-4 border-b border-border p-5">
                    <div class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-gold/15 text-lg font-bold text-gold">
                        {{ initialen }}
                    </div>

                    <div class="min-w-0 flex-1">
                        <p class="text-xs uppercase tracking-widest text-gold">
                            {{ player.position }}
                            <span v-if="player.overall_rating" class="text-muted-foreground">&middot; {{ level.label }}</span>
                        </p>
                        <h1 class="truncate text-xl font-bold tracking-tight">{{ player.name }}</h1>
                    </div>

                    <div class="shrink-0 text-right">
                        <p class="text-[10px] uppercase tracking-widest text-muted-foreground">Overall</p>
                        <p class="tabular text-4xl font-extrabold leading-none" :class="player.overall_rating ? 'text-primary' : 'text-muted-foreground'">
                            {{ player.overall_rating ?? '—' }}
                        </p>
                    </div>
                </div>

                <div v-if="player.overall_rating" class="space-y-3 p-5">
                    <div v-for="categorie in categories" :key="categorie.label">
                        <div class="flex items-baseline justify-between gap-3">
                            <p class="text-sm">{{ categorie.label }}</p>
                            <p class="tabular text-base font-bold leading-none">{{ categorie.rating ?? '—' }}</p>
                        </div>
                        <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-secondary">
                            <div class="h-full rounded-full bg-primary" :style="{ width: balkBreedte(categorie.rating) }"></div>
                        </div>
                    </div>
                </div>

                <div v-else class="p-8 text-center">
                    <p class="text-sm text-muted-foreground">Deze kaart heeft nog geen cijfers.</p>
                </div>

                <div v-if="badges.length" class="flex flex-wrap gap-2 border-t border-border p-5">
                    <span
                        v-for="badge in badges"
                        :key="badge.key"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-gold/30 bg-gold/10 px-2.5 py-1 text-xs font-semibold text-gold"
                    >
                        <Trophy class="size-3" />
                        {{ badge.label }}
                    </span>
                </div>
            </div>

            <div class="mt-6 flex items-center justify-center gap-2 text-xs text-muted-foreground">
                <AppLogoIcon class="size-4 text-primary" />
                Spelerskaart van PlayerPath
            </div>
        </div>
    </div>
</template>
