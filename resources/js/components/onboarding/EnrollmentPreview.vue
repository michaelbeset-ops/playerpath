<script setup lang="ts">
import { CalendarDays, ExternalLink, MapPin } from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * Zo ziet je inschrijfpagina eruit voor een ouder — met de keuzes van nu.
 *
 * Een nagebouwde, verkleinde versie van `/inschrijven/{slug}`: per gekozen
 * aanbodvorm één voorbeeldkaart, met een zin die zegt wanneer je erop kunt
 * inschrijven. Het zijn verzonnen namen en prijzen; het gaat om de vorm.
 * De echte pagina staat één klik verderop, in een nieuw tabblad — die toont
 * je echte aanbod, dus ook wat je nog niet hebt aangemaakt staat er níét op.
 */
const props = defineProps<{
    schoolName: string;
    slug: string;
    offeringTypes: string[];
    moments: string[];
    trainingOpen: boolean;
    trainingPaymentMethods: string[];
    trainingRequiresApproval: boolean;
    trialEnabled: boolean;
    trialAmount: string;
}>();

/** Eén voorbeeld per aanbodvorm, zoals hij op de pagina komt te staan. */
const voorbeelden: Record<string, { naam: string; prijs: string; wanneer: string; extra?: string }> = {
    doorlopend: { naam: 'Keeperstraining wekelijks', prijs: '€ 35,00 per maand', wanneer: 'Instromen kan elk moment', extra: 'Woensdag 18:00 · Sportpark De Vliert' },
    blok: { naam: 'Najaarsblok keepers O12', prijs: '€ 120,00', wanneer: 'Inschrijven tot 1 oktober', extra: '8 trainingen · 2 okt t/m 20 nov' },
    kamp: { naam: 'Herfstkamp', prijs: '€ 95,00', wanneer: 'Nog 6 plekken', extra: 'Ma 21 t/m wo 23 oktober' },
    losse_training: { naam: 'Losse training donderdag', prijs: '€ 15,00', wanneer: 'Inschrijven tot de dag zelf', extra: 'Donderdag 19 sep · 18:00' },
    privetraining: { naam: 'Privétraining', prijs: '€ 45,00 per uur', wanneer: 'Kies zelf een moment' },
    small_group: { naam: 'Small group (max. 4)', prijs: '€ 25,00 per training', wanneer: 'Reeks van 6 momenten' },
    rittenkaart: { naam: 'Rittenkaart 10 trainingen', prijs: '€ 130,00', wanneer: 'Instromen kan elk moment', extra: '10 beurten · 6 maanden geldig' },
    proefles: { naam: 'Proefles', prijs: 'gratis', wanneer: 'Eén keer meetrainen' },
};

const kaarten = computed(() =>
    props.offeringTypes
        .filter((t) => t !== 'proefles')
        .map((t) => voorbeelden[t])
        .filter(Boolean)
        .slice(0, 4),
);

const proefles = computed(() =>
    props.trialEnabled && props.offeringTypes.includes('proefles')
        ? { ...voorbeelden.proefles, prijs: props.trialAmount && props.trialAmount !== '0,00' ? '€ ' + props.trialAmount : 'gratis' }
        : null,
);

/** De zin bovenaan de pagina die zegt wanneer je kunt instappen. */
const instapzin = computed(() => {
    const delen: string[] = [];
    if (props.moments.includes('anytime')) delen.push('het hele jaar door');
    if (props.moments.includes('before_block')) delen.push('vóór de start van een blok');
    if (props.moments.includes('single_training')) delen.push('voor een losse training');
    if (props.moments.includes('camp')) delen.push('voor kampen');

    if (delen.length === 0) return 'Kies hiernaast wanneer ouders kunnen inschrijven.';

    return 'Inschrijven kan ' + (delen.length === 1 ? delen[0] : delen.slice(0, -1).join(', ') + ' en ' + delen[delen.length - 1]) + '.';
});

const betaalwijzeZin = computed(() => {
    const w: string[] = [];
    if (props.trainingPaymentMethods.includes('online')) w.push('online');
    if (props.trainingPaymentMethods.includes('cash')) w.push('contant');

    return w.length ? w.join(' of ') : 'contant';
});

