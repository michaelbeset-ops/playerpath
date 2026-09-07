<script setup lang="ts">
import FlashMessage from '@/components/FlashMessage.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ChevronRight, Settings2 } from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * Het overzicht na de wizard: per stap wat er nu staat, met één tik naar het
 * formulier van die stap. Geen tweede formulier; dit leest alleen.
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
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Inschrijven en betalen', href: '/instellingen/inschrijven' }];

const s = props.settings;

const betaalvorm: Record<string, string> = {
    upfront: 'volledig vooraf',
    installments: `in ${s.default_payment.installments} termijnen per ${s.default_payment.interval === 'week' ? 'week' : 'maand'}`,
    monthly: 'maandelijks doorlopend',
};

const afwezigheid: Record<string, string> = { none: 'geen restitutie', refund: 'restitutie', makeup: 'inhaalmoment' };
const veldStand: Record<string, string> = { off: 'niet gevraagd', optional: 'optioneel', required: 'verplicht' };
const veldNaam: Record<string, string> = { kledingmaat: 'Kledingmaat', positie: 'Positie', niveau: 'Niveau', medisch: 'Medisch' };

// Per stap een paar regels in gewone taal. Dit is het antwoord op "hoe staat
// het ook alweer ingesteld?" zonder zes formulieren te openen.
const samenvatting = computed<Record<string, string[]>>(() => {
    const kortingen: string[] = [];
    if (s.discounts.family.enabled) kortingen.push(`gezin ${s.discounts.family.percent}%`);
    if (s.discounts.early.enabled) kortingen.push(`vroegboek ${s.discounts.early.percent}% tot ${s.discounts.early.days_before} dagen vooraf`);
    if (s.discounts.volume.enabled) kortingen.push(`volume ${s.discounts.volume.percent}% vanaf ${s.discounts.volume.from_count}`);
    if (s.discounts.code.enabled) kortingen.push('kortingscodes');

    return {
        aanbod: [
            (s.offering_labels as string[]).join(', '),
            s.trial.enabled && (s.offering_types as string[]).includes('proefles')
                ? `Proefles: ${s.trial.amount_cents === 0 ? 'gratis' : s.trial.formatted}`
                : 'Geen proefles',
        ],
        kosten: [
            s.registration_fee.enabled ? `Inschrijfgeld ${s.registration_fee.formatted}` : 'Geen inschrijfgeld',
            s.kit.enabled ? `Kledingpakket ${s.kit.formatted}` : 'Geen kledingpakket',
        ],
        betalen: [
            `Standaard ${betaalvorm[s.default_payment.type]}`,
            s.approval === 'automatic' ? 'Betaling bevestigt de inschrijving' : 'Jij keurt elke aanmelding goed',
            s.auto_renew_block ? 'Een blok verlengt automatisch' : 'Een blok verlengt niet vanzelf',
            `Opzegtermijn ${s.notice_months} ${s.notice_months === 1 ? 'maand' : 'maanden'}`,
            s.chargeback_fee.enabled ? `Storneringskosten ${s.chargeback_fee.formatted}` : 'Geen storneringskosten',
        ],
        annuleren: [
            `Kosteloos tot ${s.cancellation.free_until_days} dagen voor de start, daarna ${s.cancellation.retain_percent}% ingehouden`,
            `Bij ziekte: ${afwezigheid[s.absence]}`,
        ],
        kortingen: kortingen.length ? [kortingen.join(' · '), s.discounts.stackable ? 'Stapelbaar' : 'Alleen de hoogste telt'] : ['Geen kortingen'],
        formulier: [
            s.capacity.waitlist ? `Wachtlijst aan${s.capacity.pay_on_placement ? ', betalen bij plaatsing' : ''}` : 'Geen wachtlijst',
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
                    <h1 class="text-2xl font-semibold tracking-tight">Inschrijven en betalen</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Zo werkt het bij jouw school. Het inschrijfformulier, de rekeningen en de wachtlijst volgen dit.
                    </p>
                </div>
            </div>

            <div class="mt-6 space-y-3">
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
