<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Image as ImageIcon, LoaderCircle, Plus, Trash2, X } from 'lucide-vue-next';
import { computed } from 'vue';

interface Soort {
    value: string;
    label: string;
    description: string;
    needs_interval: boolean;
    needs_credits: boolean;
    has_period: boolean;
    has_schedule: boolean;
    has_capacity: boolean;
}

const props = defineProps<{
    product: {
        id: number;
        name: string;
        description: string | null;
        type: string;
        billing_type: string;
        amount: string;
        vat_rate: number;
        credits: number | null;
        validity_months: number | null;
        interval: string | null;
        starts_on: string | null;
        ends_on: string | null;
        capacity: number | null;
        min_participants: number | null;
        min_age: number | null;
        max_age: number | null;
        audience: string;
        image: string | null;
        sessions_count: number | null;
        payment_options: Betaalvorm[];
        location: string | null;
        location_id: number | null;
        status: string;
        stops_at_end: boolean;
        is_active: boolean;
        trainers: number[];
        group_id: number | null;
        trainings_count: number;
        weekdays: number[];
        dates: string[];
        time_from: string;
        time_to: string;
    } | null;
    defaultPaymentOptions?: Betaalvorm[];
    types: Soort[];
    intervals: Record<string, string>;
    billingTypes: Record<string, string>;
    audiences: Record<string, string>;
    paymentOptionTypes: Record<string, string>;
    statuses: Record<string, string>;
    locations: { id: number; name: string }[];
    availableTrainers: { id: number; name: string }[];
}>();

type Betaalvorm = {
    type: string;
    amount: string;
    installments: number | null;
    interval: string | null;
    label: string | null;
};

const bewerken = computed(() => props.product !== null);

// De afbeelding gaat apart: kiezen is opslaan, zoals bij een profielfoto.
const uploadAfbeelding = (event: Event) => {
    const bestand = (event.target as HTMLInputElement).files?.[0];

    if (bestand && props.product) {
        router.post('/aanbod/' + props.product.id + '/foto', { photo: bestand }, { forceFormData: true, preserveScroll: true });
    }
};

const verwijderAfbeelding = () => {
    if (props.product && confirm('De afbeelding weghalen?')) {
        router.delete('/aanbod/' + props.product.id + '/foto', { preserveScroll: true });
    }
};

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Aanbod', href: '/aanbod' },
    bewerken.value
        ? { title: props.product!.name, href: '/aanbod/' + props.product!.id + '/edit' }
        : { title: 'Nieuw aanbod', href: '/aanbod/create' },
]);

const form = useForm({
    name: props.product?.name ?? '',
    description: props.product?.description ?? '',
    type: props.product?.type ?? 'blok',
    billing_type: props.product?.billing_type ?? 'eenmalig',
    amount: props.product?.amount ?? '',
    vat_rate: props.product?.vat_rate ?? 21,
    credits: props.product?.credits ?? null,
    validity_months: props.product?.validity_months ?? null,
    interval: props.product?.interval ?? 'monthly',
    starts_on: props.product?.starts_on ?? '',
    ends_on: props.product?.ends_on ?? '',
    capacity: props.product?.capacity ?? null,
    min_participants: props.product?.min_participants ?? null,
    min_age: props.product?.min_age ?? null,
    max_age: props.product?.max_age ?? null,
    audience: props.product?.audience ?? 'all',
    sessions_count: props.product?.sessions_count ?? null,
    // Nieuw aanbod: de betaalvormen uit de wizard staan er alvast; het bedrag vul je in.
    payment_options: [...(props.product?.payment_options ?? props.defaultPaymentOptions ?? [])] as Betaalvorm[],
    location_id: props.product?.location_id ?? null,
    status: props.product?.status ?? 'open',
    stops_at_end: props.product?.stops_at_end ?? true,
    is_active: props.product?.is_active ?? true,
    trainers: props.product?.trainers ?? ([] as number[]),
    // Het rooster. Een blok herhaalt wekelijks; een kamp heeft losse dagen.
    weekdays: (props.product?.weekdays ?? []) as number[],
    dates: (props.product?.dates ?? []) as string[],
    starts_at: props.product?.time_from ?? '18:00',
    ends_at: props.product?.time_to ?? '19:30',
});

