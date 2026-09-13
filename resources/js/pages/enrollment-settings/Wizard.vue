<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import EnrollmentFlow from '@/components/onboarding/EnrollmentFlow.vue';
import EnrollmentPreview from '@/components/onboarding/EnrollmentPreview.vue';
import InviteForm, { type Uitnodiging } from '@/components/onboarding/InviteForm.vue';
import ToggleSwitch from '@/components/ToggleSwitch.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, Check, ImagePlus, Info, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

/**
 * De wizard: van niets naar een draaiende school, in zeven stappen.
 *
 * Stap één gaat over de school zelf (naam, logo, kleur, locatie); de rest over
 * inschrijven en innen. Bewust één wizard en geen aparte intake ernaast: dan
 * zijn er twee plekken waar je hetzelfde instelt, en die lopen uit elkaar.
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
    school: {
        name: string;
        slug: string;
        contact_name: string | null;
        contact_email: string | null;
        contact_phone: string | null;
        brand_color: string | null;
        logo: string | null;
        locations: string[];
        domain: string | null;
    };
    groups: { id: number; name: string; age_category: string | null }[];
    ageCategories: string[];
    invitations: Uitnodiging[];
    invitationDays: number;
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
              // Stap één gaat over de school zelf. Vier vragen, meer niet: elke
              // vraag hier is er een waarop iemand kan afhaken vóórdat hij het
              // product heeft gezien.
              name: props.school.name,
              slug: props.school.slug,
              contact_name: props.school.contact_name ?? '',
              contact_email: props.school.contact_email ?? '',
              contact_phone: props.school.contact_phone ?? '',
              brand_color: props.school.brand_color ?? '#12813D',
              logo: null as File | null,
              remove_logo: false as boolean,
              location: '' as string,
          }
        : props.step === 2
          ? {
                offering_types: [...(s.offering_types as string[])],
                enrollment_moments: [...(s.enrollment.moments as string[])],
                training_open: s.training_enrollment.open as boolean,
                training_payment_methods: [...(s.training_enrollment.payment_methods as string[])],
                training_requires_approval: s.training_enrollment.requires_approval as boolean,
                trial_enabled: s.trial.enabled as boolean,
                trial_amount: s.trial.amount as string,
            }
          : props.step === 3
            ? {
                  registration_fee_enabled: s.registration_fee.enabled as boolean,
                  registration_fee_amount: s.registration_fee.amount as string,
                  kit_enabled: s.kit.enabled as boolean,
                  kit_amount: s.kit.amount as string,
              }
            : props.step === 4
              ? {
                    default_payment_types: [...(s.default_payment.types as string[])],
                    installments: s.default_payment.installments as number,
                    installment_interval: s.default_payment.interval as string,
                    auto_renew_block: s.auto_renew_block as boolean,
                    notice_months: s.notice_months as number,
                    approval: s.approval as string,
                    chargeback_fee_enabled: s.chargeback_fee.enabled as boolean,
                    chargeback_fee_amount: s.chargeback_fee.amount as string,
                    dunning_days: s.dunning.text as string,
                }
              : props.step === 5
                ? {
                      free_until_days: s.cancellation.free_until_days as number,
                      retain_percent: s.cancellation.retain_percent as number,
                      absence: s.absence as string,
                  }
                : props.step === 6
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
                  : props.step === 8
                    ? {
                          // Wat er al is, plus een lege regel om mee te beginnen.
                          groups: [
                              ...props.groups.map((g) => ({ name: g.name, age_category: g.age_category ?? '' })),
                              ...(props.groups.length ? [] : [{ name: '', age_category: '' }]),
                          ] as { name: string; age_category: string }[],
                      }
                    : props.step === 9
                      ? {}
                      : {
                            waitlist: s.capacity.waitlist as boolean,
                            pay_on_placement: s.capacity.pay_on_placement as boolean,
                            invitation_days: s.capacity.invitation_days as number,
                            fields: { ...(s.fields as Record<string, string>) },
                            consents: Object.fromEntries(
                                props.consents.map((c) => [c.key, { required: c.required, title: c.title, body: c.body }]),
                            ) as Record<string, { required: boolean; title: string; body: string }>,
                            development: props.development,
                        },
);

const f = form as any;

const opslaan = () => {
    // Stap één kan een logo meesturen, en een bestand gaat niet mee met PATCH.
    // Inertia lost dat op met een POST plus _method; voor de server is er geen
    // verschil, dus de route blijft één route.
    // Na het opslaan komt de volgende stap: dezelfde pagina-component met
    // andere props. Inertia bewaart bij een formulier standaard de staat van
    // de component, en dan bleef het formulier van stap één staan terwijl
    // stap twee getekend werd: een leeg scherm. Met preserveState 'errors'
    // blijft wat je typte alleen staan bij een validatiefout.
    if (props.step === 1) {
        form.transform((data) => ({ ...data, _method: 'patch' })).post('/instellingen/inschrijven/stap/1', {
            preserveScroll: true,
            preserveState: 'errors',
            forceFormData: true,
        });

        return;
    }

    form.patch('/instellingen/inschrijven/stap/' + props.step, { preserveScroll: true, preserveState: 'errors' });
};

/** Overslaan: elke vraag heeft een bruikbare standaard. */
const overslaan = () =>
    router.post('/instellingen/inschrijven/stap/' + props.step + '/overslaan', {}, { preserveScroll: true, preserveState: false });

