<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, useForm } from '@inertiajs/vue3';
import { CalendarRange, CheckCircle2, LoaderCircle, MapPin, Ticket, Users } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Aanbod {
    id: number;
    name: string;
    description: string | null;
    type: string;
    amount: string;
    is_free: boolean;
    billing: string;
    is_subscription: boolean;
    interval: string;
    starts_on: string | null;
    ends_on: string | null;
    location: string | null;
    min_age: number | null;
    max_age: number | null;
    credits: number | null;
    spots_left: number | null;
    is_full: boolean;
}

const props = defineProps<{
    school: { name: string; slug: string };
    products: Aanbod[];
    selected: number | null;
    positions: Record<string, string>;
    paymentOptions: { value: string; label: string; hint: string; subscription_only: boolean }[];
    submitted: boolean;
    onWaitlist: boolean;
}>();

const form = useForm({
    first_name: '',
    last_name: '',
    date_of_birth: '',
    position: 'keeper',
    guardian_name: '',
    guardian_email: '',
    guardian_phone: '',
    relationship: '',
    // Een school kan naast elk programma op haar eigen site een knop zetten die
    // hierheen wijst; dan staat de keuze al goed.
    product_id: props.selected ?? props.products[0]?.id ?? null,
    payment_method: props.paymentOptions[0]?.value ?? null,
    note: '',
    privacy: false,
});

const gekozen = computed(() => props.products.find((p) => p.id === form.product_id) ?? null);

// Incasso hoort bij iets dat doorloopt. Bij een kamp of een losse training is
// "elke termijn afschrijven" een belofte over een termijn die niet bestaat.
const betaalkeuzes = computed(() => props.paymentOptions.filter((optie) => !optie.subscription_only || gekozen.value?.is_subscription));

watch(betaalkeuzes, (keuzes) => {
    if (!keuzes.some((optie) => optie.value === form.payment_method)) {
        form.payment_method = keuzes[0]?.value ?? null;
    }
});

// Eerst kiezen, dan pas gegevens invullen. Andersom vraag je de geboortedatum
// van een kind voordat iemand weet of er iets bij zit.
const stap = ref<'aanbod' | 'gegevens'>(props.selected ? 'gegevens' : 'aanbod');

const kies = (aanbod: Aanbod) => {
    form.product_id = aanbod.id;
    stap.value = 'gegevens';
};

const periode = (aanbod: Aanbod) => {
    if (!aanbod.starts_on) {
        return null;
    }

    return aanbod.ends_on && aanbod.ends_on !== aanbod.starts_on ? aanbod.starts_on + ' t/m ' + aanbod.ends_on : aanbod.starts_on;
};

const leeftijd = (aanbod: Aanbod) => {
    if (aanbod.min_age && aanbod.max_age) {
        return aanbod.min_age + ' t/m ' + aanbod.max_age + ' jaar';
    }

    if (aanbod.min_age) {
        return 'vanaf ' + aanbod.min_age + ' jaar';
    }

    return aanbod.max_age ? 't/m ' + aanbod.max_age + ' jaar' : null;
};

// Wat je betaalt en wanneer, in gewone taal. Een bedrag zonder "wanneer" laat
// een ouder gokken of er vanavond iets van zijn rekening gaat.
const betaalregel = computed(() => {
    const aanbod = gekozen.value;

    if (aanbod === null || aanbod.is_free) {
        return null;
    }

    if (aanbod.is_full) {
        return 'Je betaalt pas als er een plek vrijkomt.';
    }

    const gekozenMethode = betaalkeuzes.value.find((optie) => optie.value === form.payment_method);

    if (gekozenMethode?.value === 'cash') {
        return 'Je rekent ' + aanbod.amount + ' af bij de school.';
    }

    if (gekozenMethode?.value === 'directdebit') {
        return 'De eerste betaling doe je zelf; daarna wordt ' + aanbod.amount + ' elke termijn afgeschreven.';
    }

    return 'Zodra de school je inschrijving goedkeurt, krijg je een betaallink van ' + aanbod.amount + '.';
});

const verstuur = () => form.post('/inschrijven/' + props.school.slug);
</script>

