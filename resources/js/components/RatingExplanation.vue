<script setup lang="ts">
import type { Kaart } from '@/components/PlayerCardVisual.vue';
import { Dialog, DialogDescription, DialogScrollContent, DialogTitle } from '@/components/ui/dialog';
import { computed } from 'vue';

/**
 * "Hoe werkt mijn rating?" - de uitleg bij de kaart.
 *
 * Geschreven voor een kind en zijn ouder, in korte alinea's. De trainer krijgt
 * er een blok bij over hoe hij scoort, want de belangrijkste regel - beoordeel
 * ten opzichte van wat normaal is voor deze leeftijd - is er een die hij elke
 * keer opnieuw moet toepassen.
 *
 * Het paneel is bewust donker, ook binnen de lichte admin-schil: het hoort bij
 * de kaart, niet bij de werkvloer.
 */
const props = defineProps<{
    card: Kaart;
    audience: 'gezin' | 'trainer';
}>();

const open = defineModel<boolean>('open', { default: false });

const voornaam = computed(() => props.card.first_name);

const categorie = computed(() => props.card.age_category?.label ?? 'zijn leeftijd');

// De levels zoals de school ze heeft ingesteld, zodat de uitleg klopt met de
// drempels die deze kaart echt gebruikt.
const levels = computed(() => props.card.levels.filter((l) => l.xp > 0));
</script>

<template>
    <Dialog v-model:open="open">
        <DialogScrollContent class="theme-donker max-w-md rounded-2xl bg-background text-foreground sm:rounded-2xl">
            <div class="space-y-1">
                <DialogTitle class="text-lg font-bold">Hoe werkt mijn rating?</DialogTitle>
                <DialogDescription class="text-sm text-muted-foreground">
                    Wat de getallen op de kaart van {{ voornaam }} betekenen.
                </DialogDescription>
            </div>

            <div class="space-y-5 text-sm leading-relaxed">
                <section>
                    <h3 class="font-semibold text-gold">Het grote getal</h3>
                    <p class="mt-1 text-muted-foreground">
                        Dat is je rating: hoe goed je bent <strong class="text-foreground">vergeleken met andere spelers van {{ categorie }}</strong
                        >. Een 70 betekent dus niet "70% van een prof", maar "goed voor iemand van jouw leeftijd". Zo kun je op elke leeftijd een
                        mooie kaart hebben.
                    </p>
                    <p class="mt-2 text-muted-foreground">
                        De trainer geeft na een training cijfers van 1 tot 10. Op de kaart wordt dat keer tien: een 8 leest als 80. De laatste drie
                        rapporten tellen mee, zodat één mindere dag je kaart niet verpest.
                    </p>
                </section>

                <section>
                    <h3 class="font-semibold text-gold">Zo ga je omhoog</h3>
                    <ul class="mt-1 list-disc space-y-1 pl-5 text-muted-foreground">
                        <li><strong class="text-foreground">Vaak trainen.</strong> Elke training waar je bent levert XP op.</li>
                        <li><strong class="text-foreground">Beter worden.</strong> Groeit je rating tussen twee rapporten, dan krijg je extra XP.</li>
                        <li>Elk rapport dat de trainer invult levert ook XP op.</li>
                    </ul>
                </section>

                <section>
                    <h3 class="font-semibold text-gold">De zes categorieën</h3>
                    <dl class="mt-1 space-y-1">
                        <div v-for="c in card.categories" :key="c.category" class="flex gap-2">
                            <dt class="w-28 shrink-0 font-medium">{{ c.label }}</dt>
                            <dd class="text-muted-foreground">{{ c.hint ?? '' }}</dd>
                        </div>
                    </dl>
                </section>

                <section>
                    <h3 class="font-semibold text-gold">Levels en badges</h3>
                    <p class="mt-1 text-muted-foreground">
                        Het frame van je kaart laat je level zien. Het level gaat over <strong class="text-foreground">inzet</strong>: je XP.
                        <template v-if="levels.length">
                            <span v-for="(l, i) in levels" :key="l.key"
                                >{{ i === 0 ? '' : i === levels.length - 1 ? ' en ' : ', '
                                }}<strong class="text-foreground">{{ l.label }}</strong> vanaf {{ l.xp }} XP</span
                            >.
                        </template>
                        XP gaat nooit omlaag. Wie trouw komt trainen haalt goud, ook als de rating nog niet zo hoog is.
                    </p>
                    <p class="mt-2 text-muted-foreground">
                        Badges zijn mijlpalen: je eerste rapport, vijf trainingen aanwezig, tien punten gegroeid. De laatste drie staan op je kaart.
                    </p>
                    <p class="mt-2 text-xs text-muted-foreground">
                        Sinds september 2026 telt het level je inzet. Daarvoor hing het aan de rating; wie toen goud zag, begint nu bij brons en bouwt
                        het opnieuw op. Je rating zelf is niet veranderd.
                    </p>
                </section>

                <section>
                    <h3 class="font-semibold text-gold">Waarom kan mijn rating dalen?</h3>
                    <p class="mt-1 text-muted-foreground">
                        Omdat de laatste drie rapporten tellen. Een mindere training weegt dus even mee, en verdwijnt weer zodra er nieuwe rapporten
                        zijn. Ga je naar een hogere leeftijdscategorie, dan wordt de lat hoger: dezelfde training telt dan iets minder zwaar. Dat is
                        normaal, en je XP en je level blijven gewoon staan.
                    </p>
                </section>

                <section v-if="audience === 'trainer'" class="rounded-xl border border-border bg-card p-4">
                    <h3 class="font-semibold text-primary">Voor trainers: zo geef je een cijfer</h3>
                    <ul class="mt-1 list-disc space-y-1 pl-5 text-muted-foreground">
                        <li>
                            <strong class="text-foreground">Beoordeel ten opzichte van wat normaal is voor deze leeftijd.</strong> Een 6 is "zoals je
                            van een {{ card.age_category?.key ?? 'speler' }} verwacht", een 8 is "valt op", een 9 of 10 is uitzonderlijk.
                        </li>
                        <li>
                            De app corrigeert je cijfer <strong class="text-foreground">niet</strong> op leeftijd. Wat jij opschrijft is wat op de
                            kaart komt; daarom moet de vergelijking met leeftijdsgenoten in je hoofd zitten, niet in een formule.
                        </li>
                        <li>Wees consequent tussen spelers: dezelfde training, hetzelfde cijfer. Kleine stappen (7 → 7,5) zijn prima.</li>
                        <li>Gaat een speler naar een hogere categorie, dan mag zijn cijfer iets zakken. Dat is eerlijk, niet streng.</li>
                    </ul>
                </section>
            </div>
        </DialogScrollContent>
    </Dialog>
</template>