// Live voorbeeld van het gekozen logo, nog vóór het is opgeslagen.
const logoVoorbeeld = ref<string | null>(props.school.logo);

const groepFout = (i: number) => (form.errors as Record<string, string | undefined>)['groups.' + i + '.name'];
const voegGroepToe = () => (f.groups as { name: string; age_category: string }[]).push({ name: '', age_category: '' });
const haalGroepWeg = (i: number) => (f.groups as unknown[]).splice(i, 1);

/** Een adres uit de naam, zolang je hem nog niet zelf hebt aangepast. */
const slugUitNaam = (naam: string) =>
    naam
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');

const slugHandmatig = ref(props.school.slug !== slugUitNaam(props.school.name));

const bijNaam = () => {
    if (!slugHandmatig.value) {
        f.slug = slugUitNaam(f.name);
    }
};

const kiesLogo = (event: Event) => {
    const bestand = (event.target as HTMLInputElement).files?.[0] ?? null;
    f.logo = bestand;
    f.remove_logo = false;
    logoVoorbeeld.value = bestand ? URL.createObjectURL(bestand) : null;
};

/** Aan- of uitvinken in een lijst. Elke vraag met vinkjes werkt zo. */
const wissel = (lijst: string[], waarde: string) => {
    const i = lijst.indexOf(waarde);
    if (i === -1) {
        lijst.push(waarde);
    } else {
        lijst.splice(i, 1);
    }
};

const wisselSoort = (waarde: string) => wissel(f.offering_types as string[], waarde);

/**
 * Wanneer een ouder kan instappen. Meerdere mag: een school die het hele jaar
 * laat instromen heeft óók kampen. Het is uitleg én instelling tegelijk - de
 * samenvatting zegt straks in gewone taal wat je hier koos.
 */
const instapmomenten = [
    {
        value: 'anytime',
        label: 'Het hele jaar door',
        hint: 'Een ouder kan op elk moment instappen. Past bij doorlopende training en rittenkaarten.',
    },
    {
        value: 'before_block',
        label: 'Vóór de start van een blok of kamp',
        hint: 'Je zet een blok of kamp open met een startdatum; tot die dag kan een ouder inschrijven.',
    },
    {
        value: 'single_training',
        label: 'Voor één losse training',
        hint: 'Een ouder meldt zijn kind aan voor precies die ene training, ook zonder abonnement. Nieuwe trainingen staan dan standaard open.',
    },
    {
        value: 'camp',
        label: 'Voor kampen en clinics',
        hint: 'Losse dagen of een weekend, met een eigen prijs en een eigen inschrijving.',
    },
];

/** Betaalvormen die je naast elkaar kunt aanbieden; de ouder kiest bij het inschrijven. */
const betaalvormen = [
    { value: 'upfront', label: 'Volledig vooraf', hint: 'Eén bedrag bij het inschrijven. Het simpelst voor jou: alles is meteen binnen.' },
    { value: 'installments', label: 'In termijnen', hint: 'Het bedrag verdeeld over een vast aantal keren, bijvoorbeeld drie maandelijkse delen.' },
    { value: 'monthly', label: 'Maandelijks doorlopend', hint: 'Elke maand een bedrag, tot iemand opzegt. Past bij doorlopende training.' },
];

const wisselBetaalvorm = (waarde: string) => wissel(f.default_payment_types as string[], waarde);
const wisselTrainingBetaalwijze = (waarde: string) => wissel(f.training_payment_methods as string[], waarde);

const trainingBetaalwijzen = [
    { value: 'online', label: 'Online', hint: 'De ouder betaalt meteen via een betaallink.' },
    { value: 'cash', label: 'Contant bij de training', hint: 'De trainer vinkt af dat het is voldaan.' },
];

/**
 * Per stap: wat het betekent voor jou en voor de ouders, in gewone taal.
 * Dit staat boven de vragen, zodat je weet waar je over beslist voordat je
 * iets aanvinkt. Meerdere antwoorden mogen overal waar dat kan.
 */
