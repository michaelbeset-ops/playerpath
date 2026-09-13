<script setup lang="ts">
import type { Kaart } from '@/components/PlayerCardVisual.vue';
import { Dialog, DialogDescription, DialogScrollContent, DialogTitle } from '@/components/ui/dialog';
import { computed } from 'vue';

/**
 * "Hoe werkt dit?" - de korte uitleg bij de kaart, voor kind en ouder.
 *
 * Vier vragen, elk met een antwoord van twee zinnen. Niet de hele uitleg
 * van de rating (die staat achter "Hoe werkt mijn rating?"), maar precies
 * wat je wilt weten als je de kaart voor het eerst ziet: wanneer stijgt
 * hij, wanneer krijg ik een nieuwe, en wat gebeurt er aan het eind.
 */
const props = defineProps<{ card: Kaart }>();

const open = defineModel<boolean>('open', { default: false });

const naam = computed(() => props.card.first_name);
const levels = computed(() => props.card.levels.filter((l) => l.xp > 0));
const laatste = computed(() => props.card.levels[props.card.levels.length - 1]?.label ?? 'het hoogste level');
</script>

<template>
    <Dialog v-model:open="open">
        <DialogScrollContent class="theme-donker max-w-md rounded-2xl bg-background text-foreground sm:rounded-2xl">
            <div class="space-y-1">
                <DialogTitle class="text-lg font-bold">Hoe werkt dit?</DialogTitle>
                <DialogDescription class="text-sm text-muted-foreground">De kaart van {{ naam }} in vier vragen.</DialogDescription>
            </div>

            <div class="mt-4 space-y-4 text-sm leading-relaxed">
                <div class="rounded-xl border border-border bg-card p-4">
                    <p class="font-semibold">Wanneer stijgt mijn kaart?</p>
                    <p class="mt-1 text-muted-foreground">
                        Na elke training vinkt de trainer af wie er was en vult hij een rapport in. Aanwezig zijn en een rapport leveren allebei punten
                        (XP) op, en groei in het rapport levert extra punten op. Die punten zie je op de balk onder de kaart.
                    </p>
                </div>

                <div class="rounded-xl border border-border bg-card p-4">
                    <p class="font-semibold">Wanneer krijg ik een nieuwe kaart?</p>
                    <p class="mt-1 text-muted-foreground">
                        Zodra je punten een grens halen, verandert de kaart vanzelf van kleur:
                        <template v-for="(l, i) in levels" :key="l.key">
                            <span class="font-medium text-foreground">{{ l.label }}</span> vanaf {{ l.xp }}<template v-if="i < levels.length - 1">, </template><template v-else>.</template>
                        </template>
                        Je hoeft er niets voor te doen; de balk zegt precies hoeveel je nog nodig hebt.
                    </p>
                </div>

                <div class="rounded-xl border border-border bg-card p-4">
                    <p class="font-semibold">Wat gebeurt er aan het einde van het seizoen?</p>
                    <p class="mt-1 text-muted-foreground">
                        <template v-if="card.season_ends">Dit seizoen ({{ card.season_label }}) loopt tot {{ card.season_ends }}.</template>
                        <template v-else>Aan het eind van het seizoen of blok</template>
                        krijg je je eindkaart: de kaart zoals hij dan is, met je cijfers en je level, bewaard bij Mijn kaarten. Daarna beginnen de punten
                        opnieuw en werk je naar de volgende kaart toe. Je cijfers blijven staan.
                    </p>
                </div>

                <div class="rounded-xl border border-border bg-card p-4">
                    <p class="font-semibold">Kan mijn kaart ook zakken?</p>
                    <p class="mt-1 text-muted-foreground">
                        Punten raak je niet kwijt door een mindere training. De cijfers per categorie kunnen wel dalen als een rapport lager
                        uitvalt, want die zeggen hoe je er nu voor staat. Het hoogste level is {{ laatste }}.
                    </p>
                </div>
            </div>
        </DialogScrollContent>
    </Dialog>
</template>
