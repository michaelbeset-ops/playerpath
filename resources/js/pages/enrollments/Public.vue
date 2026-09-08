<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import InputError from '@/components/InputError.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, CalendarRange, Check, CheckCircle2, LoaderCircle, MapPin, Plus, Ticket, Trash2, Users } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

/**
 * De openbare inschrijfflow, in stappen: aanbod → kind(eren) → jij →
 * toestemmingen → betalen → overzicht. Elke stap is één scherm; de knop
 * "Verder" zit in een vaste balk onderaan, binnen duimbereik. De meeste ouders
 * doen dit op hun telefoon.
 *
 * Het formulier bevat geen regels: welke velden er staan, welke toestemmingen
 * verplicht zijn en welke betaalvormen er zijn komt allemaal van de server
 * (`config`), en het overzicht wordt door de server berekend (OrderBuilder),
 * zodat wat je hier ziet precies is wat er wordt vastgelegd.
 */
interface Betaalvorm {
    id: number;
    type: string;
    label: string;
    description: string;
    is_default: boolean;
}

interface Aanbod {
    id: number;
    name: string;
    description: string | null;
    type: string;
    type_key: string;
    is_trial: boolean;
    image: string | null;
    amount: string;
    is_free: boolean;
    billing: string;
    is_subscription: boolean;
    starts_on: string | null;
    ends_on: string | null;
    sessions_count: number | null;
    location: string | null;
    min_age: number | null;
    max_age: number | null;
    audience: string;
    audience_label: string;
    credits: number | null;
    spots_left: number | null;
    is_full: boolean;
    payment_options: Betaalvorm[];
}

interface Kind {
    player_id: number | null;
    first_name: string;
    last_name: string;
    date_of_birth: string;
    position: string;
    product_id: number | null;
    payment_option_id: number | null;
    details: { kledingmaat: string; niveau: string; medisch: string };
}

interface BestaandKind {
    id: number;
    first_name: string;
    last_name: string;
    date_of_birth: string | null;
    position: string;
    age: number | null;
}

const props = defineProps<{
    school: { name: string; slug: string };
    products: Aanbod[];
    selected: number | null;
    config: {
        guardian: { name: string; email: string; children: BestaandKind[] } | null;
        fields: Record<string, 'off' | 'optional' | 'required'>;
        consents: { key: string; title: string; body: string; required: boolean }[];
        extras: { registration_fee: string | null; kit: string | null; code: boolean; family: number | null };
        policy: {
            approval: string;
            cancellation: { free_until_days: number; retain_percent: number };
            absence: string;
            waitlist: boolean;
            notice_months: number;
        };
        paymentMethods: { value: string; label: string; hint: string; subscription_only: boolean }[];
        positions: Record<string, string>;
    };
    loginUrl: string;
    submitted: { status: string; names: string; new_account: boolean; total: string | null; offline: boolean } | null;
}>();

const ingelogd = computed(() => props.config.guardian !== null);

const nieuwKind = (productId: number | null): Kind => ({
    player_id: null,
    first_name: '',
    last_name: '',
    date_of_birth: '',
    position: 'keeper',
    product_id: productId,
    payment_option_id: props.products.find((p) => p.id === productId)?.payment_options.find((o) => o.is_default)?.id ?? null,
    details: { kledingmaat: '', niveau: '', medisch: '' },
});

const form = useForm({
    children: [nieuwKind(props.selected ?? null)] as Kind[],
    guardian_name: props.config.guardian?.name ?? '',
    guardian_email: props.config.guardian?.email ?? '',
    guardian_phone: '',
    relationship: '',
    password: '',
    consents: props.config.consents.filter((c) => c.required).map((c) => c.key) as string[],
    payment_method: props.config.paymentMethods[0]?.value ?? null,
    code: '',
    note: '',
});

/* ---------- Stappen ---------- */

type Stap = 'aanbod' | 'kinderen' | 'jij' | 'toestemming' | 'betalen' | 'overzicht';

const stappen = computed<Stap[]>(() => {
    const lijst: Stap[] = ['aanbod', 'kinderen'];
    if (!ingelogd.value) lijst.push('jij');
    if (props.config.consents.length) lijst.push('toestemming');
    lijst.push('betalen', 'overzicht');
    return lijst;
});

const titels: Record<Stap, string> = {
    aanbod: 'Kies wat je wilt doen',
    kinderen: 'Wie schrijf je in?',
    jij: 'Jouw gegevens',
    toestemming: 'Toestemmingen',
    betalen: 'Betalen',
    overzicht: 'Controleer en bevestig',
};

