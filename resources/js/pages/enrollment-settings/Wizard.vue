<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import ToggleSwitch from '@/components/ToggleSwitch.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, Check } from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * De wizard: hoe deze school inschrijft en int, in zes stappen.
 *
 * Elke stap is één scherm met één onderwerp en één opslaan-knop. De eerste
 * keer loop je door; daarna opent het overzicht elke stap los. Het is
 * hetzelfde formulier in beide gevallen, dus er is niets dat uit elkaar kan
 * lopen.
 *
 * Mobiel-first: alles staat onder elkaar, keuzes zijn tikbare kaarten van
 * minstens 44 pixels hoog, en de opslaan-knop zit in een vaste balk onderaan.
 */
interface Stap {
    number: number;
    key: string;
    title: string;
    hint: string;
}

interface Toestemming {
    key: string;
    title: string;
    body: string;
    version: number;
    required: boolean;
    saved: boolean;
}

const props = defineProps<{
    step: number;
    steps: Stap[];
    completed: boolean;
    settings: Record<string, any>;
    consents: Toestemming[];
    development: boolean;
    offeringTypes: { value: string; label: string; description: string }[];
}>();

const huidige = computed(() => props.steps[props.step - 1]);
const laatste = computed(() => props.step === props.steps.length);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inschrijven en betalen', href: '/instellingen/inschrijven' },
    { title: huidige.value.title, href: '/instellingen/inschrijven/stap/' + props.step },
];

const s = props.settings;

// Eén formulier per stap. De velden die er niet bij horen bestaan gewoon niet,
// dus een stap kan nooit per ongeluk een andere instelling overschrijven.
const form = useForm(
    props.step === 1
        ? {
              offering_types: [...(s.offering_types as string[])],
              trial_enabled: s.trial.enabled as boolean,
              trial_amount: s.trial.amount as string,
          }
        : props.step === 2
          ? {
                registration_fee_enabled: s.registration_fee.enabled as boolean,
                registration_fee_amount: s.registration_fee.amount as string,
                kit_enabled: s.kit.enabled as boolean,
                kit_amount: s.kit.amount as string,
            }
          : props.step === 3
            ? {
                  default_payment_type: s.default_payment.type as string,
                  installments: s.default_payment.installments as number,
                  installment_interval: s.default_payment.interval as string,
                  auto_renew_block: s.auto_renew_block as boolean,
                  notice_months: s.notice_months as number,
                  approval: s.approval as string,
                  chargeback_fee_enabled: s.chargeback_fee.enabled as boolean,
                  chargeback_fee_amount: s.chargeback_fee.amount as string,
              }
            : props.step === 4
              ? {
                    free_until_days: s.cancellation.free_until_days as number,
                    retain_percent: s.cancellation.retain_percent as number,
                    absence: s.absence as string,
                }
              : props.step === 5
                ? {
                      family_enabled: s.discounts.family.enabled as boolean,
                      family_percent: s.discounts.family.percent as number,
                      early_enabled: s.discounts.early.enabled as boolean,
                      early_percent: s.discounts.early.percent as number,
                      early_days_before: s.discounts.early.days_before as number,
                      volume_enabled: s.discounts.volume.enabled as boolean,
                      volume_percent: s.discounts.volume.percent as number,
                      volume_from_count: s.discounts.volume.from_count as number,
                      code_enabled: s.discounts.code.enabled as boolean,
                      stackable: s.discounts.stackable as boolean,
                  }
                : {
                      waitlist: s.capacity.waitlist as boolean,
                      pay_on_placement: s.capacity.pay_on_placement as boolean,
                      fields: { ...(s.fields as Record<string, string>) },
                      consents: Object.fromEntries(
                          props.consents.map((c) => [c.key, { required: c.required, title: c.title, body: c.body }]),
                      ) as Record<string, { required: boolean; title: string; body: string }>,
                      development: props.development,
                  },
);

const f = form as any;

const opslaan = () => form.patch('/instellingen/inschrijven/stap/' + props.step, { preserveScroll: true });

