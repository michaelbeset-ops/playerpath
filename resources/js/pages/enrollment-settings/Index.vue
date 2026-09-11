<script setup lang="ts">
import FlashMessage from '@/components/FlashMessage.vue';
import EnrollmentFlow from '@/components/onboarding/EnrollmentFlow.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, ChevronRight, ExternalLink, Phone, Settings2 } from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * Het overzicht na de wizard, en de samenvatting in gewone taal.
 *
 * Bovenaan staat "zo werkt het nu bij jouw school": een paar alinea's die
 * zeggen wat de antwoorden betekenen voor een ouder die inschrijft en voor
 * jou die int. Negen formulieren beantwoorden de vraag "hoe staat het ook
 * alweer?" niet; deze tekst wel. Daaronder per stap de instellingen, met één
 * tik naar het formulier van die stap. Geen tweede formulier; dit leest alleen.
 *
 * Onderaan staat een telefoonnummer. Een school die dit leest en denkt "zo
 * werk ik niet" hoort niet te gaan zoeken, maar iemand aan de lijn te krijgen.
 */
interface Stap {
    number: number;
    key: string;
    title: string;
    hint: string;
}

const props = defineProps<{
    settings: Record<string, any>;
    consents: { key: string; title: string; required: boolean; version: number }[];
    development: boolean;
    steps: Stap[];
    supportPhone: string;
    justCompleted: boolean;
    slug: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Inschrijven en betalen', href: '/instellingen/inschrijven' }];

const s = props.settings;

const betaalvormNaam: Record<string, string> = {
    upfront: 'volledig vooraf',
    installments: `in ${s.default_payment.installments} termijnen per ${s.default_payment.interval === 'week' ? 'week' : 'maand'}`,
    monthly: 'maandelijks doorlopend',
};

const momentNaam: Record<string, string> = {
    anytime: 'het hele jaar door',
    before_block: 'vóór de start van een blok of kamp',
    single_training: 'voor één losse training',
    camp: 'voor kampen en clinics',
};

const afwezigheid: Record<string, string> = { none: 'geen restitutie', refund: 'restitutie', makeup: 'een inhaalmoment' };
const veldStand: Record<string, string> = { off: 'niet gevraagd', optional: 'optioneel', required: 'verplicht' };
const veldNaam: Record<string, string> = { kledingmaat: 'Kledingmaat', positie: 'Positie', niveau: 'Niveau', medisch: 'Medisch' };

/** "a, b en c" — een opsomming zoals je hem zou uitspreken. */
const opsomming = (delen: string[]) =>
    delen.length <= 1 ? (delen[0] ?? '') : delen.slice(0, -1).join(', ') + ' en ' + delen[delen.length - 1];

const betaalvormen = computed(() => (s.default_payment.types as string[]).map((t) => betaalvormNaam[t]).filter(Boolean));
const momenten = computed(() => (s.enrollment.moments as string[]).map((m) => momentNaam[m]).filter(Boolean));
const telefoonLink = computed(() => 'tel:' + props.supportPhone.replace(/\s+/g, ''));

/**
 * Zo werkt het nu bij jouw school, in alinea's. Elke alinea volgt uit de
 * antwoorden; wat uit staat, staat er niet.
 */
const verhaal = computed<string[]>(() => {
    const regels: string[] = [];
    const aanbod = s.offering_labels as string[];

    regels.push(
        `Ouders vinden je aanbod op je eigen inschrijfpagina: ${opsomming(aanbod.map((a) => a.toLowerCase()))}. ` +
            `Inschrijven kan ${opsomming(momenten.value)}.` +
            (s.trial.enabled && (s.offering_types as string[]).includes('proefles')
                ? ` Wie eerst wil kijken doet een proefles${s.trial.amount_cents === 0 ? ', gratis' : ' voor ' + s.trial.formatted}.`
                : ''),
    );

    if (s.training_enrollment.open) {
        const wijzen: string[] = [];
        if ((s.training_enrollment.payment_methods as string[]).includes('online')) wijzen.push('online');
        if ((s.training_enrollment.payment_methods as string[]).includes('cash')) wijzen.push('contant bij de training');
        regels.push(
            `Een nieuwe training staat standaard open voor losse aanmelding. Een ouder betaalt dan ${wijzen.join(' of ')}` +
                (s.training_enrollment.requires_approval ? ', nadat jij de aanmelding hebt goedgekeurd.' : ', en is meteen ingeschreven.') +
                ' Per training kun je dit aanpassen.',
        );
    } else {
        regels.push('Losse trainingen staan standaard niet open: wie in de groep zit traint mee. Per training kun je dat alsnog openzetten.');
    }

    regels.push(
        (s.approval === 'automatic'
            ? 'Meldt een ouder zich aan, dan is de inschrijving rond zodra er betaald is; jij hoeft niets te doen. '
            : 'Meldt een ouder zich aan, dan zie jij de aanmelding eerst en keur je hem goed. Daarna krijgt de ouder een betaalverzoek. ') +
            `Betalen kan ${opsomming(betaalvormen.value)}` +
            (betaalvormen.value.length > 1 ? '; de ouder kiest zelf.' : '.'),
    );

    const extra: string[] = [];
    if (s.registration_fee.enabled) extra.push(`${s.registration_fee.formatted} inschrijfgeld`);
    if (s.kit.enabled) extra.push(`${s.kit.formatted} voor het kledingpakket`);
    if (extra.length) regels.push(`Bij de eerste inschrijving komt er eenmalig ${opsomming(extra)} bij. Dat staat op het formulier en op de rekening.`);

    regels.push(
        `Annuleren is kosteloos tot ${s.cancellation.free_until_days} dagen voor de start; daarna houd je ${s.cancellation.retain_percent}% in. ` +
            `Bij ziekte geldt ${afwezigheid[s.absence]}. ` +
            (s.auto_renew_block ? 'Een blok loopt na afloop door. ' : 'Een blok stopt na afloop; de ouder krijgt vooraf een uitnodiging om opnieuw in te schrijven. ') +
            `De opzegtermijn voor een abonnement is ${s.notice_months} ${s.notice_months === 1 ? 'maand' : 'maanden'}.`,
    );

    const kortingen: string[] = [];
    if (s.discounts.family.enabled) kortingen.push(`${s.discounts.family.percent}% gezinskorting vanaf het tweede kind`);
    if (s.discounts.early.enabled) kortingen.push(`${s.discounts.early.percent}% vroegboekkorting tot ${s.discounts.early.days_before} dagen vooraf`);
    if (s.discounts.volume.enabled) kortingen.push(`${s.discounts.volume.percent}% korting vanaf ${s.discounts.volume.from_count} stuks`);
    if (s.discounts.code.enabled) kortingen.push('kortingscodes die jij aanmaakt');
    if (kortingen.length) {
        regels.push(
            `Kortingen worden automatisch berekend: ${opsomming(kortingen)}. ` +
                (s.discounts.stackable ? 'Ze mogen bij elkaar opgeteld worden.' : 'Alleen de hoogste korting telt.'),
        );
    }

    regels.push(
        s.capacity.waitlist
            ? `Is een blok vol, dan komt een ouder op de wachtlijst${s.capacity.pay_on_placement ? ' en betaalt hij pas als er plek is' : ''}. ` +
                  `Komt er plek, dan heeft hij ${s.capacity.invitation_days} dagen om die vast te leggen; daarna gaat hij naar de volgende.`
            : 'Is een blok vol, dan verdwijnt het van de inschrijfpagina. Er is geen wachtlijst.',
    );

    if (s.chargeback_fee.enabled) {
        regels.push(`Draait een ouder een incasso terug, dan reken je ${s.chargeback_fee.formatted} storneringskosten.`);
    }
    regels.push(
        `Mislukt een betaling, dan krijgt de ouder vanzelf een herinnering met een nieuwe betaallink, na ${opsomming((s.dunning.days as number[]).map(String))} dagen.`,
    );

    return regels;
});

// Per stap een paar regels: het antwoord op "hoe staat het ook alweer
// ingesteld?" zonder negen formulieren te openen.
const samenvatting = computed<Record<string, string[]>>(() => {
    const kortingen: string[] = [];
    if (s.discounts.family.enabled) kortingen.push(`gezin ${s.discounts.family.percent}%`);
    if (s.discounts.early.enabled) kortingen.push(`vroegboek ${s.discounts.early.percent}% tot ${s.discounts.early.days_before} dagen vooraf`);
    if (s.discounts.volume.enabled) kortingen.push(`volume ${s.discounts.volume.percent}% vanaf ${s.discounts.volume.from_count}`);
    if (s.discounts.code.enabled) kortingen.push('kortingscodes');

    return {
        aanbod: [
            (s.offering_labels as string[]).join(', '),
            'Inschrijven ' + opsomming(momenten.value),
            s.training_enrollment.open ? 'Losse trainingen standaard open' : 'Losse trainingen standaard dicht',
            s.trial.enabled && (s.offering_types as string[]).includes('proefles')
                ? `Proefles: ${s.trial.amount_cents === 0 ? 'gratis' : s.trial.formatted}`
                : 'Geen proefles',
        ],
        kosten: [
            s.registration_fee.enabled ? `Inschrijfgeld ${s.registration_fee.formatted}` : 'Geen inschrijfgeld',
            s.kit.enabled ? `Kledingpakket ${s.kit.formatted}` : 'Geen kledingpakket',
        ],
        betalen: [
            'Betalen ' + opsomming(betaalvormen.value),
            s.approval === 'automatic' ? 'Betaling bevestigt de inschrijving' : 'Jij keurt elke aanmelding goed',
            s.auto_renew_block ? 'Een blok verlengt automatisch' : 'Een blok verlengt niet vanzelf',
            `Opzegtermijn ${s.notice_months} ${s.notice_months === 1 ? 'maand' : 'maanden'}`,
            s.chargeback_fee.enabled ? `Storneringskosten ${s.chargeback_fee.formatted}` : 'Geen storneringskosten',
            `Herinneringen na ${s.dunning.text} dagen`,
        ],
        annuleren: [
            `Kosteloos tot ${s.cancellation.free_until_days} dagen voor de start, daarna ${s.cancellation.retain_percent}% ingehouden`,
            `Bij ziekte: ${afwezigheid[s.absence]}`,
        ],
        kortingen: kortingen.length ? [kortingen.join(' · '), s.discounts.stackable ? 'Stapelbaar' : 'Alleen de hoogste telt'] : ['Geen kortingen'],
        formulier: [
            s.capacity.waitlist
                ? `Wachtlijst aan${s.capacity.pay_on_placement ? ', betalen bij plaatsing' : ''}, uitnodiging ${s.capacity.invitation_days} dagen geldig`
                : 'Geen wachtlijst',
            Object.entries(s.fields as Record<string, string>)
                .filter(([, stand]) => stand !== 'off')
                .map(([veld, stand]) => `${veldNaam[veld] ?? veld} ${veldStand[stand]}`)
                .join(', ') || 'Geen extra velden',
            'Verplicht: ' +
                (props.consents
                    .filter((c) => c.required)
                    .map((c) => c.title)
                    .join(', ') || 'geen'),
            props.development ? 'Ontwikkelingslaag aan' : 'Ontwikkelingslaag uit',
        ],
    };
});
</script>

<template>
    <Head title="Inschrijven en betalen" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl p-4">
            <FlashMessage />

            <div class="flex items-start gap-3">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                    <Settings2 class="size-5" />
                </span>
                <div class="min-w-0">
                    <h1 class="text-2xl font-semibold tracking-tight">{{ justCompleted ? 'Je school is ingericht' : 'Inschrijven en betalen' }}</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{
                            justCompleted
                                ? 'Lees hieronder hoe het nu werkt. Klopt er iets niet, dan pas je die stap aan.'
                                : 'Zo werkt het bij jouw school. Het inschrijfformulier, de rekeningen en de wachtlijst volgen dit.'
                        }}
                    </p>
                </div>
            </div>

            <!-- Zo werkt het nu, in gewone taal. -->
            <section class="mt-6 rounded-2xl border border-border bg-card p-5 shadow-sm" aria-labelledby="verhaal">
                <h2 id="verhaal" class="font-semibold">Zo werkt het nu bij jouw school</h2>

                <div class="mt-4">
                    <EnrollmentFlow
                        :approval="s.approval"
                        :payment-types="s.default_payment.types"
                        :trial-enabled="s.trial.enabled && (s.offering_types as string[]).includes('proefles')"
                    />
                </div>

                <div class="mt-5 space-y-3 text-sm leading-relaxed text-foreground/85">
                    <p v-for="(regel, i) in verhaal" :key="i">{{ regel }}</p>
                </div>

                <a
                    :href="'/inschrijven/' + slug"
                    target="_blank"
                    rel="noopener"
                    class="mt-4 inline-flex min-h-11 items-center gap-2 rounded-xl border border-border bg-background px-4 text-sm font-medium transition hover:border-primary"
                >
                    Bekijk je inschrijfpagina zoals een ouder hem ziet
                    <ExternalLink class="size-4" />
                </a>

                <div class="mt-5 flex flex-col gap-3 rounded-xl bg-primary/10 p-4 sm:flex-row sm:items-center">
                    <Phone class="size-5 shrink-0 text-primary" />
                    <p class="min-w-0 flex-1 text-sm leading-relaxed">
                        <span class="font-medium">Vragen, of sluit dit niet aan bij hoe jij werkt?</span>
                        Bel dan even; we stellen het samen goed in.
                    </p>
                    <a
                        :href="telefoonLink"
                        class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                    >
                        {{ supportPhone }}
                    </a>
                </div>

                <Link
                    v-if="justCompleted"
                    href="/dashboard"
                    class="mt-4 inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl border border-border bg-background px-4 text-sm font-medium transition hover:border-primary sm:w-auto"
                >
                    Naar mijn dashboard
                    <ArrowRight class="size-4" />
                </Link>
            </section>

            <h2 class="mt-8 text-sm font-medium text-muted-foreground">Per onderdeel aanpassen</h2>

            <div class="mt-3 space-y-3">
                <Link
                    v-for="stap in steps"
                    :key="stap.key"
                    :href="'/instellingen/inschrijven/stap/' + stap.number"
                    class="flex items-start gap-3 rounded-xl border border-border bg-card p-4 shadow-sm transition hover:border-primary"
                >
                    <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-primary/15 text-xs font-semibold text-primary">
                        {{ stap.number }}
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block font-medium">{{ stap.title }}</span>
                        <span v-for="(regel, i) in samenvatting[stap.key]" :key="i" class="block text-sm text-muted-foreground">{{ regel }}</span>
                    </span>
                    <ChevronRight class="mt-1 size-4 shrink-0 text-muted-foreground" />
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