const stap = ref<Stap>(props.selected ? 'kinderen' : 'aanbod');
const stapIndex = computed(() => stappen.value.indexOf(stap.value));

const gekozen = computed(() => props.products.find((p) => p.id === form.children[0]?.product_id) ?? null);
const opWachtlijst = computed(() => gekozen.value?.is_full ?? false);

const kies = (aanbod: Aanbod) => {
    for (const kind of form.children) {
        kind.product_id = aanbod.id;
        kind.payment_option_id = aanbod.payment_options.find((o) => o.is_default)?.id ?? aanbod.payment_options[0]?.id ?? null;
    }
    stap.value = 'kinderen';
    window.scrollTo({ top: 0 });
};

const vorige = () => {
    if (stapIndex.value > 0) {
        stap.value = stappen.value[stapIndex.value - 1];
        window.scrollTo({ top: 0 });
    }
};

const verder = async () => {
    if (stap.value === 'betalen') {
        await haalOverzicht();
    }

    if (stapIndex.value < stappen.value.length - 1) {
        stap.value = stappen.value[stapIndex.value + 1];
        window.scrollTo({ top: 0 });
    }
};

// De client controleert alleen of er iets is ingevuld; de echte validatie
// (leeftijd, positie, vol) doet de server bij het bevestigen.
const kanVerder = computed(() => {
    switch (stap.value) {
        case 'aanbod':
            return gekozen.value !== null;
        case 'kinderen':
            return form.children.every((k) => k.player_id !== null || (k.first_name && k.last_name && k.date_of_birth));
        case 'jij':
            return !!(form.guardian_name && form.guardian_email && form.password.length >= 8);
        case 'toestemming':
            return props.config.consents.filter((c) => c.required).every((c) => form.consents.includes(c.key));
        case 'betalen':
            return form.children.every((k) => k.payment_option_id !== null);
        default:
            return true;
    }
});

/* ---------- Kinderen ---------- */

// Ingelogd: het eerste (nog niet gekozen) kind staat al klaar. Een ouder komt
// voor zijn eigen kind, niet voor "nieuw kind".
const bestaandKind = (kind: Kind) => {
    const gekozenIds = form.children.map((k) => k.player_id);
    const volgende = (props.config.guardian?.children ?? []).find((b) => !gekozenIds.includes(b.id));

    if (volgende) {
        kiesBestaand(kind, volgende);
    }

    return kind;
};

const voegKindToe = () => form.children.push(bestaandKind(nieuwKind(gekozen.value?.id ?? null)));
const verwijderKind = (i: number) => form.children.splice(i, 1);

const kiesBestaand = (kind: Kind, bestaand: BestaandKind | null) => {
    kind.player_id = bestaand?.id ?? null;
    if (bestaand) {
        kind.first_name = bestaand.first_name;
        kind.last_name = bestaand.last_name;
        kind.date_of_birth = bestaand.date_of_birth ?? '';
        kind.position = bestaand.position;
    }
};

if (props.config.guardian?.children.length) {
    kiesBestaand(form.children[0], props.config.guardian.children[0]);
}

const vraagt = (veld: string) => props.config.fields[veld] !== 'off';
const verplichtVeld = (veld: string) => props.config.fields[veld] === 'required';

/* ---------- Betalen ---------- */

const betaalmethoden = computed(() => props.config.paymentMethods.filter((m) => !m.subscription_only || gekozen.value?.is_subscription));

watch(betaalmethoden, (lijst) => {
    if (!lijst.some((m) => m.value === form.payment_method)) {
        form.payment_method = lijst[0]?.value ?? null;
    }
});

/* ---------- Overzicht ---------- */

interface Overzicht {
    lines: { type: string; description: string; amount: string; amount_cents: number }[];
    total: string;
    total_cents: number;
    recurring: { description: string; amount: string }[];
    code: { code: string; valid: boolean; message: string | null } | null;
}

const overzicht = ref<Overzicht | null>(null);
const overzichtLaadt = ref(false);
const overzichtFout = ref<string | null>(null);

const xsrf = () => decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '');

const haalOverzicht = async () => {
    overzichtLaadt.value = true;
    overzichtFout.value = null;

    try {
        const antwoord = await fetch('/inschrijven/' + props.school.slug + '/overzicht', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': xsrf() },
            body: JSON.stringify({ children: form.children, code: form.code || null }),
        });

        if (antwoord.status === 422) {
            const fouten = await antwoord.json();
            overzichtFout.value = Object.values(fouten.errors ?? {}).flat()[0] as string;
            overzicht.value = null;
            return;
        }

        overzicht.value = await antwoord.json();
    } catch {
        overzichtFout.value = 'Het overzicht kon niet worden opgehaald. Probeer het nog eens.';
    } finally {
        overzichtLaadt.value = false;
    }
};