const wisselSoort = (waarde: string) => {
    const lijst = f.offering_types as string[];
    const i = lijst.indexOf(waarde);
    if (i === -1) {
        lijst.push(waarde);
    } else {
        lijst.splice(i, 1);
    }
};

const betaalvormen = [
    { value: 'upfront', label: 'Volledig vooraf', hint: 'Eén bedrag bij het inschrijven.' },
    { value: 'installments', label: 'In termijnen', hint: 'Het bedrag verdeeld over een vast aantal keren.' },
    { value: 'monthly', label: 'Maandelijks doorlopend', hint: 'Elke maand een bedrag tot iemand opzegt.' },
];

const goedkeuring = [
    { value: 'manual', label: 'Handmatig goedkeuren', hint: 'Jij bekijkt elke aanmelding en keurt hem goed. Daarna kan er betaald worden.' },
    { value: 'automatic', label: 'Automatisch', hint: 'De betaling bevestigt de inschrijving meteen. Geen tussenstap.' },
];

const afwezigheid = [
    { value: 'none', label: 'Geen restitutie', hint: 'Een gemiste training is gemist. De gangbare regel.' },
    { value: 'makeup', label: 'Inhaalmoment', hint: 'Je biedt een andere training aan om in te halen.' },
    { value: 'refund', label: 'Restitutie', hint: 'Je betaalt het deel van de gemiste training terug.' },
];

const veldStanden = [
    { value: 'off', label: 'Niet vragen' },
    { value: 'optional', label: 'Optioneel' },
    { value: 'required', label: 'Verplicht' },
];

const veldLabels: Record<string, { label: string; hint: string }> = {
    kledingmaat: { label: 'Kledingmaat', hint: 'Handig bij een kledingpakket of een kampshirt.' },
    positie: { label: 'Positie', hint: 'Keeper of veldspeler. Bepaalt de categorieën op de spelerskaart.' },
    niveau: { label: 'Niveau', hint: 'Waar speelt het kind nu, en op welk niveau.' },
    medisch: { label: 'Medische bijzonderheden', hint: 'Allergieën, blessures, dingen die een trainer moet weten.' },
};

const consentUitleg: Record<string, string> = {
    avg: 'Wat je met de gegevens doet. Vrijwel altijd verplicht.',
    beeldrecht: 'Of je foto’s en video’s van de training mag gebruiken.',
    gedragsregels: 'De afspraken op en naast het veld.',
    medisch: 'Dat de ouder bijzonderheden doorgeeft die de trainer moet weten.',
};

const invoerKlasse = 'h-11 w-full rounded-lg border border-input bg-background px-3 text-sm outline-none focus:border-primary';
const getalKlasse = 'h-11 w-24 rounded-lg border border-input bg-background px-3 text-sm tabular outline-none focus:border-primary';
</script>

