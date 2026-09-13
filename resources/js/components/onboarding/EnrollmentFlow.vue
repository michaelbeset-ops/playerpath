<script setup lang="ts">
import { Check } from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * Zo verloopt een inschrijving bij jou - van klik tot kind in de groep.
 *
 * Een instelling als "handmatig goedkeuren" zegt een eigenaar weinig; "jij
 * krijgt een melding en keurt goed, dán pas krijgt de ouder een betaalverzoek"
 * wél. Dit blokje tekent die keten en verandert mee met de antwoorden, zodat
 * je ziet wat een vinkje doet vóórdat je opslaat.
 *
 * Elke stap zegt wie iets doet: de ouder, jij, of niemand (het gaat vanzelf).
 * Kaarten in een raster van twee, niet een rij van vijf: het staat in een
 * smalle kolom naast het voorbeeld, en vijf naast elkaar kapt daar elke
 * titel af.
 */
const props = defineProps<{
    approval: string;
    paymentTypes: string[];
    trialEnabled: boolean;
}>();

const betaalzin = computed(() => {
    const namen: Record<string, string> = { upfront: 'in één keer', installments: 'in termijnen', monthly: 'per maand' };
    const delen = props.paymentTypes.map((t) => namen[t]).filter(Boolean);

    if (delen.length === 0) return 'via een betaallink';
    if (delen.length === 1) return delen[0];

    return delen.slice(0, -1).join(', ') + ' of ' + delen[delen.length - 1] + ' - de ouder kiest';
});

const stappen = computed(() => {
    const lijst: { wie: 'ouder' | 'jij' | 'auto'; titel: string; uitleg: string }[] = [
        {
            wie: 'ouder',
            titel: 'Kiest iets op je inschrijfpagina',
            uitleg: props.trialEnabled ? 'Een blok, een abonnement, een kamp - of eerst een proefles.' : 'Een blok, een abonnement of een kamp.',
        },
        {
            wie: 'ouder',
            titel: 'Vult de gegevens van het kind in',
            uitleg: 'Naam, geboortedatum, positie en de toestemmingen. Een account ontstaat vanzelf.',
        },
    ];

    if (props.approval === 'automatic') {
        lijst.push({ wie: 'ouder', titel: 'Betaalt ' + betaalzin.value, uitleg: 'Meteen na het aanmelden, via een betaallink.' });
        lijst.push({ wie: 'auto', titel: 'Het kind staat in de groep', uitleg: 'Zodra de betaling binnen is. Jij hoeft niets te doen.' });
    } else {
        lijst.push({ wie: 'jij', titel: 'Jij keurt de aanmelding goed', uitleg: 'Je krijgt een melding en bekijkt wie het is. Tot dan is er niets betaald.' });
        lijst.push({ wie: 'ouder', titel: 'Betaalt ' + betaalzin.value, uitleg: 'Via de betaallink in de mail die na je goedkeuring uitgaat.' });
        lijst.push({ wie: 'auto', titel: 'Het kind staat in de groep', uitleg: 'Zodra de betaling binnen is. Vanaf dan staat het op de aanwezigheidslijst.' });
    }

    return lijst;
});

const wieLabel: Record<string, string> = { ouder: 'De ouder', jij: 'Jij', auto: 'Vanzelf' };
const wieKlasse: Record<string, string> = {
    ouder: 'bg-secondary text-muted-foreground',
    jij: 'bg-primary text-primary-foreground',
    auto: 'bg-primary/15 text-primary',
};
</script>

<template>
    <ol class="grid gap-2 sm:grid-cols-2">
        <li v-for="(stap, i) in stappen" :key="i" class="flex gap-3 rounded-xl border border-border bg-background p-3">
            <span class="flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold" :class="wieKlasse[stap.wie]">
                <Check v-if="stap.wie === 'auto'" class="size-3.5" />
                <template v-else>{{ i + 1 }}</template>
            </span>
            <span class="min-w-0">
                <span class="block text-[11px] font-medium uppercase tracking-wide text-muted-foreground">{{ wieLabel[stap.wie] }}</span>
                <span class="block text-sm font-medium leading-snug">{{ stap.titel }}</span>
                <span class="mt-0.5 block text-xs leading-relaxed text-muted-foreground">{{ stap.uitleg }}</span>
            </span>
        </li>
    </ol>
</template>
