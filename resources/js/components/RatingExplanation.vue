<script setup lang="ts">
import type { Kaart } from '@/components/PlayerCardVisual.vue';
import { Dialog, DialogDescription, DialogScrollContent, DialogTitle } from '@/components/ui/dialog';
import { kleurVan, STANDAARD_NIVEAUS } from '@/lib/grade';
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

// Kleuren of cijfers: in kleuren gaat de uitleg nergens over getallen.
const kleuren = computed(() => props.card.grading === 'kleuren');

// De inzetkaart: uitleg in kindertaal, zonder cijfers.
const inzet = computed(() => props.card.card_mode === 'inzet');
const regels = computed(() => props.card.effort?.rules ?? { attendance: 10, effort: [], attitude: [] });
const niveaus = STANDAARD_NIVEAUS;
</script>

<template>
    <Dialog v-model:open="open">
        <DialogScrollContent class="theme-donker max-w-md rounded-2xl bg-background text-foreground sm:rounded-2xl">
            <div class="space-y-1">
                <DialogTitle class="text-lg font-bold">{{ kleuren || inzet ? 'Hoe werkt mijn kaart?' : 'Hoe werkt mijn rating?' }}</DialogTitle>
                <DialogDescription class="text-sm text-muted-foreground">
                    {{
                        inzet
                            ? 'Hoe de kaart van ' + voornaam + ' groeit.'
                            : kleuren
                              ? 'Wat de kleuren op de kaart van ' + voornaam + ' betekenen.'
                              : 'Wat de getallen op de kaart van ' + voornaam + ' betekenen.'
                    }}
                </DialogDescription>
            </div>

            <div v-if="inzet" class="space-y-5 text-sm leading-relaxed">
                <section>
                    <h3 class="font-semibold text-gold">Iedereen begint gelijk</h3>
                    <p class="mt-1 text-muted-foreground">
                        Aan het begin van het seizoen heeft iedereen dezelfde kaart. Het maakt niet uit hoe goed je al bent: niemand heeft een voorsprong.
                    </p>
                </section>

                <section>
                    <h3 class="font-semibold text-gold">Zo verdien je punten</h3>
                    <p class="mt-1 text-muted-foreground">Na elke training geeft je trainer je punten. Niet voor hoe goed je al bent, maar voor hoe je je best doet.</p>
                    <ul class="mt-2 space-y-1.5">
                        <li class="flex items-baseline justify-between gap-3">
                            <span class="text-muted-foreground">Je bent er bij de training</span>
                            <strong class="tabular shrink-0 text-foreground">+{{ regels.attendance }}</strong>
                        </li>
                        <li v-for="t in regels.effort" :key="'e' + t.key" class="flex items-baseline justify-between gap-3">
                            <span class="text-muted-foreground">Inzet: {{ t.label }}</span>
                            <strong class="tabular shrink-0 text-foreground">+{{ t.points }}</strong>
                        </li>
                        <li v-for="t in regels.attitude" :key="'h' + t.key" class="flex items-baseline justify-between gap-3">
                            <span class="text-muted-foreground">Luisteren: {{ t.label }}</span>
                            <strong class="tabular shrink-0 text-foreground">+{{ t.points }}</strong>
                        </li>
                    </ul>
                </section>

                <section>
                    <h3 class="font-semibold text-gold">Punten gaan nooit omlaag</h3>
                    <p class="mt-1 text-muted-foreground">
                        Je kunt geen punten kwijtraken en je krijgt nooit een onvoldoende. Ging een training minder? Dan kost dat je niks. Wie traint, groeit.
                    </p>
                </section>

                <section>
                    <h3 class="font-semibold text-gold">Genoeg punten? Dan wordt je kaart mooier</h3>
                    <p class="mt-1 text-muted-foreground">
                        Is je balk vol, dan krijg je vanzelf een nieuwe kaart<template v-if="levels.length"
                            >:
                            <span v-for="(l, i) in levels" :key="l.key"
                                >{{ i === 0 ? '' : i === levels.length - 1 ? ' en ' : ', ' }}<strong class="text-foreground">{{ l.label }}</strong> bij
                                {{ l.xp }} punten</span
                            ></template
                        >. Werk je vaak hard of luister je goed, dan krijg je ook badges, zoals <strong class="text-foreground">Doorzetter</strong>.
                    </p>
                </section>

                <section>
                    <h3 class="font-semibold text-gold">En waar word ik beter in?</h3>
                    <p class="mt-1 text-muted-foreground">
                        Aan het begin en aan het eind van een cursus kijkt je trainer waar je staat, met kleuren. Dat zie je bij Voortgang. Het gaat alleen over
                        jou, niet over wie de beste is.
                    </p>
                </section>

                <section v-if="audience === 'trainer'" class="rounded-xl border border-border bg-card p-4">
                    <h3 class="font-semibold text-primary">Voor trainers: zo geef je inzetpunten</h3>
                    <ul class="mt-1 list-disc space-y-1 pl-5 text-muted-foreground">
                        <li>Na de training: per kind aanwezig, inzet en houding. Een paar tikken per kind.</li>
                        <li>Het gaat om inzet, niet om talent. Het kind dat minder kan maar alles geeft, verdient de hoogste inzet.</li>
                        <li>Niets aantikken mag; het kost nooit punten.</li>
                    </ul>
                </section>
            </div>

            <div v-else class="space-y-5 text-sm leading-relaxed">
                <!-- In kleuren: geen getal, vier kleuren -->
                <section v-if="kleuren">
                    <h3 class="font-semibold text-gold">De kleuren</h3>
                    <p class="mt-1 text-muted-foreground">
                        Na de training kiest de trainer per onderdeel een kleur. Het gaat om <strong class="text-foreground">jouw eigen groei</strong>,
                        niet om wie de beste is.
                    </p>
                    <ul class="mt-2 space-y-1.5">
                        <li v-for="n in niveaus" :key="n.key" class="flex items-center gap-2">
                            <span class="inline-block size-3 shrink-0 rounded-full" :style="{ backgroundColor: kleurVan(n.key) }"></span>
                            <strong class="text-foreground">{{ n.label }}</strong>
                            <span class="text-muted-foreground">
                                {{ { rood: 'hier gaan we samen aan werken', oranje: 'je bent op weg', groen: 'dit gaat goed', blauw: 'dit is echt top' }[n.key] }}
                            </span>
                        </li>
                    </ul>
                    <p class="mt-2 text-muted-foreground">
                        De grote kleur op de kaart is hoe het in het algemeen gaat. De laatste drie trainingen tellen mee, dus één mindere dag
                        verandert je kaart niet meteen.
                    </p>
                </section>

                <section v-else>
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
                        <li>
                            <strong class="text-foreground">Beter worden.</strong>
                            {{ kleuren ? 'Ga je vooruit tussen twee trainingen, dan krijg je extra XP.' : 'Groeit je rating tussen twee rapporten, dan krijg je extra XP.' }}
                        </li>
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
                        XP gaat nooit omlaag. Wie trouw komt trainen haalt goud, ook als {{ kleuren ? 'nog niet alles groen is' : 'de rating nog niet zo hoog is' }}.
                    </p>
                    <p class="mt-2 text-muted-foreground">
                        Badges zijn mijlpalen: je eerste rapport, vijf trainingen aanwezig, tien punten gegroeid. De laatste drie staan op je kaart.
                    </p>
                    <p class="mt-2 text-xs text-muted-foreground">
                        Sinds september 2026 telt het level je inzet. Daarvoor hing het aan de rating; wie toen goud zag, begint nu bij brons en bouwt
                        het opnieuw op. Je rating zelf is niet veranderd.
                    </p>
                </section>

                <section v-if="kleuren">
                    <h3 class="font-semibold text-gold">Kan een kleur ook terug?</h3>
                    <p class="mt-1 text-muted-foreground">
                        Ja, dat kan, en dat is niet erg. Een mindere periode hoort bij leren. Je XP en je level blijven gewoon staan, en een paar
                        goede trainingen maken het weer goed.
                    </p>
                </section>

                <section v-else>
                    <h3 class="font-semibold text-gold">Waarom kan mijn rating dalen?</h3>
                    <p class="mt-1 text-muted-foreground">
                        Omdat de laatste drie rapporten tellen. Een mindere training weegt dus even mee, en verdwijnt weer zodra er nieuwe rapporten
                        zijn. Ga je naar een hogere leeftijdscategorie, dan wordt de lat hoger: dezelfde training telt dan iets minder zwaar. Dat is
                        normaal, en je XP en je level blijven gewoon staan.
                    </p>
                </section>

                <section v-if="audience === 'trainer' && kleuren" class="rounded-xl border border-border bg-card p-4">
                    <h3 class="font-semibold text-primary">Voor trainers: zo kies je een kleur</h3>
                    <ul class="mt-1 list-disc space-y-1 pl-5 text-muted-foreground">
                        <li>
                            <strong class="text-foreground">Kijk naar het kind zelf.</strong> Groen is "gaat goed voor waar dit kind nu staat", blauw
                            is "valt echt op", oranje is "op weg", rood is "hier gaan we aan werken".
                        </li>
                        <li>Wees consequent: dezelfde training, dezelfde kleur. Kinderen en ouders zien geen cijfers, alleen de kleuren.</li>
                        <li>Een toelichting erbij maakt een kleur pas echt waardevol voor ouders.</li>
                    </ul>
                </section>

                <section v-if="audience === 'trainer' && !kleuren" class="rounded-xl border border-border bg-card p-4">
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
