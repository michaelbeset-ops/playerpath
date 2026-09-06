<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';

interface Soort {
    value: string;
    label: string;
    description: string;
    needs_interval: boolean;
    needs_credits: boolean;
}

const props = defineProps<{
    product: {
        id: number;
        name: string;
        description: string | null;
        type: string;
        amount: string;
        vat_rate: number;
        credits: number | null;
        validity_months: number | null;
        interval: string | null;
        is_active: boolean;
    } | null;
    types: Soort[];
    intervals: Record<string, string>;
}>();

const bewerken = computed(() => props.product !== null);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Producten', href: '/products' },
    bewerken.value
        ? { title: props.product!.name, href: '/products/' + props.product!.id + '/edit' }
        : { title: 'Nieuw product', href: '/products/create' },
]);

const form = useForm({
    name: props.product?.name ?? '',
    description: props.product?.description ?? '',
    type: props.product?.type ?? 'abonnement',
    amount: props.product?.amount ?? '',
    vat_rate: props.product?.vat_rate ?? 21,
    credits: props.product?.credits ?? null,
    validity_months: props.product?.validity_months ?? null,
    interval: props.product?.interval ?? 'monthly',
    is_active: props.product?.is_active ?? true,
});

// Het soort bepaalt welke velden er staan. Een rittenkaart met een
// maandfrequentie is een veld dat later niemand meer snapt.
const soort = computed(() => props.types.find((t) => t.value === form.type) ?? props.types[0]);

const opslaan = () => (bewerken.value ? form.put('/products/' + props.product!.id) : form.post('/products'));

const verwijderen = () => {
    if (confirm('Dit product verwijderen? Wat er al is afgenomen loopt gewoon door met het eigen bedrag.')) {
        router.delete('/products/' + props.product!.id);
    }
};

const btwTarieven = [
    { waarde: 21, label: '21% — algemeen tarief' },
    { waarde: 9, label: '9% — laag tarief, geldt vaak voor sportlessen' },
    { waarde: 0, label: '0% — vrijgesteld' },
];
</script>

<template>
    <Head :title="bewerken ? 'Product bewerken' : 'Product toevoegen'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl p-4">
            <Link href="/products" class="text-sm text-muted-foreground underline underline-offset-4">Terug naar producten</Link>

            <h1 class="mt-2 text-2xl font-semibold tracking-tight">
                {{ bewerken ? product!.name : 'Nieuw product' }}
            </h1>

            <form class="mt-6 space-y-6" @submit.prevent="opslaan">
                <div class="rounded-xl border border-border bg-card p-5 shadow-sm">
                    <p class="font-medium">Wat voor product is dit?</p>

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

                <div class="space-y-4 rounded-xl border border-border bg-card p-5 shadow-sm">
                    <div class="grid gap-2">
                        <Label for="name">Naam</Label>
                        <Input id="name" v-model="form.name" required placeholder="10-rittenkaart" />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="description">Omschrijving <span class="text-muted-foreground">(optioneel)</span></Label>
                        <Input id="description" v-model="form.description" placeholder="Voor wie af en toe komt" />
                        <InputError :message="form.errors.description" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="amount">Prijs</Label>
                            <div class="flex items-center gap-2">
                                <span class="text-muted-foreground">€</span>
                                <Input id="amount" v-model="form.amount" required inputmode="decimal" placeholder="110,00" />
                            </div>
                            <p class="text-xs text-muted-foreground">Wat de ouder betaalt, dus inclusief btw.</p>
                            <InputError :message="form.errors.amount" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="vat_rate">Btw</Label>
                            <select
                                id="vat_rate"
                                v-model.number="form.vat_rate"
                                class="h-10 rounded-lg border border-input bg-background px-3 text-base outline-none focus:border-primary sm:text-sm"
                            >
                                <option v-for="tarief in btwTarieven" :key="tarief.waarde" :value="tarief.waarde">{{ tarief.label }}</option>
                            </select>
                            <InputError :message="form.errors.vat_rate" />
                        </div>
                    </div>

                    <div v-if="soort?.needs_interval" class="grid gap-2">
                        <Label for="interval">Hoe vaak in rekening brengen</Label>
                        <select
                            id="interval"
                            v-model="form.interval"
                            class="h-10 rounded-lg border border-input bg-background px-3 text-base outline-none focus:border-primary sm:text-sm"
                        >
                            <option v-for="(label, waarde) in intervals" :key="waarde" :value="waarde">{{ label }}</option>
                        </select>
                        <InputError :message="form.errors.interval" />
                    </div>

                    <div v-if="soort?.needs_credits" class="grid gap-2">
                        <Label for="credits">Aantal beurten</Label>
                        <Input id="credits" v-model.number="form.credits" type="number" min="1" max="500" placeholder="10" />
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
                                class="max-w-24"
                                placeholder="6"
                            />
                            <span class="text-sm text-muted-foreground">maanden na aanschaf</span>
                        </div>
                        <p class="text-xs text-muted-foreground">Laat leeg als het niet verloopt.</p>
                        <InputError :message="form.errors.validity_months" />
                    </div>

                    <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-border p-3">
                        <input v-model="form.is_active" type="checkbox" class="size-4 shrink-0 rounded border-input accent-primary" />
                        <span class="text-sm">
                            <span class="block font-medium">Te koop</span>
                            <span class="block text-xs text-muted-foreground">
                                Uitgezet blijft het bestaan voor wie het al heeft, maar je kunt het niet meer toekennen.
                            </span>
                        </span>
                    </label>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <Button type="submit" :disabled="form.processing">
                        <LoaderCircle v-if="form.processing" class="mr-2 size-4 animate-spin" />
                        {{ bewerken ? 'Opslaan' : 'Product aanmaken' }}
                    </Button>

                    <Link href="/products" class="text-sm text-muted-foreground underline underline-offset-4">Annuleren</Link>

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