<template>
    <Head :title="'Inschrijven bij ' + school.name" />

    <!-- Licht, net als de rest van de werkvloer. Dit is de eerste pagina die
         een ouder van de school ziet, vaak in een iframe op haar eigen site;
         een donker vlak in een lichte website valt daar uit de toon. -->
    <div class="min-h-svh bg-background px-4 py-8 text-foreground sm:py-12">
        <div class="mx-auto w-full max-w-lg">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-xl bg-primary text-primary-foreground">
                    <AppLogoIcon class="size-6" />
                </div>
                <div class="min-w-0">
                    <p class="text-xs uppercase tracking-widest text-muted-foreground">Inschrijven bij</p>
                    <p class="truncate text-lg font-semibold">{{ school.name }}</p>
                </div>
            </div>

            <!-- Verstuurd -->
            <div v-if="submitted" class="mt-8 rounded-2xl border border-primary/40 bg-primary/10 p-6 text-center">
                <CheckCircle2 class="mx-auto size-8 text-primary" />
                <template v-if="onWaitlist">
                    <p class="mt-3 font-semibold">Je staat op de wachtlijst</p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Het zat vol. Zodra er een plek vrijkomt, hoor je het van {{ school.name }} — en pas dan betaal je.
                    </p>
                </template>
                <template v-else>
                    <p class="mt-3 font-semibold">Je inschrijving is binnen</p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ school.name }} kijkt ernaar en neemt contact met je op. Je hoort van ons hoe en wanneer je betaalt.
                    </p>
                </template>
            </div>

            <template v-else>
                <!-- Stap 1: waar schrijf je je voor in -->
                <template v-if="stap === 'aanbod'">
                    <h1 class="mt-8 text-2xl font-semibold tracking-tight">Waar wil je je voor inschrijven?</h1>

                    <div v-if="products.length" class="mt-4 space-y-3">
                        <article v-for="aanbod in products" :key="aanbod.id" class="rounded-2xl border border-border bg-card p-4">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs uppercase tracking-wide text-muted-foreground">{{ aanbod.type }}</p>
                                    <p class="font-semibold">{{ aanbod.name }}</p>
                                </div>
                                <p class="tabular shrink-0 text-right">
                                    <span class="text-lg font-bold">{{ aanbod.is_free ? 'gratis' : aanbod.amount }}</span>
                                    <span v-if="!aanbod.is_free" class="block text-xs text-muted-foreground">{{ aanbod.billing }}</span>
                                </p>
                            </div>

                            <p v-if="aanbod.description" class="mt-2 text-sm text-muted-foreground">{{ aanbod.description }}</p>

                            <div class="mt-3 space-y-1 text-sm text-muted-foreground">
                                <p v-if="periode(aanbod)" class="flex items-start gap-2">
                                    <CalendarRange class="mt-0.5 size-4 shrink-0" />
                                    <span>{{ periode(aanbod) }}</span>
                                </p>
                                <p v-if="aanbod.location" class="flex items-start gap-2">
                                    <MapPin class="mt-0.5 size-4 shrink-0" />
                                    <span>{{ aanbod.location }}</span>
                                </p>
                                <p v-if="leeftijd(aanbod)" class="flex items-start gap-2">
                                    <Users class="mt-0.5 size-4 shrink-0" />
                                    <span>{{ leeftijd(aanbod) }}</span>
                                </p>
                                <p v-if="aanbod.credits" class="flex items-start gap-2">
                                    <Ticket class="mt-0.5 size-4 shrink-0" />
                                    <span>{{ aanbod.credits }} beurten</span>
                                </p>
                            </div>

                            <!-- Hoeveel plek er nog is, wil je weten vóórdat je je
                                 gegevens invult. Vol is geen afwijzing: dan is er
                                 een wachtlijst. -->
                            <p v-if="aanbod.is_full" class="mt-3 inline-flex rounded-lg bg-warning/10 px-2 py-1 text-xs font-medium text-warning">
                                Vol — er is een wachtlijst
                            </p>
                            <p
                                v-else-if="aanbod.spots_left !== null"
                                class="mt-3 inline-flex rounded-lg px-2 py-1 text-xs font-medium"
                                :class="aanbod.spots_left <= 3 ? 'bg-warning/10 text-warning' : 'bg-primary/10 text-primary'"
                            >
                                <span v-if="aanbod.spots_left === 1">Nog 1 plek</span>
                                <span v-else>Nog {{ aanbod.spots_left }} plekken</span>
                            </p>

                            <Button class="mt-4 h-11 w-full" :variant="aanbod.is_full ? 'secondary' : 'default'" @click="kies(aanbod)">
                                {{ aanbod.is_full ? 'Zet me op de wachtlijst' : 'Dit wil ik' }}
                            </Button>
                        </article>
                    </div>

                    <div v-else class="mt-6 rounded-2xl border border-dashed border-border bg-card/50 p-8 text-center">
                        <p class="font-medium">Er staat op dit moment niets open</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Neem contact op met {{ school.name }}; zodra er weer een training of kamp start, staat het hier.
                        </p>
                    </div>
                </template>

                <!-- Stap 2: de gegevens -->
                <form v-else class="mt-8 space-y-5" @submit.prevent="verstuur">
                    <div v-if="gekozen" class="rounded-2xl border border-primary/40 bg-primary/5 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-xs uppercase tracking-wide text-muted-foreground">Je schrijft in voor</p>
                                <p class="font-semibold">{{ gekozen.name }}</p>
                                <p v-if="periode(gekozen)" class="mt-0.5 text-xs text-muted-foreground">{{ periode(gekozen) }}</p>
                            </div>
                            <p class="tabular shrink-0 text-right">
                                <span class="font-bold">{{ gekozen.is_free ? 'gratis' : gekozen.amount }}</span>
                                <span v-if="!gekozen.is_free" class="block text-xs text-muted-foreground">{{ gekozen.billing }}</span>
                            </p>
                        </div>
                        <!-- Wat je betaalt en waarvoor, vóór het invullen. -->
                        <dl v-if="!gekozen.is_free" class="mt-3 space-y-1 border-t border-border pt-3 text-sm">
                            <div class="flex items-baseline justify-between gap-3">
                                <dt class="text-muted-foreground">Wat het kost</dt>
                                <dd class="tabular font-semibold">{{ gekozen.amount }} {{ gekozen.billing }}</dd>
                            </div>
                            <div v-if="gekozen.is_subscription" class="flex items-baseline justify-between gap-3">
                                <dt class="text-muted-foreground">Hoe vaak</dt>
                                <dd>
                                    {{ gekozen.interval }}<template v-if="gekozen.ends_on">, tot {{ gekozen.ends_on }}</template>
                                </dd>
                            </div>
                        </dl>

                        <p v-if="betaalregel" class="mt-2 text-xs text-muted-foreground">{{ betaalregel }}</p>

                        <p v-if="gekozen.is_free" class="mt-3 rounded-lg bg-primary/10 p-3 text-sm text-primary">
                            Dit kost niets. Er komt dus ook geen rekening.
                        </p>

                        <p v-if="gekozen.is_full" class="mt-3 rounded-lg bg-warning/10 p-3 text-sm text-warning">
                            Dit zit vol. Je komt op de wachtlijst en betaalt pas als er een plek vrijkomt.
                        </p>

                        <button
                            v-if="products.length > 1"
                            type="button"
                            class="mt-3 text-sm text-muted-foreground underline underline-offset-4"
                            @click="stap = 'aanbod'"
                        >
                            Iets anders kiezen
                        </button>
                        <InputError class="mt-2" :message="form.errors.product_id" />
                    </div>

                    <section class="rounded-2xl border border-border bg-card p-5">
                        <p class="font-semibold">Je kind</p>

                        <div class="mt-4 grid gap-4">
                            <div class="grid gap-2">
                                <Label for="first_name">Voornaam</Label>
                                <Input id="first_name" v-model="form.first_name" required class="h-11" />
                                <InputError :message="form.errors.first_name" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="last_name">Achternaam</Label>
                                <Input id="last_name" v-model="form.last_name" required class="h-11" />
                                <InputError :message="form.errors.last_name" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="date_of_birth">Geboortedatum</Label>
                                <input
                                    id="date_of_birth"
                                    v-model="form.date_of_birth"
                                    type="date"
                                    required
                                    class="h-11 w-full rounded-lg border border-input bg-background px-3 text-base outline-none focus:border-primary"
                                />
                                <InputError :message="form.errors.date_of_birth" />
                            </div>
                            <div class="grid gap-2">
                                <Label>Positie</Label>
                                <div class="grid grid-cols-2 gap-2">
                                    <button
                                        v-for="(label, waarde) in positions"
                                        :key="waarde"
                                        type="button"
                                        class="h-11 rounded-lg border text-sm font-medium transition"
                                        :class="
                                            form.position === waarde
                                                ? 'border-primary bg-primary/15 text-primary'
                                                : 'border-border text-muted-foreground'
                                        "
                                        @click="form.position = waarde"
                                    >
                                        {{ label }}
                                    </button>
                                </div>
                                <InputError :message="form.errors.position" />
                            </div>
                        </div>
                    </section>

                    <section class="rounded-2xl border border-border bg-card p-5">
                        <p class="font-semibold">Jijzelf</p>
                        <p class="mt-0.5 text-xs text-muted-foreground">Met dit e-mailadres log je straks in om alles van je kind te volgen.</p>

                        <div class="mt-4 grid gap-4">
                            <div class="grid gap-2">
                                <Label for="guardian_name">Naam</Label>
                                <Input id="guardian_name" v-model="form.guardian_name" required class="h-11" />
                                <InputError :message="form.errors.guardian_name" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="relationship">Relatie <span class="text-muted-foreground">(optioneel)</span></Label>
                                <Input id="relationship" v-model="form.relationship" placeholder="moeder, vader, verzorger" class="h-11" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="guardian_email">E-mailadres</Label>
                                <Input id="guardian_email" v-model="form.guardian_email" type="email" required autocomplete="email" class="h-11" />
                                <InputError :message="form.errors.guardian_email" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="guardian_phone">Telefoon <span class="text-muted-foreground">(optioneel)</span></Label>
                                <Input id="guardian_phone" v-model="form.guardian_phone" type="tel" autocomplete="tel" class="h-11" />
                                <InputError :message="form.errors.guardian_phone" />
                            </div>
                        </div>
                    </section>

                    <section v-if="betaalkeuzes.length" class="rounded-2xl border border-border bg-card p-5">
                        <p class="font-semibold">Hoe wil je betalen?</p>

                        <div class="mt-4 grid gap-2">
                            <button
                                v-for="optie in betaalkeuzes"
                                :key="optie.value"
                                type="button"
                                class="flex min-h-11 w-full items-start gap-3 rounded-xl border p-3 text-left transition"
                                :class="form.payment_method === optie.value ? 'border-primary bg-primary/10' : 'border-border'"
                                @click="form.payment_method = optie.value"
                            >
                                <span
                                    class="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-full border"
                                    :class="form.payment_method === optie.value ? 'border-primary' : 'border-muted-foreground'"
                                >
                                    <span v-if="form.payment_method === optie.value" class="size-2 rounded-full bg-primary"></span>
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-medium">{{ optie.label }}</span>
                                    <span class="block text-xs text-muted-foreground">{{ optie.hint }}</span>
                                </span>
                            </button>
                        </div>
                        <InputError class="mt-2" :message="form.errors.payment_method" />
                    </section>

                    <section class="rounded-2xl border border-border bg-card p-5">
                        <Label for="note">Opmerking <span class="text-muted-foreground">(optioneel)</span></Label>
                        <textarea
                            id="note"
                            v-model="form.note"
                            rows="3"
                            class="mt-2 w-full rounded-lg border border-input bg-background p-3 text-base outline-none focus:border-primary"
                            placeholder="Bijvoorbeeld: speelt al bij een club, of wil graag op zaterdag trainen."
                        ></textarea>

                        <label class="mt-4 flex items-start gap-3 text-sm">
                            <input v-model="form.privacy" type="checkbox" class="mt-0.5 size-4 shrink-0 rounded border-input accent-primary" />
                            <span class="text-muted-foreground">
                                Ik ga ermee akkoord dat {{ school.name }} deze gegevens gebruikt om de training te regelen.
                            </span>
                        </label>
                        <InputError class="mt-2" :message="form.errors.privacy" />
                    </section>

                    <Button type="submit" class="h-12 w-full text-base" :disabled="form.processing">
                        <LoaderCircle v-if="form.processing" class="mr-2 size-4 animate-spin" />
                        {{ gekozen?.is_full ? 'Op de wachtlijst' : 'Inschrijven' }}
                    </Button>

                    <p class="text-center text-xs text-muted-foreground">
                        Je zit nergens aan vast: {{ school.name }} bevestigt je inschrijving eerst.
                    </p>
                </form>
            </template>
        </div>
    </div>
</template>