// Het soort bepaalt welke velden er staan. Een rittenkaart met een einddatum is
// een veld dat later niemand meer snapt.
const soort = computed(() => props.types.find((t) => t.value === form.type) ?? props.types[0]);

const perMaand = computed(() => form.billing_type === 'maandelijks');

// Extra betaalvormen naast de standaard: "of 3 × € 40", "of € 30 per maand".
const voegBetaalvormToe = () => form.payment_options.push({ type: 'termijnen', amount: '', installments: 3, interval: 'month', label: null });
const verwijderBetaalvorm = (i: number) => form.payment_options.splice(i, 1);

// Een kamp gaat over losse dagen; een blok over elke week dezelfde dag.
const losseDagen = computed(() => form.type === 'kamp');

const dagen = [
    { waarde: 1, label: 'ma' },
    { waarde: 2, label: 'di' },
    { waarde: 3, label: 'wo' },
    { waarde: 4, label: 'do' },
    { waarde: 5, label: 'vr' },
    { waarde: 6, label: 'za' },
    { waarde: 0, label: 'zo' },
];

const wisselDag = (waarde: number) => {
    const positie = form.weekdays.indexOf(waarde);

    if (positie === -1) {
        form.weekdays.push(waarde);
    } else {
        form.weekdays.splice(positie, 1);
    }
};

const wisselTrainer = (id: number) => {
    const positie = form.trainers.indexOf(id);

    if (positie === -1) {
        form.trainers.push(id);
    } else {
        form.trainers.splice(positie, 1);
    }
};

const voegDagToe = () => form.dates.push(form.starts_on || '');
const haalDagWeg = (index: number) => form.dates.splice(index, 1);

const opslaan = () => (bewerken.value ? form.put('/aanbod/' + props.product!.id) : form.post('/aanbod'));

const verwijderen = () => {
    if (confirm('Dit aanbod verwijderen? Wat er al is afgenomen loopt gewoon door met het eigen bedrag.')) {
        router.delete('/aanbod/' + props.product!.id);
    }
};

const btwTarieven = [
    { waarde: 21, label: '21% - algemeen tarief' },
    { waarde: 9, label: '9% - laag tarief, geldt vaak voor sportlessen' },
    { waarde: 0, label: '0% - vrijgesteld' },
];

const veldKlassen = 'h-11 w-full rounded-lg border border-input bg-background px-3 text-base outline-none focus:border-primary sm:text-sm';
</script>