<template>
    <Head :title="huidige.title + ' - Inschrijven en betalen'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl p-4 pb-28">
            <!-- Waar je bent. Zes bolletjes, de huidige groen, gedane met een vinkje. -->
            <div class="flex items-center justify-between gap-2">
                <p class="text-sm text-muted-foreground">Stap {{ step }} van {{ steps.length }}</p>
                <ol class="flex items-center gap-1.5">
                    <li v-for="stap in steps" :key="stap.key">
                        <Link
                            :href="'/instellingen/inschrijven/stap/' + stap.number"
                            class="flex size-7 items-center justify-center rounded-full text-xs font-semibold transition"
                            :class="
                                stap.number === step
                                    ? 'bg-primary text-primary-foreground'
                                    : stap.number < step || completed
                                      ? 'bg-primary/15 text-primary'
                                      : 'bg-secondary text-muted-foreground'
                            "
                            :aria-label="'Stap ' + stap.number + ': ' + stap.title"
                            :aria-current="stap.number === step ? 'step' : undefined"
                        >
                            <Check v-if="stap.number < step || (completed && stap.number !== step)" class="size-3.5" />
                            <template v-else>{{ stap.number }}</template>
                        </Link>
                    </li>
                </ol>
            </div>

            <h1 class="mt-3 text-2xl font-semibold tracking-tight">{{ huidige.title }}</h1>
            <p class="mt-1 text-sm text-muted-foreground">{{ huidige.hint }}</p>

            <form id="wizard" class="mt-6 space-y-4" @submit.prevent="opslaan">
                <!-- ================= 1. Aanbod ================= -->
                <template v-if="step === 1">
                    <section class="rounded-xl border border-border bg-card p-5 shadow-sm">
                        <p class="font-medium">Wat bied je aan?</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Alleen wat je aanzet staat straks in het aanbodformulier. Je kunt dit altijd uitbreiden.
                        </p>

                        <div class="mt-4 grid gap-2">
                            <button
                                v-for="soort in offeringTypes"
                                :key="soort.value"
                                type="button"
                                class="flex min-h-11 items-start gap-3 rounded-xl border p-3 text-left transition"
                                :class="
                                    f.offering_types.includes(soort.value) ? 'border-primary bg-primary/5' : 'border-border hover:border-primary/50'
                                "
                                :aria-pressed="f.offering_types.includes(soort.value)"
                                @click="wisselSoort(soort.value)"
                            >
                                <span
                                    class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-md border"
                                    :class="
                                        f.offering_types.includes(soort.value) ? 'border-primary bg-primary text-primary-foreground' : 'border-border'
                                    "
                                >
                                    <Check v-if="f.offering_types.includes(soort.value)" class="size-3.5" />
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-medium">{{ soort.label }}</span>
                                    <span class="block text-xs text-muted-foreground">{{ soort.description }}</span>
                                </span>
                            </button>
                        </div>
                        <InputError class="mt-2" :message="form.errors.offering_types" />
                    </section>

                    <section v-if="f.offering_types.includes('proefles')" class="rounded-xl border border-border bg-card p-5 shadow-sm">
                        <ToggleSwitch v-model="f.trial_enabled" label="Proefles aanbieden" description="Een ouder kan eerst één keer meedoen." />

                        <div v-if="f.trial_enabled" class="mt-3 border-t border-border pt-4">
                            <label for="trial_amount" class="block text-sm font-medium">Wat kost de proefles?</label>
                            <p class="text-xs text-muted-foreground">Leeg of 0 betekent gratis.</p>
                            <div class="mt-2 flex items-center gap-2">
                                <span class="text-sm text-muted-foreground">€</span>
                                <input id="trial_amount" v-model="f.trial_amount" inputmode="decimal" placeholder="0,00" :class="getalKlasse" />
                            </div>
                            <InputError class="mt-2" :message="form.errors.trial_amount" />
                        </div>
                    </section>
                </template>

                <!-- ================= 2. Kosten erbij ================= -->
                <template v-else-if="step === 2">
                    <section class="rounded-xl border border-border bg-card p-5 shadow-sm">
                        <ToggleSwitch
                            v-model="f.registration_fee_enabled"
                            label="Eenmalig inschrijfgeld"
                            description="Eén keer bij de eerste inschrijving, niet bij elk blok."
                        />
                        <div v-if="f.registration_fee_enabled" class="mt-3 border-t border-border pt-4">
                            <label for="registration_fee_amount" class="block text-sm font-medium">Bedrag</label>
                            <div class="mt-2 flex items-center gap-2">
                                <span class="text-sm text-muted-foreground">€</span>
                                <input
                                    id="registration_fee_amount"
                                    v-model="f.registration_fee_amount"
                                    inputmode="decimal"
                                    placeholder="25,00"
                                    :class="getalKlasse"
                                />
                            </div>
                            <InputError class="mt-2" :message="form.errors.registration_fee_amount" />
                        </div>
                    </section>

                    <section class="rounded-xl border border-border bg-card p-5 shadow-sm">
                        <ToggleSwitch
                            v-model="f.kit_enabled"
                            label="Verplicht kledingpakket"
                            description="Shirt, short en kousen van de school. Wordt bij de eerste inschrijving in rekening gebracht."
                        />
                        <div v-if="f.kit_enabled" class="mt-3 border-t border-border pt-4">
                            <label for="kit_amount" class="block text-sm font-medium">Bedrag</label>
                            <div class="mt-2 flex items-center gap-2">
                                <span class="text-sm text-muted-foreground">€</span>
                                <input id="kit_amount" v-model="f.kit_amount" inputmode="decimal" placeholder="45,00" :class="getalKlasse" />
                            </div>
                            <InputError class="mt-2" :message="form.errors.kit_amount" />
                        </div>
                    </section>
                </template>

                <!-- ================= 3. Betalen ================= -->
                <template v-else-if="step === 3">
                    <section class="rounded-xl border border-border bg-card p-5 shadow-sm">
                        <p class="font-medium">Standaard betaalvorm</p>
                        <p class="mt-1 text-sm text-muted-foreground">Wordt vooringevuld bij nieuw aanbod. Per aanbod kun je ervan afwijken.</p>

                        <div class="mt-4 grid gap-2">
                            <label
                                v-for="vorm in betaalvormen"
                                :key="vorm.value"
                                class="flex min-h-11 cursor-pointer items-start gap-3 rounded-xl border p-3 transition"
                                :class="
                                    f.default_payment_type === vorm.value ? 'border-primary bg-primary/5' : 'border-border hover:border-primary/50'
                                "
                            >
                                <input v-model="f.default_payment_type" type="radio" :value="vorm.value" class="mt-1 accent-primary" />
                                <span class="min-w-0">
                                    <span class="block text-sm font-medium">{{ vorm.label }}</span>
                                    <span class="block text-xs text-muted-foreground">{{ vorm.hint }}</span>
                                </span>
                            </label>
                        </div>

                        <div v-if="f.default_payment_type === 'installments'" class="mt-4 flex flex-wrap items-end gap-4 border-t border-border pt-4">
                            <div>
                                <label for="installments" class="block text-sm font-medium">Aantal termijnen</label>
                                <input
                                    id="installments"
                                    v-model.number="f.installments"
                                    type="number"
                                    min="2"
                                    max="12"
                                    :class="'mt-2 ' + getalKlasse"
                                />
                                <InputError class="mt-2" :message="form.errors.installments" />
                            </div>
                            <div>
                                <label for="installment_interval" class="block text-sm font-medium">Om de</label>
                                <select
                                    id="installment_interval"
                                    v-model="f.installment_interval"
                                    :class="'mt-2 h-11 rounded-lg border border-input bg-background px-3 text-sm'"
                                >
                                    <option value="month">maand</option>
                                    <option value="week">week</option>
                                </select>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-xl border border-border bg-card p-5 shadow-sm">
                        <p class="font-medium">Goedkeuren</p>
                        <p class="mt-1 text-sm text-muted-foreground">Wat er gebeurt als een ouder zich aanmeldt.</p>

                        <div class="mt-4 grid gap-2">
                            <label
                                v-for="keuze in goedkeuring"
                                :key="keuze.value"
                                class="flex min-h-11 cursor-pointer items-start gap-3 rounded-xl border p-3 transition"
                                :class="f.approval === keuze.value ? 'border-primary bg-primary/5' : 'border-border hover:border-primary/50'"
                            >
                                <input v-model="f.approval" type="radio" :value="keuze.value" class="mt-1 accent-primary" />
                                <span class="min-w-0">
                                    <span class="block text-sm font-medium">{{ keuze.label }}</span>
                                    <span class="block text-xs text-muted-foreground">{{ keuze.hint }}</span>
                                </span>
                            </label>
                        </div>
                    </section>

                    <section class="divide-y divide-border rounded-xl border border-border bg-card px-5 shadow-sm">
                        <ToggleSwitch
                            v-model="f.auto_renew_block"
                            label="Een blok verlengt automatisch"
                            description="Uit: de ouder krijgt vóór het einde een uitnodiging en meldt opnieuw aan."
                        />

                        <div class="flex items-center justify-between gap-4 py-3">
                            <label for="notice_months" class="min-w-0">
                                <span class="block text-sm font-medium">Opzegtermijn abonnement</span>
                                <span class="block text-xs text-muted-foreground">In maanden. 0 is per direct.</span>
                            </label>
                            <div class="flex shrink-0 items-center gap-2">
                                <input
                                    id="notice_months"
                                    v-model.number="f.notice_months"
                                    type="number"
                                    min="0"
                                    max="12"
                                    :class="'w-20 ' + getalKlasse"
                                />
                                <span class="text-sm text-muted-foreground">mnd</span>
                            </div>
                        </div>
                        <InputError :message="form.errors.notice_months" />

                        <div class="py-1">
                            <ToggleSwitch
                                v-model="f.chargeback_fee_enabled"
                                label="Storneringskosten"
                                description="Als een ouder een incasso terugdraait, reken je dit bedrag extra."
                            />
                            <div v-if="f.chargeback_fee_enabled" class="mb-3 flex items-center gap-2">
                                <span class="text-sm text-muted-foreground">€</span>
                                <input v-model="f.chargeback_fee_amount" inputmode="decimal" placeholder="7,50" :class="getalKlasse" />
                            </div>
                            <InputError class="mb-3" :message="form.errors.chargeback_fee_amount" />
                        </div>
                    </section>
                </template>

                <!-- ================= 4. Annuleren ================= -->
                <template v-else-if="step === 4">
                    <section class="rounded-xl border border-border bg-card p-5 shadow-sm">
                        <p class="font-medium">Annuleren vóór de start</p>
                        <p class="mt-1 text-sm text-muted-foreground">Dit komt letterlijk op het inschrijfformulier te staan.</p>

                        <div class="mt-4 space-y-4">
                            <div class="flex flex-wrap items-center gap-2 text-sm">
                                <span>Kosteloos annuleren tot</span>
                                <input v-model.number="f.free_until_days" type="number" min="0" max="365" :class="'w-20 ' + getalKlasse" />
                                <span>dagen voor de start.</span>
                            </div>
                            <InputError :message="form.errors.free_until_days" />

                            <div class="flex flex-wrap items-center gap-2 text-sm">
                                <span>Daarna houd je</span>
                                <input v-model.number="f.retain_percent" type="number" min="0" max="100" :class="'w-20 ' + getalKlasse" />
                                <span>% van het bedrag in.</span>
                            </div>
                            <InputError :message="form.errors.retain_percent" />
                        </div>
                    </section>

                    <section class="rounded-xl border border-border bg-card p-5 shadow-sm">
                        <p class="font-medium">Ziekte of afwezigheid</p>
                        <p class="mt-1 text-sm text-muted-foreground">Wat er gebeurt als een kind een training mist.</p>

                        <div class="mt-4 grid gap-2">
                            <label
                                v-for="keuze in afwezigheid"
                                :key="keuze.value"
                                class="flex min-h-11 cursor-pointer items-start gap-3 rounded-xl border p-3 transition"
                                :class="f.absence === keuze.value ? 'border-primary bg-primary/5' : 'border-border hover:border-primary/50'"
                            >
                                <input v-model="f.absence" type="radio" :value="keuze.value" class="mt-1 accent-primary" />
                                <span class="min-w-0">
                                    <span class="block text-sm font-medium">{{ keuze.label }}</span>
                                    <span class="block text-xs text-muted-foreground">{{ keuze.hint }}</span>
                                </span>
                            </label>
                        </div>
                    </section>
                </template>

                <!-- ================= 5. Kortingen ================= -->
                <template v-else-if="step === 5">
                    <section class="rounded-xl border border-border bg-card px-5 py-1 shadow-sm">
                        <ToggleSwitch v-model="f.family_enabled" label="Gezinskorting" description="Voor een tweede kind uit hetzelfde gezin." />
                        <div v-if="f.family_enabled" class="mb-3 flex items-center gap-2 text-sm">
                            <input v-model.number="f.family_percent" type="number" min="0" max="100" :class="'w-20 ' + getalKlasse" />
                            <span>% korting op het tweede kind en verder</span>
                        </div>
                        <InputError class="mb-3" :message="form.errors.family_percent" />
                    </section>

                    <section class="rounded-xl border border-border bg-card px-5 py-1 shadow-sm">
                        <ToggleSwitch
                            v-model="f.early_enabled"
                            label="Vroegboekkorting"
                            description="Wie ruim voor de start inschrijft betaalt minder."
                        />
                        <div v-if="f.early_enabled" class="mb-3 flex flex-wrap items-center gap-2 text-sm">
                            <input v-model.number="f.early_percent" type="number" min="0" max="100" :class="'w-20 ' + getalKlasse" />
                            <span>% korting tot</span>
                            <input v-model.number="f.early_days_before" type="number" min="1" max="365" :class="'w-20 ' + getalKlasse" />
                            <span>dagen voor de start</span>
                        </div>
                        <InputError class="mb-3" :message="form.errors.early_percent ?? form.errors.early_days_before" />
                    </section>

                    <section class="rounded-xl border border-border bg-card px-5 py-1 shadow-sm">
                        <ToggleSwitch v-model="f.volume_enabled" label="Volumekorting" description="Meerdere blokken of kampen tegelijk afnemen." />
                        <div v-if="f.volume_enabled" class="mb-3 flex flex-wrap items-center gap-2 text-sm">
                            <input v-model.number="f.volume_percent" type="number" min="0" max="100" :class="'w-20 ' + getalKlasse" />
                            <span>% korting vanaf</span>
                            <input v-model.number="f.volume_from_count" type="number" min="2" max="20" :class="'w-20 ' + getalKlasse" />
                            <span>stuks in één bestelling</span>
                        </div>
                        <InputError class="mb-3" :message="form.errors.volume_percent ?? form.errors.volume_from_count" />
                    </section>

                    <section class="divide-y divide-border rounded-xl border border-border bg-card px-5 shadow-sm">
                        <ToggleSwitch
                            v-model="f.code_enabled"
                            label="Kortingscodes"
                            description="Je maakt zelf codes aan die een ouder bij het inschrijven invult."
                        />
                        <ToggleSwitch
                            v-model="f.stackable"
                            label="Kortingen stapelen"
                            description="Uit: alleen de hoogste korting telt. Aan: ze worden bij elkaar opgeteld."
                        />
                    </section>
                </template>

                <!-- ================= 6. Formulier ================= -->
                <template v-else>
                    <section class="divide-y divide-border rounded-xl border border-border bg-card px-5 shadow-sm">
                        <ToggleSwitch
                            v-model="f.waitlist"
                            label="Wachtlijst bij vol aanbod"
                            description="Vol aanbod blijft zichtbaar; een ouder kan zich op de wachtlijst zetten."
                        />
                        <ToggleSwitch
                            v-if="f.waitlist"
                            v-model="f.pay_on_placement"
                            label="Betalen pas bij plaatsing"
                            description="Op de wachtlijst staat niets open. Aan te raden."
                        />
                    </section>

                    <section class="rounded-xl border border-border bg-card p-5 shadow-sm">
                        <p class="font-medium">Aanmeldvelden</p>
                        <p class="mt-1 text-sm text-muted-foreground">Naam, geboortedatum en contactgegevens vraag je altijd. Dit zijn de extra's.</p>

                        <div class="mt-4 space-y-4">
                            <div v-for="(stand, veld) in f.fields" :key="veld">
                                <p class="text-sm font-medium">{{ veldLabels[veld]?.label ?? veld }}</p>
                                <p class="text-xs text-muted-foreground">{{ veldLabels[veld]?.hint }}</p>
                                <div class="mt-2 grid grid-cols-3 gap-1 rounded-lg bg-secondary p-1">
                                    <button
                                        v-for="optie in veldStanden"
                                        :key="optie.value"
                                        type="button"
                                        class="h-9 rounded-md text-xs font-medium transition"
                                        :class="stand === optie.value ? 'bg-card shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                                        :aria-pressed="stand === optie.value"
                                        @click="f.fields[veld] = optie.value"
                                    >
                                        {{ optie.label }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-xl border border-border bg-card p-5 shadow-sm">
                        <p class="font-medium">Toestemmingen</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Wat een ouder aanvinkt bij het inschrijven. Pas je een tekst aan, dan is dat een nieuwe versie en zie je wie de oude
                            tekende.
                        </p>

                        <div class="mt-4 space-y-3">
                            <div v-for="doc in consents" :key="doc.key" class="rounded-xl border border-border p-4">
                                <ToggleSwitch
                                    v-model="f.consents[doc.key].required"
                                    :label="'Verplicht: ' + doc.title"
                                    :description="consentUitleg[doc.key]"
                                />

                                <div class="mt-3 space-y-3 border-t border-border pt-3">
                                    <div>
                                        <label :for="'consent_title_' + doc.key" class="block text-xs font-medium text-muted-foreground">Titel</label>
                                        <input
                                            :id="'consent_title_' + doc.key"
                                            v-model="f.consents[doc.key].title"
                                            maxlength="120"
                                            :class="'mt-1 ' + invoerKlasse"
                                        />
                                    </div>
                                    <div>
                                        <label :for="'consent_body_' + doc.key" class="block text-xs font-medium text-muted-foreground">
                                            Tekst
                                            <span v-if="doc.saved" class="tabular"> &middot; versie {{ doc.version }}</span>
                                        </label>
                                        <textarea
                                            :id="'consent_body_' + doc.key"
                                            v-model="f.consents[doc.key].body"
                                            rows="3"
                                            maxlength="5000"
                                            class="mt-1 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm outline-none focus:border-primary"
                                        ></textarea>
                                    </div>
                                    <InputError
                                        :message="
                                            form.errors[('consents.' + doc.key + '.body') as any] ??
                                            form.errors[('consents.' + doc.key + '.title') as any]
                                        "
                                    />
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-xl border border-border bg-card px-5 py-1 shadow-sm">
                        <ToggleSwitch
                            v-model="f.development"
                            label="Ontwikkelingslaag"
                            description="Rapporten, spelerskaart en voortgang. Uit: alleen inschrijven, plannen en betalen."
                        />
                    </section>
                </template>
            </form>
        </div>

        <!-- Vaste balk onderaan, binnen duimbereik. -->
        <div class="fixed inset-x-0 bottom-0 z-30 border-t border-border bg-card/95 backdrop-blur">
            <div class="mx-auto flex w-full max-w-2xl items-center justify-between gap-3 p-4">
                <Link
                    v-if="step > 1"
                    :href="'/instellingen/inschrijven/stap/' + (step - 1)"
                    class="inline-flex h-11 items-center gap-2 rounded-xl border border-border bg-card px-4 text-sm font-medium transition hover:border-primary"
                >
                    <ArrowLeft class="size-4" />
                    Vorige
                </Link>
                <Link
                    v-else-if="completed"
                    href="/instellingen/inschrijven"
                    class="inline-flex h-11 items-center gap-2 rounded-xl border border-border bg-card px-4 text-sm font-medium transition hover:border-primary"
                >
                    <ArrowLeft class="size-4" />
                    Overzicht
                </Link>
                <span v-else></span>

                <button
                    type="submit"
                    form="wizard"
                    :disabled="form.processing"
                    class="inline-flex h-11 items-center gap-2 rounded-xl bg-primary px-5 text-sm font-semibold text-primary-foreground transition hover:opacity-90 disabled:opacity-60"
                >
                    <template v-if="completed || laatste">
                        <Check class="size-4" />
                        {{ laatste && !completed ? 'Afronden' : 'Opslaan' }}
                    </template>
                    <template v-else>
                        Opslaan en verder
                        <ArrowRight class="size-4" />
                    </template>
                </button>
            </div>
        </div>
    </AppLayout>
</template>
