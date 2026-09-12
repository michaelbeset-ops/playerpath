<script setup lang="ts">
import CardGlow from '@/components/CardGlow.vue';
import PlayerCardVisual, { type Kaart } from '@/components/PlayerCardVisual.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { Layers } from 'lucide-vue-next';

/**
 * Mijn kaarten: de kaart van nu bovenaan, daaronder de kaarten van vorige
 * seizoenen. Een verzameling, geen archief: elke oude kaart wordt met
 * dezelfde component getekend, met het frame van het level van toen.
 */
const props = defineProps<{
    player: { id: number; first_name: string; name: string };
    current: Kaart;
    seasons: Kaart[];
    isOwn: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: props.player.name, href: '/players/' + props.player.id + '/card' },
    { title: 'Mijn kaarten', href: '/players/' + props.player.id + '/kaarten' },
];
</script>

<template>
    <Head :title="(isOwn ? 'Mijn kaarten' : 'Kaarten van ' + player.first_name) + ' - ' + player.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-3xl p-4">
            <div class="flex items-start gap-3">
                <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                    <Layers class="size-5" />
                </span>
                <div class="min-w-0">
                    <h1 class="text-2xl font-semibold tracking-tight">{{ isOwn ? 'Mijn kaarten' : 'De kaarten van ' + player.first_name }}</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Aan het eind van elke jaargang bewaren we de kaart zoals hij toen was. Zo zie je waar
                        {{ isOwn ? 'je' : player.first_name }} vandaan {{ isOwn ? 'komt' : 'komt' }}.
                    </p>
                </div>
            </div>

            <!-- Nu -->
            <section class="mt-6">
                <div class="flex items-baseline justify-between gap-2">
                    <h2 class="font-semibold">Nu</h2>
                    <p class="text-xs text-muted-foreground">Seizoen {{ current.season }}<template v-if="current.age_category"> · {{ current.age_category.key }}</template></p>
                </div>
                <div class="theme-donker mt-3 overflow-hidden rounded-3xl bg-background p-4 text-foreground sm:p-8">
                    <CardGlow :level="current.overall === null ? 'geen' : current.level.key">
                        <PlayerCardVisual :card="current" :shareable="false" />
                    </CardGlow>
                </div>
                <Link
                    :href="'/players/' + player.id + '/card'"
                    class="mt-3 inline-flex min-h-11 items-center text-sm font-medium text-primary underline underline-offset-4"
                >
                    Naar de kaart van nu
                </Link>
            </section>

            <!-- Vorige seizoenen -->
            <section class="mt-8">
                <h2 class="font-semibold">Vorige seizoenen</h2>

                <div v-if="seasons.length" class="mt-3 grid gap-4 sm:grid-cols-2">
                    <div v-for="kaart in seasons" :key="kaart.season + (kaart.age_category?.key ?? '')" class="theme-donker overflow-hidden rounded-3xl bg-background p-4 text-foreground">
                        <p class="mb-3 text-center text-xs font-semibold uppercase tracking-widest text-muted-foreground">
                            Seizoen {{ kaart.season }}<template v-if="kaart.age_category"> · {{ kaart.age_category.key }}</template>
                        </p>
                        <CardGlow :level="kaart.overall === null ? 'geen' : kaart.level.key">
                            <PlayerCardVisual :card="kaart" :shareable="false" />
                        </CardGlow>
                    </div>
                </div>

                <div v-else class="mt-3 rounded-2xl border border-dashed border-border bg-card p-6 text-center">
                    <p class="font-medium">Nog geen oude kaarten</p>
                    <p class="mx-auto mt-1 max-w-sm text-sm text-muted-foreground">
                        Zodra {{ isOwn ? 'je' : player.first_name }} een jaargang omhoog {{ isOwn ? 'gaat' : 'gaat' }}, komt de kaart van dat seizoen hier
                        te staan — met de cijfers en het level van toen.
                    </p>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
