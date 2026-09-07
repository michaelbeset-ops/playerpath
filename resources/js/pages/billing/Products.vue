<script setup lang="ts">
import FlashMessage from '@/components/FlashMessage.vue';
import GatewayNotice from '@/components/GatewayNotice.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { CalendarDays, CalendarRange, MapPin, Pencil, Plus, Tag, Tent, Ticket, User, Users } from 'lucide-vue-next';
import { computed, type Component } from 'vue';

interface ProductRij {
    id: number;
    name: string;
    description: string | null;
    type: string;
    type_label: string;
    billing_type: string;
    billing_label: string;
    amount: string;
    amount_excl_vat: string;
    vat_rate: number;
    credits: number | null;
    validity_months: number | null;
    interval: string | null;
    starts_on: string | null;
    ends_on: string | null;
    capacity: number | null;
    taken: number;
    is_full: boolean;
    min_age: number | null;
    max_age: number | null;
    location: string | null;
    status: string;
    status_label: string;
    is_active: boolean;
    group_id: number | null;
    subscriptions_count: number;
    purchases_count: number;
}

const props = defineProps<{
    products: ProductRij[];
    types: { value: string; label: string; description: string }[];
    gateway: { connected: boolean; name: string; message: string };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Aanbod', href: '/aanbod' }];

const iconen: Record<string, Component> = {
    doorlopend: CalendarDays,
    blok: CalendarRange,
    kamp: Tent,
    losse_training: Tag,
    privetraining: User,
    small_group: Users,
    rittenkaart: Ticket,
    overig: Tag,
};

// Per soort gegroepeerd, in de volgorde van de enum. Eén lange platte lijst met
// blokken, kampen en rittenkaarten door elkaar leest niet.
const groepen = computed(() =>
    props.types.map((type) => ({ ...type, items: props.products.filter((p) => p.type === type.value) })).filter((groep) => groep.items.length > 0),
);

/** Wat er van de inschrijving te zeggen valt: vol telt zwaarder dan open. */
const stand = (product: ProductRij) => {
    if (!product.is_active) {
        return { tekst: 'Niet zichtbaar', klas: 'bg-secondary text-muted-foreground' };
    }

    if (product.is_full) {
        return { tekst: 'Vol', klas: 'bg-warning/10 text-warning' };
    }

    if (product.status === 'open') {
        return { tekst: 'Open', klas: 'bg-success/10 text-success' };
    }

    return { tekst: product.status_label, klas: 'bg-secondary text-muted-foreground' };
};

const periode = (product: ProductRij) => {
    if (!product.starts_on) {
        return null;
    }

    return product.ends_on && product.ends_on !== product.starts_on ? product.starts_on + ' – ' + product.ends_on : product.starts_on;
};

const leeftijd = (product: ProductRij) => {
    if (product.min_age && product.max_age) {
        return product.min_age + ' t/m ' + product.max_age + ' jaar';
    }

    if (product.min_age) {
        return 'vanaf ' + product.min_age + ' jaar';
    }

    return product.max_age ? 't/m ' + product.max_age + ' jaar' : null;
};
</script>

<template>
    <Head title="Aanbod" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-4xl p-4">
            <FlashMessage />

            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <h1 class="text-2xl font-semibold tracking-tight">Aanbod</h1>
                    <p class="mt-1 text-sm text-muted-foreground">Wat je verkoopt: blokken, kampen, doorlopende training en privétraining.</p>
                </div>

                <Link
                    href="/aanbod/create"
                    class="inline-flex h-11 items-center gap-2 rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                >
                    <Plus class="size-4" />
                    Nieuw aanbod
                </Link>
            </div>

            <GatewayNotice v-if="!gateway.connected" class="mt-4" :name="gateway.name" :message="gateway.message" />

            <div v-if="products.length" class="mt-6 space-y-6">
                <section v-for="groep in groepen" :key="groep.value">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ groep.label }}</p>

                    <div class="mt-2 space-y-2">
                        <article v-for="product in groep.items" :key="product.id" class="rounded-xl border border-border bg-card p-3 shadow-sm sm:p-4">
                            <div class="flex min-w-0 items-start gap-3 sm:gap-4">
                                <span
                                    class="flex size-10 shrink-0 items-center justify-center rounded-lg"
                                    :class="product.is_active ? 'bg-primary/10 text-primary' : 'bg-secondary text-muted-foreground/70'"
                                >
                                    <component :is="iconen[product.type] ?? Tag" class="size-5" />
                                </span>

                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="font-medium">{{ product.name }}</p>
                                        <span class="rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide" :class="stand(product).klas">
                                            {{ stand(product).tekst }}
                                        </span>
                                    </div>

                                    <p v-if="product.description" class="mt-0.5 line-clamp-2 text-xs text-muted-foreground">
                                        {{ product.description }}
                                    </p>

                                    <p v-if="periode(product)" class="tabular mt-1 flex items-center gap-1.5 text-xs text-muted-foreground">
                                        <CalendarRange class="size-3.5 shrink-0" />
                                        {{ periode(product) }}
                                    </p>
                                    <p v-if="product.location" class="mt-0.5 flex items-center gap-1.5 text-xs text-muted-foreground">
                                        <MapPin class="size-3.5 shrink-0" />
                                        {{ product.location }}
                                    </p>

                                    <!-- Wat dit aanbod doet, in woorden. Een kolom
                                         met "10" zegt niets; "10 beurten" wel. -->
                                    <p class="tabular mt-1 flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-muted-foreground">
                                        <span v-if="product.capacity">
                                            <span class="font-medium text-foreground">{{ product.taken }} van {{ product.capacity }}</span> plekken
                                            bezet
                                        </span>
                                        <span v-else-if="product.taken">{{ product.taken }} deelnemers</span>
                                        <span v-if="leeftijd(product)">{{ leeftijd(product) }}</span>
                                        <span v-if="product.credits">{{ product.credits }} beurten</span>
                                        <span v-if="product.validity_months">{{ product.validity_months }} maanden geldig</span>
                                        <span>btw {{ product.vat_rate }}%</span>
                                        <span v-if="product.subscriptions_count">{{ product.subscriptions_count }} lopend</span>
                                        <span v-if="product.purchases_count">{{ product.purchases_count }} afgenomen</span>
                                    </p>

                                    <!-- Bedrag onder de naam op een telefoon: naast
                                         de naam knijpt het de titel af. -->
                                    <p class="tabular mt-2 sm:hidden">
                                        <span class="font-bold">{{ product.amount }}</span>
                                        <span class="ml-1 text-xs text-muted-foreground">{{ product.billing_label }}</span>
                                    </p>
                                </div>

                                <div class="hidden shrink-0 text-right sm:block">
                                    <p class="tabular font-bold">{{ product.amount }}</p>
                                    <p class="text-[11px] text-muted-foreground">{{ product.billing_label }}</p>
                                    <p v-if="product.vat_rate > 0" class="tabular text-[11px] text-muted-foreground">
                                        {{ product.amount_excl_vat }} ex btw
                                    </p>
                                </div>

                                <Link
                                    :href="'/aanbod/' + product.id + '/edit'"
                                    class="flex size-9 shrink-0 items-center justify-center rounded-lg text-muted-foreground transition hover:bg-secondary hover:text-foreground"
                                    :aria-label="product.name + ' bewerken'"
                                >
                                    <Pencil class="size-4" />
                                </Link>
                            </div>
                        </article>
                    </div>
                </section>
            </div>

            <div v-else class="mt-6 rounded-2xl border border-dashed border-border bg-card/50 p-10 text-center">
                <p class="font-medium">Nog geen aanbod</p>
                <p class="mt-1 text-sm text-muted-foreground">Begin met wat je het vaakst verkoopt: een blok van zes weken of doorlopende training.</p>
                <Link
                    href="/aanbod/create"
                    class="mt-4 inline-flex h-11 items-center gap-2 rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground"
                >
                    <Plus class="size-4" />
                    Nieuw aanbod
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