const betaalregel = computed(() => {
    if (opWachtlijst.value) {
        return 'Je betaalt pas als er een plek vrijkomt.';
    }

    if (overzicht.value && overzicht.value.total_cents === 0) {
        return 'Er valt niets te betalen.';
    }

    const methode = betaalmethoden.value.find((m) => m.value === form.payment_method);

    if (props.config.policy.approval === 'manual') {
        return methode?.value === 'cash'
            ? 'Zodra de school je aanmelding goedkeurt, reken je af bij de school.'
            : 'Zodra de school je aanmelding goedkeurt, krijg je een betaallink per e-mail.';
    }

    return methode?.value === 'cash'
        ? 'Je rekent af bij de school; daarna is de inschrijving rond.'
        : 'Je krijgt meteen een betaallink; na betaling is de inschrijving rond.';
});

const verstuur = () => form.post('/inschrijven/' + props.school.slug, { onError: () => window.scrollTo({ top: 0 }) });

// Een fout van de server op een stap die je al voorbij was: terug ernaartoe.
watch(
    () => form.errors,
    (fouten) => {
        const sleutels = Object.keys(fouten);
        if (!sleutels.length) return;
        if (sleutels.some((k) => k.startsWith('children'))) stap.value = 'kinderen';
        else if (sleutels.some((k) => k.startsWith('guardian') || k === 'password')) stap.value = 'jij';
        else if (sleutels.includes('consents')) stap.value = 'toestemming';
        else if (sleutels.includes('payment_method') || sleutels.includes('code')) stap.value = 'betalen';
    },
    { deep: true },
);

/* ---------- Hulpjes ---------- */

const periode = (a: Aanbod) => (a.starts_on ? (a.ends_on && a.ends_on !== a.starts_on ? a.starts_on + ' t/m ' + a.ends_on : a.starts_on) : null);

const leeftijd = (a: Aanbod) => {
    if (a.min_age && a.max_age) return a.min_age + ' t/m ' + a.max_age + ' jaar';
    if (a.min_age) return 'vanaf ' + a.min_age + ' jaar';
    return a.max_age ? 't/m ' + a.max_age + ' jaar' : null;
};

const fout = (sleutel: string) => (form.errors as Record<string, string>)[sleutel];

const invoer = 'h-11 w-full rounded-lg border border-input bg-background px-3 text-base outline-none focus:border-primary';
</script>