<template>
    <Head :title="bewerken ? 'Aanbod bewerken' : 'Aanbod toevoegen'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl p-4">
            <Link href="/aanbod" class="inline-flex min-h-11 items-center text-sm text-muted-foreground underline underline-offset-4">Terug naar het aanbod</Link>

            <h1 class="mt-2 text-2xl font-semibold tracking-tight">
                {{ bewerken ? product!.name : 'Nieuw aanbod' }}
            </h1>

            <form class="mt-6 space-y-6" @submit.prevent="opslaan">
                <div class="rounded-xl border border-border bg-card p-5 shadow-sm">
                    <p class="font-medium">Wat bied je aan?</p>

                    <div class="mt-4 space-y-2">
                        <label
                            v-for="type in types"
                            :key="type.value"
                            class="flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition"
                            :class="form.type === type.value ? 'border-primary bg-primary/5' : 'border-border hover:border-primary/40'"
                        >
                            <input v-model="form.type" type="radio" :value="type.value" class="mt-0.5 size-4 shrink-0 accent-primary" />
                            <span class="min-w-0">
                                <span class="block text-sm font-medium">{{ type.label }}</span>
                                <span class="block text-xs text-muted-foreground">{{ type.description }}</span>
                            </span>
                        </label>
                    </div>
                    <InputError class="mt-2" :message="form.errors.type" />
                </div>

                <!-- Naam, omschrijving, locatie -->
                <div class="space-y-4 rounded-xl border border-border bg-card p-5 shadow-sm">
                    <div class="grid gap-2">
                        <Label for="name">Naam</Label>
                        <Input id="name" v-model="form.name" required placeholder="Keepersblok najaar" class="h-11 sm:h-10" />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="description">Omschrijving <span class="text-muted-foreground">(optioneel)</span></Label>
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="3"
                            class="w-full rounded-lg border border-input bg-background p-3 text-base outline-none focus:border-primary sm:text-sm"
                            placeholder="Zes weken keeperstraining, met wedstrijdvormen en video."
                        ></textarea>
                        <p class="text-xs text-muted-foreground">Dit leest een ouder op de aanmeldpagina.</p>
                    </div>

                    <!-- Een afbeelding, optioneel. Pas na het opslaan: dan is er iets om hem aan te hangen. -->
                    <div class="grid gap-2">
                        <Label>Afbeelding <span class="text-muted-foreground">(optioneel)</span></Label>
                        <template v-if="bewerken">
                            <img v-if="product!.image" :src="product!.image" alt="" class="h-32 w-full rounded-lg object-cover" />
                            <div class="flex flex-wrap items-center gap-3">
                                <label
                                    class="inline-flex h-11 cursor-pointer items-center gap-2 rounded-lg border border-border bg-card px-3 text-sm font-medium hover:border-primary sm:h-10"
                                >
                                    <ImageIcon class="size-4" />
                                    {{ product!.image ? 'Andere afbeelding' : 'Afbeelding kiezen' }}
                                    <input type="file" accept="image/png,image/jpeg,image/webp" class="hidden" @change="uploadAfbeelding" />
                                </label>
                                <button
                                    v-if="product!.image"
                                    type="button"
                                    class="text-sm text-muted-foreground underline underline-offset-4 hover:text-destructive"
                                    @click="verwijderAfbeelding"
                                >
                                    Weghalen
                                </button>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Liggend werkt het best; hij staat in de shop en op de inschrijfpagina. png, jpg of webp, tot 5 MB.
                            </p>
                            <InputError :message="(form.errors as any).photo" />
                        </template>
                        <p v-else class="text-xs text-muted-foreground">Sla het aanbod eerst op; daarna kun je hier een afbeelding kiezen.</p>
                        <InputError :message="form.errors.description" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="location_id">Locatie <span class="text-muted-foreground">(optioneel)</span></Label>
                        <select id="location_id" v-model="form.location_id" :class="veldKlassen">
                            <option :value="null">Nog niet bekend</option>
                            <option v-for="locatie in locations" :key="locatie.id" :value="locatie.id">{{ locatie.name }}</option>
                        </select>
                        <p v-if="!locations.length" class="text-xs text-muted-foreground">
                            Je hebt nog geen locaties. Zet ze neer bij Mijn bedrijf → Locaties.
                        </p>
                        <InputError :message="form.errors.location_id" />
                    </div>
                </div>

                <!-- Wanneer, en het rooster -->
                <div v-if="soort?.has_schedule" class="space-y-4 rounded-xl border border-border bg-card p-5 shadow-sm">
                    <p class="font-medium">Wanneer</p>
                    <p v-if="!soort?.has_period" class="text-sm text-muted-foreground">
                        Een vast ritme, bijvoorbeeld elke woensdag om 18:00. Wie dit abonnement afneemt komt vanzelf in de groep en staat op al deze
                        trainingen; de app plant twaalf weken vooruit en legt er elke nacht een dag bij.
                    </p>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="starts_on">Startdatum <span v-if="!soort?.has_period" class="text-muted-foreground">(optioneel)</span></Label>
                            <input id="starts_on" v-model="form.starts_on" type="date" :class="veldKlassen" />
                            <InputError :message="form.errors.starts_on" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="ends_on">Einddatum <span v-if="!soort?.has_period" class="text-muted-foreground">(optioneel)</span></Label>
                            <input id="ends_on" v-model="form.ends_on" type="date" :class="veldKlassen" />
                            <InputError :message="form.errors.ends_on" />
                        </div>
                    </div>

                    <!-- Uit de data en de dagen komen echte trainingen. Daardoor
                         werken aanwezigheid en rapporten net als altijd. -->
                    <div v-if="!losseDagen" class="grid gap-2">
                        <Label>Elke week op</Label>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="dag in dagen"
                                :key="dag.waarde"
                                type="button"
                                class="h-11 min-w-11 rounded-lg border px-3 text-sm font-medium transition"
                                :class="
                                    form.weekdays.includes(dag.waarde)
                                        ? 'border-transparent bg-primary text-primary-foreground'
                                        : 'border-border text-muted-foreground hover:border-primary'
                                "
                                @click="wisselDag(dag.waarde)"
                            >
                                {{ dag.label }}
                            </button>
                        </div>
                        <InputError :message="form.errors.weekdays" />
                    </div>

                    <div v-else class="grid gap-2">
                        <Label>Dagen</Label>
                        <div v-for="(dag, index) in form.dates" :key="index" class="flex items-center gap-2">
                            <input v-model="form.dates[index]" type="date" :class="veldKlassen" />
                            <button
                                type="button"
                                class="flex size-11 shrink-0 items-center justify-center rounded-lg border border-border text-muted-foreground transition hover:border-destructive hover:text-destructive"
                                aria-label="Dag weghalen"
                                @click="haalDagWeg(index)"
                            >
                                <X class="size-4" />
                            </button>
                        </div>
                        <button
                            type="button"
                            class="inline-flex h-11 items-center gap-2 self-start rounded-lg border border-border px-3 text-sm font-medium transition hover:border-primary"
                            @click="voegDagToe"
                        >
                            <Plus class="size-4" />
                            Dag toevoegen
                        </button>
                        <InputError :message="form.errors.dates" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="starts_at">Van</Label>
                            <input id="starts_at" v-model="form.starts_at" type="time" :class="veldKlassen" />
                            <InputError :message="form.errors.starts_at" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="ends_at">Tot</Label>
                            <input id="ends_at" v-model="form.ends_at" type="time" :class="veldKlassen" />
                            <InputError :message="form.errors.ends_at" />
                        </div>
                    </div>

                    <p class="text-xs text-muted-foreground">
                        Hier maken we de trainingen van. Ze komen in de agenda en op de aanwezigheidslijst, net als elke andere training.
                        <template v-if="bewerken && product!.trainings_count">
                            Er staan er nu <span class="tabular">{{ product!.trainings_count }}</span
                            >. Trainingen die al geweest zijn blijven staan.
                        </template>
                    </p>
                </div>

                <!-- Voor wie -->
                <div class="space-y-4 rounded-xl border border-border bg-card p-5 shadow-sm">
                    <p class="font-medium">Voor wie</p>

                    <div class="grid gap-2">
                        <Label for="audience">Positie</Label>
                        <select id="audience" v-model="form.audience" :class="veldKlassen">
                            <option v-for="(label, waarde) in audiences" :key="waarde" :value="waarde">{{ label }}</option>
                        </select>
                        <p class="text-xs text-muted-foreground">Een keeperskamp is niet voor een spits. Het inschrijfformulier houdt dit aan.</p>
                        <InputError :message="form.errors.audience" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="min_age">Vanaf leeftijd <span class="text-muted-foreground">(optioneel)</span></Label>
                            <Input id="min_age" v-model.number="form.min_age" type="number" min="3" max="99" placeholder="5" class="h-11 sm:h-10" />
                            <InputError :message="form.errors.min_age" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="max_age">Tot en met <span class="text-muted-foreground">(optioneel)</span></Label>
                            <Input id="max_age" v-model.number="form.max_age" type="number" min="3" max="99" placeholder="12" class="h-11 sm:h-10" />
                            <InputError :message="form.errors.max_age" />
                        </div>
                    </div>

                    <div v-if="soort?.has_period" class="grid gap-2 sm:max-w-xs">
                        <Label for="sessions_count">Aantal sessies <span class="text-muted-foreground">(optioneel)</span></Label>
                        <Input
                            id="sessions_count"
                            v-model.number="form.sessions_count"
                            type="number"
                            min="1"
                            max="200"
                            placeholder="6"
                            class="h-11 sm:h-10"
                        />
                        <p class="text-xs text-muted-foreground">Hoeveel trainingen erin zitten; staat op het inschrijfformulier.</p>
                        <InputError :message="form.errors.sessions_count" />
                    </div>

                    <div v-if="soort?.has_capacity" class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="capacity">Aantal plekken <span class="text-muted-foreground">(optioneel)</span></Label>
                            <Input
                                id="capacity"
                                v-model.number="form.capacity"
                                type="number"
                                min="1"
                                max="500"
                                placeholder="12"
                                class="h-11 sm:h-10"
                            />
                            <p class="text-xs text-muted-foreground">Vol is vol: dan gaat de inschrijving vanzelf dicht.</p>
                            <InputError :message="form.errors.capacity" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="min_participants">Minimum <span class="text-muted-foreground">(optioneel)</span></Label>
                            <Input
                                id="min_participants"
                                v-model.number="form.min_participants"
                                type="number"
                                min="1"
                                max="500"
                                placeholder="6"
                                class="h-11 sm:h-10"
                            />
                            <p class="text-xs text-muted-foreground">Onder dit aantal gaat het niet door.</p>
                            <InputError :message="form.errors.min_participants" />
                        </div>
                    </div>

                    <div v-if="availableTrainers.length" class="grid gap-2">
                        <Label>Trainers <span class="text-muted-foreground">(optioneel)</span></Label>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="trainer in availableTrainers"
                                :key="trainer.id"
                                type="button"
                                class="h-11 rounded-lg border px-3 text-sm font-medium transition"
                                :class="
                                    form.trainers.includes(trainer.id)
                                        ? 'border-primary bg-primary/10 text-primary'
                                        : 'border-border text-muted-foreground hover:border-primary'
                                "
                                @click="wisselTrainer(trainer.id)"
                            >
                                {{ trainer.name }}
                            </button>
                        </div>
                        <p class="text-xs text-muted-foreground">Ze komen bij de trainingen te staan. Afvinken mag elke trainer.</p>
                        <InputError :message="form.errors.trainers" />
                    </div>
                </div>

                <!-- Prijs -->
                <div class="space-y-4 rounded-xl border border-border bg-card p-5 shadow-sm">
                    <p class="font-medium">Prijs</p>

                    <div class="grid gap-2">
                        <Label>Betaalwijze</Label>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <label
                                v-for="(label, waarde) in billingTypes"
                                :key="waarde"
                                class="flex cursor-pointer items-center gap-3 rounded-lg border p-3 transition"
                                :class="form.billing_type === waarde ? 'border-primary bg-primary/5' : 'border-border hover:border-primary/40'"
                            >
                                <input v-model="form.billing_type" type="radio" :value="waarde" class="size-4 shrink-0 accent-primary" />
                                <span class="text-sm font-medium">{{ label }}</span>
                            </label>
                        </div>
                        <InputError :message="form.errors.billing_type" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="amount">Bedrag</Label>
                            <div class="flex items-center gap-2">
                                <span class="text-muted-foreground">€</span>
                                <Input id="amount" v-model="form.amount" required inputmode="decimal" placeholder="120,00" class="h-11 sm:h-10" />
                            </div>
                            <p class="text-xs text-muted-foreground">Wat de ouder betaalt, dus inclusief btw.</p>
                            <InputError :message="form.errors.amount" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="vat_rate">Btw</Label>
                            <select id="vat_rate" v-model.number="form.vat_rate" :class="veldKlassen">
                                <option v-for="tarief in btwTarieven" :key="tarief.waarde" :value="tarief.waarde">{{ tarief.label }}</option>
                            </select>
                            <InputError :message="form.errors.vat_rate" />
                        </div>
                    </div>

                    <!-- Extra betaalvormen naast de standaard hierboven. -->
                    <div class="space-y-3 border-t border-border pt-4">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <p class="text-sm font-medium">Andere manieren om te betalen <span class="text-muted-foreground">(optioneel)</span></p>
                            <button type="button" class="inline-flex min-h-11 items-center text-sm font-medium text-primary underline underline-offset-4" @click="voegBetaalvormToe">
                                Betaalvorm toevoegen
                            </button>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Bijvoorbeeld: hetzelfde blok in drie termijnen, of per maand. De ouder kiest bij het inschrijven.
                        </p>

                        <div v-for="(optie, i) in form.payment_options" :key="i" class="rounded-lg border border-border p-3">
                            <div class="grid gap-3 sm:grid-cols-[1fr_auto]">
                                <div class="grid gap-3 sm:grid-cols-3">
                                    <div class="grid gap-1">
                                        <Label :for="'po_type_' + i" class="text-xs">Soort</Label>
                                        <select :id="'po_type_' + i" v-model="optie.type" :class="veldKlassen">
                                            <option v-for="(label, waarde) in paymentOptionTypes" :key="waarde" :value="waarde">{{ label }}</option>
                                        </select>
                                    </div>
                                    <div class="grid gap-1">
                                        <Label :for="'po_amount_' + i" class="text-xs">{{
                                            optie.type === 'termijnen' ? 'Per termijn' : 'Bedrag'
                                        }}</Label>
                                        <div class="flex items-center gap-2">
                                            <span class="text-muted-foreground">€</span>
                                            <Input
                                                :id="'po_amount_' + i"
                                                v-model="optie.amount"
                                                inputmode="decimal"
                                                placeholder="40,00"
                                                class="h-11 sm:h-10"
                                            />
                                        </div>
                                    </div>
                                    <div v-if="optie.type === 'termijnen'" class="grid gap-1">
                                        <Label :for="'po_inst_' + i" class="text-xs">Aantal termijnen</Label>
                                        <Input
                                            :id="'po_inst_' + i"
                                            v-model.number="optie.installments"
                                            type="number"
                                            min="2"
                                            max="12"
                                            class="h-11 sm:h-10"
                                        />
                                    </div>
                                    <div v-else-if="optie.type === 'abonnement'" class="grid gap-1">
                                        <Label :for="'po_int_' + i" class="text-xs">Hoe vaak</Label>
                                        <select :id="'po_int_' + i" v-model="optie.interval" :class="veldKlassen">
                                            <option v-for="(label, waarde) in intervals" :key="waarde" :value="waarde">{{ label }}</option>
                                        </select>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    class="self-end text-sm text-muted-foreground underline underline-offset-4 hover:text-destructive"
                                    @click="verwijderBetaalvorm(i)"
                                >
                                    Weghalen
                                </button>
                            </div>
                            <InputError
                                :message="
                                    (form.errors as any)['payment_options.' + i + '.amount'] ??
                                    (form.errors as any)['payment_options.' + i + '.installments']
                                "
                            />
                        </div>
                    </div>

                    <div v-if="perMaand" class="grid gap-2">
                        <Label for="interval">Hoe vaak in rekening brengen</Label>
                        <select id="interval" v-model="form.interval" :class="veldKlassen">
                            <option v-for="(label, waarde) in intervals" :key="waarde" :value="waarde">{{ label }}</option>
                        </select>
                        <InputError :message="form.errors.interval" />
                    </div>

                    <label v-if="perMaand && soort?.has_period" class="flex cursor-pointer items-start gap-3 rounded-lg border border-border p-3">
                        <input v-model="form.stops_at_end" type="checkbox" class="mt-0.5 size-4 shrink-0 rounded border-input accent-primary" />
                        <span class="text-sm">
                            <span class="block font-medium">Stopt vanzelf op de einddatum</span>
                            <span class="block text-xs text-muted-foreground"> Uit betekent dat het doorloopt tot iemand het stopt. </span>
                        </span>
                    </label>

                    <div v-if="soort?.needs_credits" class="grid gap-2">
                        <Label for="credits">Aantal beurten</Label>
                        <Input id="credits" v-model.number="form.credits" type="number" min="1" max="500" placeholder="10" class="h-11 sm:h-10" />
                        <p class="text-xs text-muted-foreground">
                            Er gaat een beurt af zodra een trainer de speler aanwezig meldt. Vergist hij zich, dan komt hij terug.
                        </p>
                        <InputError :message="form.errors.credits" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="validity_months">Geldig <span class="text-muted-foreground">(optioneel)</span></Label>
                        <div class="flex items-center gap-2">
                            <Input
                                id="validity_months"
                                v-model.number="form.validity_months"
                                type="number"
                                min="1"
                                max="120"
                                class="h-11 max-w-24 sm:h-10"
                                placeholder="6"
                            />
                            <span class="text-sm text-muted-foreground">maanden na aanschaf</span>
                        </div>
                        <p class="text-xs text-muted-foreground">Laat leeg als het niet verloopt.</p>
                        <InputError :message="form.errors.validity_months" />
                    </div>
                </div>

                <!-- Status -->
                <div class="space-y-4 rounded-xl border border-border bg-card p-5 shadow-sm">
                    <div class="grid gap-2">
                        <Label for="status">Inschrijving</Label>
                        <select id="status" v-model="form.status" :class="veldKlassen">
                            <option v-for="(label, waarde) in statuses" :key="waarde" :value="waarde">{{ label }}</option>
                        </select>
                        <p class="text-xs text-muted-foreground">
                            Vol hoef je niet zelf te zetten: zodra alle plekken bezet zijn gaat de inschrijving vanzelf dicht.
                        </p>
                        <InputError :message="form.errors.status" />
                    </div>

                    <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-border p-3">
                        <input v-model="form.is_active" type="checkbox" class="size-4 shrink-0 rounded border-input accent-primary" />
                        <span class="text-sm">
                            <span class="block font-medium">Zichtbaar</span>
                            <span class="block text-xs text-muted-foreground">
                                Uitgezet blijft het bestaan voor wie het al heeft, maar je kunt het niet meer toekennen.
                            </span>
                        </span>
                    </label>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <Button type="submit" class="h-11 sm:h-10" :disabled="form.processing">
                        <LoaderCircle v-if="form.processing" class="mr-2 size-4 animate-spin" />
                        {{ bewerken ? 'Opslaan' : 'Aanbod aanmaken' }}
                    </Button>

                    <Link href="/aanbod" class="inline-flex min-h-11 items-center text-sm text-muted-foreground underline underline-offset-4">Annuleren</Link>

                    <button
                        v-if="bewerken"
                        type="button"
                        class="ml-auto inline-flex items-center gap-2 text-sm text-muted-foreground transition hover:text-destructive"
                        @click="verwijderen"
                    >
                        <Trash2 class="size-4" />
                        Verwijderen
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