const uitleg: Record<number, string[]> = {
    1: ['Dit is wat ouders als eerste zien: de naam op je inschrijfpagina, je logo in de app en boven elke e-mail.'],
    2: [
        'Vier korte blokken. Rechts (op een telefoon: hierboven) zie je meteen wat een ouder straks op je inschrijfpagina ziet; het verandert mee met wat je aanvinkt.',
        'Alles mag naast elkaar, en je kunt overal meerdere antwoorden kiezen.',
    ],
    3: ['Eenmalige kosten bij de eerste inschrijving, los van de training zelf. Ze komen automatisch op de rekening en staan op het formulier.'],
    4: [
        'Hoe ouders kunnen betalen. Je kunt meerdere betaalvormen aanbieden; de ouder kiest dan zelf bij het inschrijven.',
        'Daaronder: of jij eerst naar een aanmelding kijkt, en wat er gebeurt als een blok afloopt of een betaling mislukt.',
    ],
    5: ['Wat een ouder terugkrijgt als hij annuleert of een kind ziek is. Dit staat letterlijk op het inschrijfformulier, zodat er later geen discussie is.'],
    6: ['Kortingen worden automatisch berekend bij het inschrijven. Wat je hier aanzet, zie je terug op de rekening als aparte regel.'],
    7: ['Wat een ouder invult en aanvinkt bij het inschrijven, en wat er gebeurt als een blok vol is.'],
    8: ['Een groep is waar je op plant en afvinkt. Begin met de groepen die je nu hebt; een speler mag straks in meerdere groepen.'],
    9: ['Trainers krijgen een e-mail en kiezen zelf een wachtwoord. Ze zien alleen hun eigen trainingen en spelers.'],
};

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
        <div class="mx-auto w-full p-4 pb-28" :class="step === 2 ? 'max-w-5xl' : 'max-w-2xl'">
            <!-- Waar je bent. Zes bolletjes, de huidige groen, gedane met een vinkje. -->
            <div class="flex items-center justify-between gap-2">
                <p class="text-sm text-muted-foreground">Stap {{ step }} van {{ steps.length }}</p>
                <ol class="hidden items-center gap-1.5 sm:flex">
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

                <!-- Op een telefoon een balk in plaats van zeven bolletjes: zeven
                     tikvlakken van 44 pixels passen niet naast elkaar op 375, en
                     de teller ernaast zegt al waar je bent. -->
                <div class="h-1.5 w-24 overflow-hidden rounded-full bg-secondary sm:hidden">
                    <div class="h-full rounded-full bg-primary transition-all" :style="{ width: (step / steps.length) * 100 + '%' }"></div>
                </div>
            </div>

            <h1 class="mt-3 text-2xl font-semibold tracking-tight">{{ huidige.title }}</h1>
            <p class="mt-1 text-sm text-muted-foreground">{{ huidige.hint }}</p>

            <!-- Wat deze stap betekent, vóór de vragen. Een instelling waarvan
                 je niet weet wat hij doet vink je verkeerd aan. -->
            <div v-if="uitleg[step]" class="mt-4 flex gap-3 rounded-xl bg-primary/10 p-4 text-sm leading-relaxed">
                <Info class="mt-0.5 size-4 shrink-0 text-primary" />
                <div class="space-y-1">
                    <p v-for="(regel, i) in uitleg[step]" :key="i">{{ regel }}</p>
                </div>
            </div>

            <form id="wizard" class="mt-6 space-y-4" @submit.prevent="opslaan">
                <!-- ================= 1. Je school ================= -->
                <template v-if="step === 1">
                    <section class="rounded-xl border border-border bg-card p-5 shadow-sm">
                        <label for="naam" class="font-medium">Hoe heet je school?</label>
                        <p class="mt-1 text-sm text-muted-foreground">Deze naam staat op je inschrijfpagina en boven elke e-mail aan ouders.</p>
                        <input id="naam" v-model="f.name" type="text" :class="invoerKlasse" class="mt-3" @input="bijNaam" />
                        <InputError class="mt-2" :message="form.errors.name" />

                        <label for="slug" class="mt-4 block text-sm font-medium">Het adres van je inschrijfpagina</label>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Alleen kleine letters, cijfers en streepjes. Dit is de link die je aan ouders geeft en op je website zet.
                        </p>
                        <div class="mt-2 flex items-center gap-2">
                            <span class="hidden shrink-0 text-sm text-muted-foreground sm:inline">/inschrijven/</span>
                            <input
                                id="slug"
                                v-model="f.slug"
                                type="text"
                                :class="invoerKlasse"
                                class="min-w-0 flex-1"
                                @input="slugHandmatig = true"
                            />
                        </div>
                        <p v-if="f.slug" class="mt-1 truncate text-xs text-muted-foreground">
                            {{ school.domain ? 'https://' + f.slug + '.' + school.domain : '/inschrijven/' + f.slug }}
                        </p>
                        <InputError class="mt-2" :message="form.errors.slug" />
                    </section>

                    <section class="rounded-xl border border-border bg-card p-5 shadow-sm">
                        <p class="font-medium">Contactgegevens</p>
                        <p class="mt-1 text-sm text-muted-foreground">Voor ouders die je willen bereiken, en voor ons als er iets is.</p>

                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <div>
                                <label for="contact_name" class="text-sm font-medium">Contactpersoon</label>
                                <input id="contact_name" v-model="f.contact_name" type="text" :class="invoerKlasse" class="mt-1" />
                            </div>
                            <div>
                                <label for="contact_phone" class="text-sm font-medium">Telefoon</label>
                                <input id="contact_phone" v-model="f.contact_phone" type="tel" :class="invoerKlasse" class="mt-1" />
                            </div>
                            <div class="sm:col-span-2">
                                <label for="contact_email" class="text-sm font-medium">E-mailadres</label>
                                <input id="contact_email" v-model="f.contact_email" type="email" :class="invoerKlasse" class="mt-1" />
                                <InputError class="mt-2" :message="form.errors.contact_email" />
                            </div>
                        </div>
                    </section>

                    <section class="rounded-xl border border-border bg-card p-5 shadow-sm">
                        <p class="font-medium">Je logo</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Staat in de app en in de uitnodigingen die je verstuurt. Heb je er nog geen? Sla deze over.
                        </p>

                        <div class="mt-3 flex flex-wrap items-center gap-3">
                            <span
                                class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-border bg-background"
                            >
                                <img v-if="logoVoorbeeld && !f.remove_logo" :src="logoVoorbeeld" alt="" class="size-full object-contain p-1" />
                                <ImagePlus v-else class="size-5 text-muted-foreground" />
                            </span>

                            <label
                                class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-xl border border-border bg-background px-3 text-sm font-medium transition hover:border-primary"
                            >
                                <ImagePlus class="size-4" />
                                {{ f.logo ? f.logo.name : 'Logo kiezen' }}
                                <input type="file" accept="image/*" class="hidden" @change="kiesLogo" />
                            </label>

                            <button
                                v-if="logoVoorbeeld && !f.remove_logo"
                                type="button"
                                class="inline-flex min-h-11 items-center gap-2 rounded-xl px-3 text-sm text-muted-foreground transition hover:text-destructive"
                                @click="((f.remove_logo = true), (f.logo = null), (logoVoorbeeld = null))"
                            >
                                <Trash2 class="size-4" />
                                Weghalen
                            </button>
                        </div>

                        <InputError class="mt-2" :message="form.errors.logo" />
                    </section>

                    <section class="rounded-xl border border-border bg-card p-5 shadow-sm">
                        <label for="kleur" class="font-medium">Je kleur</label>
                        <p class="mt-1 text-sm text-muted-foreground">
                            De kleur van je knoppen. Statuskleuren en de spelerskaart blijven zoals ze zijn, zodat "waarschuwing" overal hetzelfde
                            betekent.
                        </p>

                        <div class="mt-3 flex items-center gap-3">
                            <input
                                id="kleur"
                                v-model="f.brand_color"
                                type="color"
                                class="size-11 shrink-0 cursor-pointer rounded-lg border border-input bg-background"
                            />
                            <input v-model="f.brand_color" type="text" :class="invoerKlasse" class="min-w-0 flex-1" placeholder="#12813D" />
                        </div>

                        <InputError class="mt-2" :message="form.errors.brand_color" />

                        <!-- Zo staat het straks in de app: de balk met je logo, en
                             een knop in je kleur. Kijken is sneller dan opslaan en
                             terugkomen. -->
                        <div class="mt-4 overflow-hidden rounded-xl border border-border">
                            <div class="theme-donker flex h-12 items-center gap-3 bg-[hsl(var(--topbar))] px-3 text-foreground">
                                <img
                                    v-if="logoVoorbeeld && !f.remove_logo"
                                    :src="logoVoorbeeld"
                                    alt=""
                                    class="h-7 w-auto max-w-[8rem] object-contain"
                                />
                                <span v-else class="text-sm font-semibold">{{ f.name || 'Jouw school' }}</span>
                                <span
                                    class="ml-auto flex size-8 items-center justify-center rounded-md text-xs font-bold text-primary-foreground"
                                    :style="{ backgroundColor: f.brand_color }"
                                >
                                    +
                                </span>
                            </div>
                            <div class="flex items-center justify-between gap-3 bg-background p-3">
                                <span class="text-sm text-muted-foreground">Voorbeeld</span>
                                <span
                                    class="inline-flex h-9 items-center rounded-lg px-3 text-sm font-semibold text-primary-foreground"
                                    :style="{ backgroundColor: f.brand_color }"
                                >
                                    Rapport invullen
                                </span>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-xl border border-border bg-card p-5 shadow-sm">
                        <label for="locatie" class="font-medium">Waar train je?</label>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Eén locatie is genoeg om te beginnen; de rest zet je later bij Mijn bedrijf → Locaties.
                        </p>

                        <input
                            id="locatie"
                            v-model="f.location"
                            type="text"
                            :class="invoerKlasse"
                            class="mt-3"
                            placeholder="Bijvoorbeeld: Sportpark De Vliert, veld 3"
                        />

                        <ul v-if="school.locations.length" class="mt-3 flex flex-wrap gap-2">
                            <li
                                v-for="naam in school.locations"
                                :key="naam"
                                class="rounded-full bg-secondary px-3 py-1 text-xs text-muted-foreground"
                            >
                                {{ naam }}
                            </li>
                        </ul>

                        <InputError class="mt-2" :message="form.errors.location" />
                    </section>
                </template>

                <!-- ================= 2. Inschrijven ================= -->
                <template v-if="step === 2">
                    <div class="lg:grid lg:grid-cols-[minmax(0,1fr)_22rem] lg:items-start lg:gap-6">
                    <div class="space-y-4">
                    <!-- Eerst zien hoe het loopt, dan pas kiezen. -->
                    <section class="rounded-xl border border-border bg-card p-5 shadow-sm">
                        <p class="font-medium">Zo verloopt een inschrijving bij jou</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Van klik tot kind in de groep. Wie wat doet verandert mee met je antwoorden hier en bij Betalen.
                        </p>
                        <div class="mt-4">
                            <EnrollmentFlow
                                :approval="s.approval"
                                :payment-types="s.default_payment.types"
                                :trial-enabled="f.trial_enabled && f.offering_types.includes('proefles')"
                            />
                        </div>
                    </section>

                    <!-- Op een telefoon staat het voorbeeld hier, vóór de vragen. -->
                    <div class="lg:hidden">
                        <EnrollmentPreview
                            :school-name="school.name"
                            :slug="school.slug"
                            :offering-types="f.offering_types"
                            :moments="f.enrollment_moments"
                            :training-open="f.training_open || f.enrollment_moments.includes('single_training')"
                            :training-payment-methods="f.training_payment_methods"
                            :training-requires-approval="f.training_requires_approval"
                            :trial-enabled="f.trial_enabled"
                            :trial-amount="f.trial_amount"
                        />
                    </div>

                    <section class="rounded-xl border border-border bg-card p-5 shadow-sm">
                        <p class="text-xs font-medium uppercase tracking-wide text-primary">Blok 1 van 4</p>
                        <p class="mt-1 text-lg font-semibold">Wat bied je aan?</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Elke vorm die je aanvinkt wordt een soort kaart op je inschrijfpagina, en een keuze in je aanbodformulier. Meerdere mag;
                            uitbreiden kan altijd.
                        </p>
                        <p class="mt-2 text-sm">
                            <span class="font-medium">Voor de ouder:</span> hij ziet alleen wat jij aanzet, met prijs, data en hoeveel plekken er nog zijn.
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

                    <section class="rounded-xl border border-border bg-card p-5 shadow-sm">
                        <p class="text-xs font-medium uppercase tracking-wide text-primary">Blok 2 van 4</p>
                        <p class="mt-1 text-lg font-semibold">Wanneer kunnen ouders inschrijven?</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Kies alles wat bij jou voorkomt. Dit bepaalt de zin bovenaan je inschrijfpagina en welk aanbod er wanneer open staat.
                        </p>
                        <p class="mt-2 text-sm">
                            <span class="font-medium">Voor de ouder:</span> hij weet meteen of hij nu kan instappen, of moet wachten op een volgend blok.
                        </p>

                        <div class="mt-4 grid gap-2">
                            <button
                                v-for="moment in instapmomenten"
                                :key="moment.value"
                                type="button"
                                class="flex min-h-11 items-start gap-3 rounded-xl border p-3 text-left transition"
                                :class="
                                    f.enrollment_moments.includes(moment.value) ? 'border-primary bg-primary/5' : 'border-border hover:border-primary/50'
                                "
                                :aria-pressed="f.enrollment_moments.includes(moment.value)"
                                @click="wissel(f.enrollment_moments, moment.value)"
                            >
                                <span
                                    class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-md border"
                                    :class="
                                        f.enrollment_moments.includes(moment.value)
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'border-border'
                                    "
                                >
                                    <Check v-if="f.enrollment_moments.includes(moment.value)" class="size-3.5" />
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-medium">{{ moment.label }}</span>
                                    <span class="block text-xs text-muted-foreground">{{ moment.hint }}</span>
                                </span>
                            </button>
                        </div>
                        <InputError class="mt-2" :message="form.errors.enrollment_moments" />
                    </section>

                    <section class="rounded-xl border border-border bg-card p-5 shadow-sm">
                        <p class="text-xs font-medium uppercase tracking-wide text-primary">Blok 3 van 4</p>
                        <p class="mt-1 text-lg font-semibold">Losse trainingen</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Naast een blok of abonnement kan een kind ook meedoen aan precies één training. Dit is wat een nieuwe training standaard
                            krijgt; per training kun je ervan afwijken.
                        </p>
                        <p class="mt-2 text-sm">
                            <span class="font-medium">Voor de ouder:</span> in zijn agenda staat bij zo'n training een knop Inschrijven, met hoeveel plekken er
                            nog zijn. Zit het vol, dan komt hij op de wachtlijst.
                        </p>

                        <div class="mt-3 divide-y divide-border">
                            <ToggleSwitch
                                v-model="f.training_open"
                                label="Ouders kunnen hun kind aanmelden voor één training"
                                description="Aan: elke nieuwe training staat open voor losse aanmelding. Uit: alleen de groep traint mee."
                            />

                            <div v-if="f.training_open || f.enrollment_moments.includes('single_training')" class="py-4">
                                <p class="text-sm font-medium">Hoe betaalt een ouder dan?</p>
                                <p class="text-xs text-muted-foreground">Allebei mag; de ouder kiest.</p>
                                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                    <button
                                        v-for="wijze in trainingBetaalwijzen"
                                        :key="wijze.value"
                                        type="button"
                                        class="flex min-h-11 items-start gap-3 rounded-xl border p-3 text-left transition"
                                        :class="
                                            f.training_payment_methods.includes(wijze.value)
                                                ? 'border-primary bg-primary/5'
                                                : 'border-border hover:border-primary/50'
                                        "
                                        :aria-pressed="f.training_payment_methods.includes(wijze.value)"
                                        @click="wisselTrainingBetaalwijze(wijze.value)"
                                    >
                                        <span
                                            class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-md border"
                                            :class="
                                                f.training_payment_methods.includes(wijze.value)
                                                    ? 'border-primary bg-primary text-primary-foreground'
                                                    : 'border-border'
                                            "
                                        >
                                            <Check v-if="f.training_payment_methods.includes(wijze.value)" class="size-3.5" />
                                        </span>
                                        <span class="min-w-0">
                                            <span class="block text-sm font-medium">{{ wijze.label }}</span>
                                            <span class="block text-xs text-muted-foreground">{{ wijze.hint }}</span>
                                        </span>
                                    </button>
                                </div>
                            </div>

                            <ToggleSwitch
                                v-if="f.training_open || f.enrollment_moments.includes('single_training')"
                                v-model="f.training_requires_approval"
                                label="Jij kijkt eerst naar de aanmelding"
                                description="Aan: een aanmelding is eerst een aanvraag die jij goedkeurt; daarna pas betalen. Uit: direct ingeschreven."
                            />
                        </div>
                    </section>

                    <section class="rounded-xl border border-border bg-card p-5 shadow-sm">
                        <p class="text-xs font-medium uppercase tracking-wide text-primary">Blok 4 van 4</p>
                        <p class="mt-1 text-lg font-semibold">Proefles</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Eén keer meetrainen om te kijken of het bevalt. De proefles komt vanzelf op je inschrijfpagina te staan; je hoeft hem niet
                            als aanbod aan te maken.
                        </p>
                        <p v-if="!f.offering_types.includes('proefles')" class="mt-3 rounded-lg bg-secondary p-3 text-sm text-muted-foreground">
                            Vink in blok 1 "Proefles" aan om hem aan te bieden.
                        </p>
                        <ToggleSwitch
                            v-else
                            v-model="f.trial_enabled"
                            label="Proefles aanbieden"
                            description="Voor de ouder: een kaart 'Proefles' bovenaan de pagina, gratis of tegen een klein bedrag."
                        />

                        <div v-if="f.trial_enabled && f.offering_types.includes('proefles')" class="mt-3 border-t border-border pt-4">
                            <label for="trial_amount" class="block text-sm font-medium">Wat kost de proefles?</label>
                            <p class="text-xs text-muted-foreground">Leeg of 0 betekent gratis.</p>
                            <div class="mt-2 flex items-center gap-2">
                                <span class="text-sm text-muted-foreground">€</span>
                                <input id="trial_amount" v-model="f.trial_amount" inputmode="decimal" placeholder="0,00" :class="getalKlasse" />
                            </div>
                            <InputError class="mt-2" :message="form.errors.trial_amount" />
                        </div>
                    </section>
                    </div>

                    <!-- Op een groot scherm blijft het voorbeeld in beeld terwijl je scrolt. -->
                    <div class="hidden lg:sticky lg:top-24 lg:block">
                        <EnrollmentPreview
                            :school-name="school.name"
                            :slug="school.slug"
                            :offering-types="f.offering_types"
                            :moments="f.enrollment_moments"
                            :training-open="f.training_open || f.enrollment_moments.includes('single_training')"
                            :training-payment-methods="f.training_payment_methods"
                            :training-requires-approval="f.training_requires_approval"
                            :trial-enabled="f.trial_enabled"
                            :trial-amount="f.trial_amount"
                        />
                    </div>
                    </div>
                </template>

                <!-- ================= 3. Kosten erbij ================= -->
                <template v-else-if="step === 3">
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

                <!-- ================= 4. Betalen ================= -->
                <template v-else-if="step === 4">
                    <section class="rounded-xl border border-border bg-card p-5 shadow-sm">
                        <p class="font-medium">Zo verloopt een inschrijving bij jou</p>
                        <p class="mt-1 text-sm text-muted-foreground">Kijk wat er verandert als je hieronder iets aanvinkt.</p>
                        <div class="mt-4">
                            <EnrollmentFlow
                                :approval="f.approval"
                                :payment-types="f.default_payment_types"
                                :trial-enabled="s.trial.enabled && s.offering_types.includes('proefles')"
                            />
                        </div>
                    </section>

                    <section class="rounded-xl border border-border bg-card p-5 shadow-sm">
                        <p class="font-medium">Welke betaalvormen bied je aan?</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Meerdere mag: de ouder kiest dan bij het inschrijven. Ze staan alvast klaar bij elk nieuw aanbod; per aanbod kun je
                            ervan afwijken.
                        </p>

                        <div class="mt-4 grid gap-2">
                            <button
                                v-for="vorm in betaalvormen"
                                :key="vorm.value"
                                type="button"
                                class="flex min-h-11 items-start gap-3 rounded-xl border p-3 text-left transition"
                                :class="
                                    f.default_payment_types.includes(vorm.value) ? 'border-primary bg-primary/5' : 'border-border hover:border-primary/50'
                                "
                                :aria-pressed="f.default_payment_types.includes(vorm.value)"
                                @click="wisselBetaalvorm(vorm.value)"
                            >
                                <span
                                    class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-md border"
                                    :class="
                                        f.default_payment_types.includes(vorm.value)
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'border-border'
                                    "
                                >
                                    <Check v-if="f.default_payment_types.includes(vorm.value)" class="size-3.5" />
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-medium">{{ vorm.label }}</span>
                                    <span class="block text-xs text-muted-foreground">{{ vorm.hint }}</span>
                                </span>
                            </button>
                        </div>
                        <InputError class="mt-2" :message="form.errors.default_payment_types" />

                        <div v-if="f.default_payment_types.includes('installments')" class="mt-4 flex flex-wrap items-end gap-4 border-t border-border pt-4">
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
                        <p class="mt-1 text-sm text-muted-foreground">
                            Wat er gebeurt als een ouder zich aanmeldt. Hier kies je er één: het is of het één, of het ander.
                        </p>

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

                        <div class="py-3">
                            <label for="dunning_days" class="block text-sm font-medium">Herinneringen na een mislukte betaling</label>
                            <p class="text-xs text-muted-foreground">Na hoeveel dagen, met telkens een nieuwe betaallink. Bijvoorbeeld 3, 7, 14.</p>
                            <input id="dunning_days" v-model="f.dunning_days" placeholder="3, 7, 14" :class="'mt-2 ' + invoerKlasse" />
                            <InputError :message="form.errors.dunning_days" />
                        </div>
                    </section>
                </template>

                <!-- ================= 5. Annuleren ================= -->
                <template v-else-if="step === 5">
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

                <!-- ================= 6. Kortingen ================= -->
                <template v-else-if="step === 6">
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

                <!-- ================= 7. Formulier ================= -->
                <template v-else-if="step === 7">
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
                        <div v-if="f.waitlist" class="flex items-center justify-between gap-4 py-3">
                            <label for="invitation_days" class="min-w-0">
                                <span class="block text-sm font-medium">Hoe lang een uitnodiging geldig is</span>
                                <span class="block text-xs text-muted-foreground"
                                    >Komt er plek, dan krijgt de ouder een betaallink. Verloopt die, dan schuift de volgende door.</span
                                >
                            </label>
                            <div class="flex shrink-0 items-center gap-2">
                                <input
                                    id="invitation_days"
                                    v-model.number="f.invitation_days"
                                    type="number"
                                    min="1"
                                    max="30"
                                    :class="'w-20 ' + getalKlasse"
                                />
                                <span class="text-sm text-muted-foreground">dagen</span>
                            </div>
                        </div>
                        <InputError :message="form.errors.invitation_days" />
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
                                        class="min-h-11 rounded-md text-xs font-medium transition"
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
                                            class="mt-1 min-h-11 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm outline-none focus:border-primary"
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
                <!-- ================= 8. Groepen ================= -->
                <template v-else-if="step === 8">
                    <section class="rounded-xl border border-border bg-card p-5 shadow-sm">
                        <p class="font-medium">In welke groepen train je?</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Een groep is waar je op plant en afvinkt. Een speler mag in meerdere groepen zitten; indelen doe je straks per speler.
                        </p>

                        <div class="mt-4 space-y-2">
                            <div v-for="(groep, i) in f.groups" :key="i" class="flex flex-col gap-2 sm:flex-row">
                                <input
                                    v-model="groep.name"
                                    type="text"
                                    :class="invoerKlasse"
                                    class="min-w-0 sm:flex-1"
                                    placeholder="Bijvoorbeeld: Keepers O12"
                                    :aria-label="'Naam van groep ' + (i + 1)"
                                />
                                <div class="flex gap-2">
                                    <select
                                        v-model="groep.age_category"
                                        :class="invoerKlasse"
                                        class="min-w-0 flex-1 sm:w-40"
                                        :aria-label="'Leeftijd van groep ' + (i + 1)"
                                    >
                                        <option value="">Leeftijd (optioneel)</option>
                                        <option v-for="cat in ageCategories" :key="cat" :value="cat">{{ cat }}</option>
                                    </select>
                                    <button
                                        type="button"
                                        class="flex size-11 shrink-0 items-center justify-center rounded-lg text-muted-foreground transition hover:text-destructive"
                                        :aria-label="'Groep ' + (i + 1) + ' weghalen'"
                                        @click="haalGroepWeg(i)"
                                    >
                                        <Trash2 class="size-4" />
                                    </button>
                                </div>
                                <InputError :message="groepFout(i)" />
                            </div>
                        </div>

                        <button
                            type="button"
                            class="mt-3 inline-flex min-h-11 items-center gap-2 rounded-xl border border-border bg-background px-3 text-sm font-medium transition hover:border-primary"
                            @click="voegGroepToe"
                        >
                            + Nog een groep
                        </button>
                    </section>
                </template>

                <!-- ================= 9. Trainers ================= -->
                <template v-else-if="step === 9">
                    <InviteForm role="trainer" :invitations="invitations" :valid-days="invitationDays" title="Wie geeft er training?" />

                    <p class="text-xs text-muted-foreground">
                        Geef je alleen zelf training? Dan sla je dit over. Uitnodigen kan altijd later, bij Mijn bedrijf â†’ Personeel.
                    </p>
                </template>
            </form>
        </div>

        <!-- Vaste balk onderaan, binnen duimbereik. -->
        <div class="fixed inset-x-0 bottom-[var(--pp-tabbar)] z-30 border-t border-border bg-card/95 backdrop-blur">
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

                <!-- Overslaan mag, en zegt erbij dat het later kan. Wie er nu
                     geen antwoord op heeft moet verder kunnen in plaats van te
                     stoppen; elke vraag heeft een bruikbare standaard. -->
                <button
                    v-if="!completed"
                    type="button"
                    class="ml-auto inline-flex min-h-11 shrink-0 items-center rounded-xl px-1 text-sm text-muted-foreground transition hover:text-foreground"
                    title="Je kunt dit later in de instellingen aanvullen"
                    @click="overslaan"
                >
                    Overslaan
                </button>

                <button
                    type="submit"
                    form="wizard"
                    :disabled="form.processing"
                    class="inline-flex h-11 items-center gap-2 rounded-xl bg-primary px-5 text-sm font-semibold text-primary-foreground transition hover:opacity-90 disabled:opacity-60"
                >
                    <template v-if="completed || laatste">
                        <Check class="size-4" />
                        {{ laatste && !completed ? 'Afronden en naar mijn dashboard' : 'Opslaan' }}
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