const echteUrl = computed(() => '/inschrijven/' + props.slug);
</script>

<template>
    <div class="overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
        <div class="flex items-center justify-between gap-3 border-b border-border bg-secondary/60 px-4 py-2.5">
            <p class="text-xs font-medium text-muted-foreground">Zo ziet een ouder je inschrijfpagina</p>
            <a
                :href="echteUrl"
                target="_blank"
                rel="noopener"
                class="inline-flex min-h-11 items-center gap-1.5 text-xs font-medium text-primary hover:underline"
            >
                Echte pagina openen
                <ExternalLink class="size-3.5" />
            </a>
        </div>

        <!-- De nagebouwde pagina, kleiner dan echt. Verzonnen namen en prijzen. -->
        <div class="bg-background p-4">
            <p class="text-base font-semibold">{{ schoolName || 'Jouw school' }}</p>
            <p class="mt-0.5 text-xs text-muted-foreground">{{ instapzin }}</p>

            <div v-if="kaarten.length || proefles" class="mt-3 space-y-2">
                <div v-if="proefles" class="flex items-center justify-between gap-3 rounded-xl border border-dashed border-primary/50 bg-primary/5 p-3">
                    <span class="min-w-0">
                        <span class="block text-sm font-medium">{{ proefles.naam }}</span>
                        <span class="block text-xs text-muted-foreground">{{ proefles.wanneer }}</span>
                    </span>
                    <span class="shrink-0 text-sm font-semibold text-primary">{{ proefles.prijs }}</span>
                </div>

                <div v-for="kaart in kaarten" :key="kaart.naam" class="rounded-xl border border-border p-3">
                    <div class="flex items-start justify-between gap-3">
                        <span class="min-w-0">
                            <span class="block text-sm font-medium">{{ kaart.naam }}</span>
                            <span v-if="kaart.extra" class="mt-0.5 flex items-center gap-1 text-xs text-muted-foreground">
                                <CalendarDays class="size-3" />
                                {{ kaart.extra }}
                            </span>
                        </span>
                        <span class="shrink-0 text-sm font-semibold">{{ kaart.prijs }}</span>
                    </div>
                    <div class="mt-2 flex items-center justify-between gap-3">
                        <span class="text-xs text-muted-foreground">{{ kaart.wanneer }}</span>
                        <span class="inline-flex h-8 items-center rounded-lg bg-primary px-3 text-xs font-semibold text-primary-foreground">Aanmelden</span>
                    </div>
                </div>
            </div>
            <p v-else class="mt-3 rounded-xl border border-dashed border-border p-3 text-center text-xs text-muted-foreground">
                Vink hiernaast aan wat je aanbiedt; het verschijnt hier.
            </p>

            <!-- Los inschrijven staat niet op de inschrijfpagina maar in de
                 agenda van een ingelogde ouder. Dat laten we apart zien. -->
            <div v-if="trainingOpen" class="mt-4 border-t border-border pt-3">
                <p class="text-xs font-medium text-muted-foreground">En in de agenda van een ouder, bij een losse training:</p>
                <div class="mt-2 flex items-center gap-3 rounded-xl border border-dashed border-primary/60 p-3">
                    <span class="rounded-lg bg-secondary px-2 py-1 text-center text-xs font-semibold leading-tight">
                        18:00<br /><span class="font-normal text-muted-foreground">wo 25 sep</span>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-medium">Keepers O12</span>
                        <span class="flex items-center gap-1 text-xs text-muted-foreground">
                            <MapPin class="size-3" />
                            Sportpark De Vliert · 3 plekken vrij
                        </span>
                    </span>
                    <span class="shrink-0 rounded-lg border border-primary px-2.5 py-1 text-xs font-semibold text-primary">
                        {{ trainingRequiresApproval ? 'Aanvragen' : 'Inschrijven' }}
                    </span>
                </div>
                <p class="mt-2 text-xs text-muted-foreground">
                    De ouder betaalt {{ betaalwijzeZin }}{{ trainingRequiresApproval ? ', nadat jij de aanvraag hebt goedgekeurd' : ' en het kind staat meteen op de lijst' }}.
                </p>
            </div>
        </div>
    </div>
</template>