<template>
    <Head :title="'Inschrijven bij ' + school.name" />

    <!-- Licht: dit staat vaak in een iframe op de eigen website van de school. -->
    <div class="min-h-svh bg-background px-4 pb-28 pt-6 text-foreground sm:pt-10">
        <div class="mx-auto w-full max-w-lg">
            <div class="flex items-center gap-3">
                <AppLogoIcon class="size-10 rounded-xl" />
                <div class="min-w-0">
                    <p class="text-xs uppercase tracking-widest text-muted-foreground">Inschrijven bij</p>
                    <p class="break-words text-lg font-semibold">{{ school.name }}</p>
                </div>
            </div>

            <!-- ================= Klaar ================= -->
            <div v-if="submitted" class="mt-8 rounded-2xl border border-primary/40 bg-primary/10 p-6 text-center">
                <CheckCircle2 class="mx-auto size-8 text-primary" />

                <template v-if="submitted.status === 'waitlist'">
                    <p class="mt-3 font-semibold">{{ submitted.names }} staat op de wachtlijst</p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Dit aanbod zit vol. Zodra er plek is hoor je het van de school, en je betaalt pas dan.
                    </p>
                </template>
                <template v-else-if="submitted.status === 'awaiting_approval'">
                    <p class="mt-3 font-semibold">Bedankt, we hebben de aanmelding van {{ submitted.names }} ontvangen</p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        De school bekijkt je aanmelding. Zodra die is goedgekeurd krijg je bericht, met daarin hoe je betaalt.
                    </p>
                </template>
                <template v-else-if="submitted.status === 'awaiting_payment'">
                    <p class="mt-3 font-semibold">Bijna klaar: nog even betalen</p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Er staat {{ submitted.total }} open.
                        {{ submitted.offline ? 'Dat reken je af bij de school.' : 'Je hebt een betaallink per e-mail gekregen.' }}
                        Daarna is de inschrijving van {{ submitted.names }} rond.
                    </p>
                </template>
                <template v-else>
                    <p class="mt-3 font-semibold">De inschrijving van {{ submitted.names }} is rond</p>
                    <p class="mt-1 text-sm text-muted-foreground">Welkom! Je hoort van de school wanneer de trainingen beginnen.</p>
                </template>

                <p v-if="submitted.new_account" class="mt-4 text-sm">
                    Er is een account voor je aangemaakt.
                    <a :href="loginUrl" class="font-medium text-primary underline underline-offset-4">Inloggen</a>
                </p>

                <!-- Ingelogd: terug naar waar je vandaan kwam. Nieuw account: inloggen is de weg. -->
                <a
                    v-if="ingelogd"
                    href="/dashboard"
                    class="mt-5 inline-flex h-11 items-center gap-2 rounded-xl bg-primary px-5 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                >
                    <ArrowLeft class="size-4" />
                    Terug naar het dashboard
                </a>
                <a
                    v-else-if="!submitted.new_account"
                    :href="loginUrl"
                    class="mt-5 inline-flex h-11 items-center gap-2 rounded-xl bg-primary px-5 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                >
                    Inloggen
                </a>
            </div>

            <template v-else>
                <!-- Waar je bent -->
                <div class="mt-6 flex items-center justify-between gap-2">
                    <p class="text-sm text-muted-foreground">Stap {{ stapIndex + 1 }} van {{ stappen.length }}</p>
                    <ol class="flex items-center gap-1.5">
                        <li
                            v-for="(s, i) in stappen"
                            :key="s"
                            class="size-2.5 rounded-full"
                            :class="i === stapIndex ? 'bg-primary' : i < stapIndex ? 'bg-primary/40' : 'bg-secondary'"
                        ></li>
                    </ol>
                </div>
                <h1 class="mt-2 text-2xl font-semibold tracking-tight">{{ titels[stap] }}</h1>

                <p
                    v-if="fout('guardian_email') && stap !== 'jij'"
                    class="mt-3 rounded-lg border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive"
                >
                    {{ fout('guardian_email') }}
                </p>

                <!-- ================= 1. Aanbod ================= -->
                <div v-if="stap === 'aanbod'" class="mt-5 space-y-3">
                    <p v-if="!products.length" class="rounded-xl border border-dashed border-border p-6 text-center text-sm text-muted-foreground">
                        Er staat op dit moment niets open om je voor aan te melden.
                    </p>

                    <button
                        v-for="aanbod in products"
                        :key="aanbod.id"
                        type="button"
                        class="w-full overflow-hidden rounded-2xl border bg-card text-left shadow-sm transition hover:border-primary"
                        :class="gekozen?.id === aanbod.id ? 'border-primary ring-2 ring-primary/20' : 'border-border'"
                        @click="kies(aanbod)"
                    >
                        <img v-if="aanbod.image" :src="aanbod.image" alt="" class="h-32 w-full object-cover" />
                        <div class="p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-xs uppercase tracking-wide text-muted-foreground">
                                        {{ aanbod.type }}<template v-if="aanbod.audience !== 'all'"> · {{ aanbod.audience_label }}</template>
                                    </p>
                                    <p class="font-semibold">{{ aanbod.name }}</p>
                                </div>
                                <p class="tabular shrink-0 text-right">
                                    <span class="font-bold">{{ aanbod.is_free ? 'gratis' : aanbod.amount }}</span>
                                    <span v-if="!aanbod.is_free" class="block text-xs text-muted-foreground">{{ aanbod.billing }}</span>
                                </p>
                            </div>

                            <p v-if="aanbod.description" class="mt-2 text-sm text-muted-foreground">{{ aanbod.description }}</p>

                            <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                <span v-if="periode(aanbod)" class="flex items-center gap-1"
                                    ><CalendarRange class="size-3.5" />{{ periode(aanbod) }}</span
                                >
                                <span v-if="aanbod.sessions_count" class="tabular">{{ aanbod.sessions_count }} trainingen</span>
                                <span v-if="aanbod.location" class="flex items-center gap-1"><MapPin class="size-3.5" />{{ aanbod.location }}</span>
                                <span v-if="leeftijd(aanbod)" class="flex items-center gap-1"><Users class="size-3.5" />{{ leeftijd(aanbod) }}</span>
                                <span v-if="aanbod.credits" class="flex items-center gap-1"
                                    ><Ticket class="size-3.5" />{{ aanbod.credits }} beurten</span
                                >
                            </div>

                            <p v-if="aanbod.payment_options.length > 1" class="mt-2 text-xs text-muted-foreground">
                                Ook:
                                {{
                                    aanbod.payment_options
                                        .filter((o) => !o.is_default)
                                        .map((o) => o.description)
                                        .join(' · ')
                                }}
                            </p>

                            <p v-if="aanbod.is_full" class="mt-2 text-xs font-medium text-warning">Vol · je kunt op de wachtlijst</p>
                            <p v-else-if="aanbod.spots_left !== null && aanbod.spots_left <= 3" class="mt-2 text-xs font-medium text-warning">
                                Nog {{ aanbod.spots_left }} {{ aanbod.spots_left === 1 ? 'plek' : 'plekken' }}
                            </p>
                        </div>
                    </button>
                </div>

                <!-- ================= 2. Kinderen ================= -->
                <div v-else-if="stap === 'kinderen'" class="mt-5 space-y-4">
                    <p v-if="gekozen" class="rounded-lg bg-secondary px-3 py-2 text-sm">
                        <span class="font-medium">{{ gekozen.name }}</span>
                        <span v-if="opWachtlijst" class="text-warning"> · vol, wachtlijst</span>
                    </p>

                    <div v-for="(kind, i) in form.children" :key="i" class="rounded-2xl border border-border bg-card p-4 shadow-sm">
                        <div class="flex items-center justify-between gap-2">
                            <p class="font-medium">{{ form.children.length > 1 ? 'Kind ' + (i + 1) : 'Je kind' }}</p>
                            <button
                                v-if="form.children.length > 1"
                                type="button"
                                class="text-sm text-muted-foreground hover:text-destructive"
                                @click="verwijderKind(i)"
                            >
                                <Trash2 class="size-4" />
                            </button>
                        </div>

                        <!-- Ingelogd: een bestaand kind kiezen, of een nieuw. -->
                        <div v-if="config.guardian?.children.length" class="mt-3 flex flex-wrap gap-2">
                            <button
                                v-for="bestaand in config.guardian.children"
                                :key="bestaand.id"
                                type="button"
                                class="inline-flex min-h-11 items-center rounded-full border px-3 text-sm transition"
                                :class="kind.player_id === bestaand.id ? 'border-primary bg-primary/10 font-medium text-primary' : 'border-border'"
                                @click="kiesBestaand(kind, kind.player_id === bestaand.id ? null : bestaand)"
                            >
                                {{ bestaand.first_name }}<span v-if="bestaand.age" class="text-muted-foreground"> · {{ bestaand.age }}</span>
                            </button>
                            <button
                                type="button"
                                class="inline-flex min-h-11 items-center rounded-full border px-3 text-sm transition"
                                :class="kind.player_id === null ? 'border-primary bg-primary/10 font-medium text-primary' : 'border-border'"
                                @click="kiesBestaand(kind, null)"
                            >
                                Nieuw kind
                            </button>
                        </div>

                        <div v-if="kind.player_id === null" class="mt-3 grid gap-3">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="block text-sm font-medium">Voornaam</label>
                                    <input v-model="kind.first_name" :class="'mt-1 ' + invoer" autocomplete="off" />
                                    <InputError :message="fout('children.' + i + '.first_name')" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium">Achternaam</label>
                                    <input v-model="kind.last_name" :class="'mt-1 ' + invoer" autocomplete="off" />
                                    <InputError :message="fout('children.' + i + '.last_name')" />
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium">Geboortedatum</label>
                                <input v-model="kind.date_of_birth" type="date" :class="'mt-1 ' + invoer" />
                                <InputError :message="fout('children.' + i + '.date_of_birth')" />
                            </div>
                            <div v-if="vraagt('positie')">
                                <label class="block text-sm font-medium">Positie</label>
                                <div class="mt-1 grid grid-cols-2 gap-2">
                                    <label
                                        v-for="(label, waarde) in config.positions"
                                        :key="waarde"
                                        class="flex min-h-11 cursor-pointer items-center gap-2 rounded-lg border px-3 text-sm"
                                        :class="kind.position === waarde ? 'border-primary bg-primary/5' : 'border-border'"
                                    >
                                        <input v-model="kind.position" type="radio" :value="waarde" class="accent-primary" />
                                        {{ label }}
                                    </label>
                                </div>
                                <InputError :message="fout('children.' + i + '.position')" />
                            </div>
                        </div>
                        <p v-else class="mt-3 text-sm text-muted-foreground">
                            {{ kind.first_name }} {{ kind.last_name }} · {{ config.positions[kind.position] }}
                        </p>

                        <div
                            v-if="vraagt('kledingmaat') || vraagt('niveau') || vraagt('medisch')"
                            class="mt-3 grid gap-3 border-t border-border pt-3"
                        >
                            <div v-if="vraagt('kledingmaat')">
                                <label class="block text-sm font-medium">
                                    Kledingmaat <span v-if="!verplichtVeld('kledingmaat')" class="text-muted-foreground">(optioneel)</span>
                                </label>
                                <input v-model="kind.details.kledingmaat" placeholder="152, S, M" :class="'mt-1 ' + invoer" />
                                <InputError :message="fout('children.' + i + '.details.kledingmaat')" />
                            </div>
                            <div v-if="vraagt('niveau')">
                                <label class="block text-sm font-medium">
                                    Niveau <span v-if="!verplichtVeld('niveau')" class="text-muted-foreground">(optioneel)</span>
                                </label>
                                <input v-model="kind.details.niveau" placeholder="Bijv. JO11-2 bij VV Dorp" :class="'mt-1 ' + invoer" />
                                <InputError :message="fout('children.' + i + '.details.niveau')" />
                            </div>
                            <div v-if="vraagt('medisch')">
                                <label class="block text-sm font-medium">
                                    Medische bijzonderheden <span v-if="!verplichtVeld('medisch')" class="text-muted-foreground">(optioneel)</span>
                                </label>
                                <textarea
                                    v-model="kind.details.medisch"
                                    rows="2"
                                    placeholder="Allergieën, blessures, iets wat de trainer moet weten"
                                    class="mt-1 w-full rounded-lg border border-input bg-background px-3 py-2 text-base outline-none focus:border-primary"
                                ></textarea>
                                <InputError :message="fout('children.' + i + '.details.medisch')" />
                            </div>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="flex h-11 w-full items-center justify-center gap-2 rounded-xl border border-dashed border-border text-sm font-medium transition hover:border-primary"
                        @click="voegKindToe"
                    >
                        <Plus class="size-4" />
                        Nog een kind inschrijven
                        <span v-if="config.extras.family" class="text-xs text-primary">· {{ config.extras.family }}% gezinskorting</span>
                    </button>
                </div>

                <!-- ================= 3. Jij ================= -->
                <div v-else-if="stap === 'jij'" class="mt-5 space-y-4">
                    <p class="text-sm text-muted-foreground">
                        Hiermee maak je een account aan, zodat je straks de trainingen, de spelerskaart en je betalingen ziet. Al een account?
                        <a :href="loginUrl" class="font-medium text-primary underline underline-offset-4">Log in</a> en kom hier terug.
                    </p>

                    <div class="grid gap-3 rounded-2xl border border-border bg-card p-4 shadow-sm">
                        <div>
                            <label for="guardian_name" class="block text-sm font-medium">Je naam</label>
                            <input id="guardian_name" v-model="form.guardian_name" autocomplete="name" :class="'mt-1 ' + invoer" />
                            <InputError :message="fout('guardian_name')" />
                        </div>
                        <div>
                            <label for="guardian_email" class="block text-sm font-medium">E-mailadres</label>
                            <input
                                id="guardian_email"
                                v-model="form.guardian_email"
                                type="email"
                                autocomplete="email"
                                inputmode="email"
                                :class="'mt-1 ' + invoer"
                            />
                            <InputError :message="fout('guardian_email')" />
                        </div>
                        <div>
                            <label for="guardian_phone" class="block text-sm font-medium"
                                >Telefoon <span class="text-muted-foreground">(optioneel)</span></label
                            >
                            <input
                                id="guardian_phone"
                                v-model="form.guardian_phone"
                                type="tel"
                                autocomplete="tel"
                                inputmode="tel"
                                :class="'mt-1 ' + invoer"
                            />
                            <InputError :message="fout('guardian_phone')" />
                        </div>
                        <div>
                            <label for="relationship" class="block text-sm font-medium"
                                >Je bent <span class="text-muted-foreground">(optioneel)</span></label
                            >
                            <select id="relationship" v-model="form.relationship" :class="'mt-1 ' + invoer">
                                <option value="">Kies</option>
                                <option value="moeder">Moeder</option>
                                <option value="vader">Vader</option>
                                <option value="verzorger">Verzorger</option>
                            </select>
                        </div>
                        <div>
                            <label for="password" class="block text-sm font-medium">Kies een wachtwoord</label>
                            <input id="password" v-model="form.password" type="password" autocomplete="new-password" :class="'mt-1 ' + invoer" />
                            <p class="mt-1 text-xs text-muted-foreground">Minstens 8 tekens.</p>
                            <InputError :message="fout('password')" />
                        </div>
                    </div>
                </div>

                <!-- ================= 4. Toestemmingen ================= -->
                <div v-else-if="stap === 'toestemming'" class="mt-5 space-y-3">
                    <label
                        v-for="doc in config.consents"
                        :key="doc.key"
                        class="flex cursor-pointer items-start gap-3 rounded-2xl border bg-card p-4 shadow-sm"
                        :class="form.consents.includes(doc.key) ? 'border-primary/50' : 'border-border'"
                    >
                        <input v-model="form.consents" type="checkbox" :value="doc.key" class="mt-1 size-5 shrink-0 accent-primary" />
                        <span class="min-w-0">
                            <span class="block font-medium">
                                {{ doc.title }}
                                <span v-if="!doc.required" class="text-xs font-normal text-muted-foreground">(optioneel)</span>
                            </span>
                            <span class="mt-1 block whitespace-pre-line text-sm text-muted-foreground">{{ doc.body }}</span>
                        </span>
                    </label>
                    <InputError :message="fout('consents')" />
                </div>

                <!-- ================= 5. Betalen ================= -->
                <div v-else-if="stap === 'betalen'" class="mt-5 space-y-4">
                    <div v-for="(kind, i) in form.children" :key="i" class="rounded-2xl border border-border bg-card p-4 shadow-sm">
                        <p class="font-medium">{{ kind.first_name || 'Kind ' + (i + 1) }} · {{ gekozen?.name }}</p>
                        <div class="mt-3 grid gap-2">
                            <label
                                v-for="optie in gekozen?.payment_options ?? []"
                                :key="optie.id"
                                class="flex min-h-11 cursor-pointer items-center gap-3 rounded-lg border px-3 py-2 text-sm"
                                :class="kind.payment_option_id === optie.id ? 'border-primary bg-primary/5' : 'border-border'"
                            >
                                <input v-model="kind.payment_option_id" type="radio" :value="optie.id" class="accent-primary" />
                                <span class="min-w-0">
                                    <span class="block font-medium">{{ optie.description }}</span>
                                    <span class="block text-xs text-muted-foreground">{{ optie.label }}</span>
                                </span>
                            </label>
                        </div>
                        <InputError :message="fout('children.' + i + '.payment_option_id')" />
                    </div>

                    <div
                        v-if="config.extras.registration_fee || config.extras.kit"
                        class="rounded-lg bg-secondary px-3 py-2 text-sm text-muted-foreground"
                    >
                        <span v-if="config.extras.registration_fee">Eenmalig inschrijfgeld {{ config.extras.registration_fee }}. </span>
                        <span v-if="config.extras.kit">Kledingpakket {{ config.extras.kit }}. </span>
                        Eén keer per gezin.
                    </div>

                    <div v-if="betaalmethoden.length" class="rounded-2xl border border-border bg-card p-4 shadow-sm">
                        <p class="font-medium">Hoe wil je betalen?</p>
                        <div class="mt-3 grid gap-2">
                            <label
                                v-for="m in betaalmethoden"
                                :key="m.value"
                                class="flex min-h-11 cursor-pointer items-start gap-3 rounded-lg border px-3 py-2 text-sm"
                                :class="form.payment_method === m.value ? 'border-primary bg-primary/5' : 'border-border'"
                            >
                                <input v-model="form.payment_method" type="radio" :value="m.value" class="mt-1 accent-primary" />
                                <span class="min-w-0">
                                    <span class="block font-medium">{{ m.label }}</span>
                                    <span class="block text-xs text-muted-foreground">{{ m.hint }}</span>
                                </span>
                            </label>
                        </div>
                        <InputError :message="fout('payment_method')" />
                    </div>

                    <div v-if="config.extras.code" class="rounded-2xl border border-border bg-card p-4 shadow-sm">
                        <label for="code" class="block text-sm font-medium"
                            >Kortingscode <span class="text-muted-foreground">(optioneel)</span></label
                        >
                        <input id="code" v-model="form.code" :class="'mt-1 ' + invoer" autocomplete="off" />
                        <InputError :message="fout('code')" />
                    </div>

                    <div>
                        <label for="note" class="block text-sm font-medium"
                            >Opmerking voor de school <span class="text-muted-foreground">(optioneel)</span></label
                        >
                        <textarea
                            id="note"
                            v-model="form.note"
                            rows="2"
                            class="mt-1 w-full rounded-lg border border-input bg-background px-3 py-2 text-base outline-none focus:border-primary"
                        ></textarea>
                    </div>
                </div>

                <!-- ================= 6. Overzicht ================= -->
                <div v-else class="mt-5 space-y-4">
                    <div v-if="overzichtLaadt" class="flex items-center gap-2 text-sm text-muted-foreground">
                        <LoaderCircle class="size-4 animate-spin" />
                        Even rekenen…
                    </div>
                    <p v-else-if="overzichtFout" class="rounded-lg border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive">
                        {{ overzichtFout }}
                    </p>

                    <div v-else-if="overzicht" class="overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
                        <div v-if="opWachtlijst" class="border-b border-border bg-warning/10 px-4 py-3 text-sm">
                            Dit aanbod zit vol. Je komt op de <span class="font-medium">wachtlijst</span>; er wordt nu niets in rekening gebracht.
                        </div>

                        <template v-else>
                            <div
                                v-for="(regel, i) in overzicht.lines"
                                :key="i"
                                class="flex items-start justify-between gap-3 border-b border-border px-4 py-3 text-sm"
                            >
                                <span class="min-w-0" :class="regel.amount_cents < 0 ? 'text-primary' : ''">{{ regel.description }}</span>
                                <span class="tabular shrink-0" :class="regel.amount_cents < 0 ? 'text-primary' : ''">{{ regel.amount }}</span>
                            </div>
                            <div
                                v-for="(r, i) in overzicht.recurring"
                                :key="'r' + i"
                                class="flex items-start justify-between gap-3 border-b border-border px-4 py-3 text-sm text-muted-foreground"
                            >
                                <span class="min-w-0">{{ r.description }}</span>
                                <span class="tabular shrink-0">{{ r.amount }}</span>
                            </div>
                            <div class="flex items-baseline justify-between gap-3 px-4 py-3">
                                <span class="font-semibold">{{ overzicht.recurring.length ? 'Nu te betalen' : 'Totaal' }}</span>
                                <span class="tabular text-lg font-bold">{{ overzicht.total }}</span>
                            </div>
                        </template>

                        <p v-if="overzicht.code && !overzicht.code.valid" class="border-t border-border px-4 py-2 text-xs text-warning">
                            {{ overzicht.code.message }}
                        </p>
                    </div>

                    <p class="text-sm text-muted-foreground">{{ betaalregel }}</p>

                    <div class="rounded-lg bg-secondary px-3 py-2 text-xs text-muted-foreground">
                        Annuleren kan kosteloos tot {{ config.policy.cancellation.free_until_days }} dagen voor de start; daarna houdt de school
                        {{ config.policy.cancellation.retain_percent }}% in.
                        <template v-if="config.policy.absence === 'none'">Bij ziekte of afwezigheid is er geen restitutie.</template>
                        <template v-else-if="config.policy.absence === 'makeup'">Bij ziekte kun je een training inhalen.</template>
                        <template v-else>Bij ziekte krijg je het deel van de gemiste training terug.</template>
                    </div>

                    <div class="rounded-2xl border border-border bg-card p-4 text-sm shadow-sm">
                        <p class="font-medium">{{ form.children.map((k) => k.first_name).join(' en ') }}</p>
                        <p class="text-muted-foreground">{{ gekozen?.name }} · {{ form.guardian_name }} · {{ form.guardian_email }}</p>
                    </div>
                </div>
            </template>
        </div>

        <!-- Vaste balk onderaan -->
        <div v-if="!submitted && stap !== 'aanbod'" class="fixed inset-x-0 bottom-0 z-30 border-t border-border bg-card/95 backdrop-blur">
            <div class="mx-auto flex w-full max-w-lg items-center justify-between gap-3 p-4">
                <button
                    type="button"
                    class="inline-flex h-11 items-center gap-2 rounded-xl border border-border px-4 text-sm font-medium"
                    @click="vorige"
                >
                    <ArrowLeft class="size-4" />
                    Vorige
                </button>

                <button
                    v-if="stap !== 'overzicht'"
                    type="button"
                    :disabled="!kanVerder"
                    class="inline-flex h-11 items-center gap-2 rounded-xl bg-primary px-5 text-sm font-semibold text-primary-foreground transition hover:opacity-90 disabled:opacity-50"
                    @click="verder"
                >
                    Verder
                    <ArrowRight class="size-4" />
                </button>
                <button
                    v-else
                    type="button"
                    :disabled="form.processing || overzichtLaadt || !!overzichtFout"
                    class="inline-flex h-11 items-center gap-2 rounded-xl bg-primary px-5 text-sm font-semibold text-primary-foreground transition hover:opacity-90 disabled:opacity-50"
                    @click="verstuur"
                >
                    <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                    <Check v-else class="size-4" />
                    {{ opWachtlijst ? 'Op de wachtlijst' : 'Bevestigen' }}
                </button>
            </div>
        </div>
    </div>
</template>
