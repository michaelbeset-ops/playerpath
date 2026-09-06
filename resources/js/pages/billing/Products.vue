<script setup lang="ts">
import FlashMessage from '@/components/FlashMessage.vue';
import GatewayNotice from '@/components/GatewayNotice.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { CalendarDays, Pencil, Plus, Tag, Tent, Ticket } from 'lucide-vue-next';
import { computed, type Component } from 'vue';

interface ProductRij {
    id: number;
    name: string;
    description: string | null;
    type: string;
    type_label: string;
    amount: string;
    amount_excl_vat: string;
    vat_rate: number;
    credits: number | null;
    validity_months: number | null;
    interval: string | null;
    is_active: boolean;
    subscriptions_count: number;
    purchases_count: number;
}

const props = defineProps<{
    products: ProductRij[];
    types: { value: string; label: string; description: string }[];
    gateway: { connected: boolean; name: string; message: string };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Financiën', href: '/products' },
    { title: 'Producten', href: '/products' },
];

const iconen: Record<string, Component> = {
    abonnement: CalendarDays,
    rittenkaart: Ticket,
    losse_training: Tag,
    kamp: Tent,
    overig: Tag,
};

// Per soort gegroepeerd, in de volgorde van de enum. Een lange platte lijst
// met abonnementen en rittenkaarten door elkaar leest niet.
const groepen = computed(() =>
    props.types.map((type) => ({ ...type, items: props.products.filter((p) => p.type === type.value) })).filter((groep) => groep.items.length > 0),
);
</script>

<template>
    <Head title="Producten" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-4xl p-4">
            <FlashMessage />

            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Producten</h1>
                    <p class="mt-1 text-sm text-muted-foreground">Wat je verkoopt: abonnementen, rittenkaarten, losse trainingen en kampen.</p>
                </div>

                <Link
                    href="/products/create"
                    class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                >
                    <Plus class="size-4" />
                    Nieuw product
                </Link>
            </div>

            <GatewayNotice v-if="!gateway.connected" class="mt-4" :name="gateway.name" :message="gateway.message" />

            <div v-if="products.length" class="mt-6 space-y-6">
                <div v-for="groep in groepen" :key="groep.value">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ groep.label }}</p>

                    <div class="mt-2 space-y-2">
                        <div
                            v-for="product in groep.items"
                            :key="product.id"
                            class="flex items-center gap-4 rounded-xl border border-border bg-card p-4 shadow-sm"
                        >
                            <span
                                class="flex size-10 shrink-0 items-center justify-center rounded-lg"
                                :class="product.is_active ? 'bg-primary/10 text-primary' : 'bg-secondary text-muted-foreground/70'"
                            >
                                <component :is="iconen[product.type] ?? Tag" class="size-5" />
                            </span>

                            <div class="min-w-0 flex-1">
                                <p class="flex flex-wrap items-center gap-2 font-medium">
                                    {{ product.name }}
                                    <span
                                        v-if="!product.is_active"
                                        class="rounded bg-secondary px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground"
                                    >
                                        niet actief
                                    </span>
                                </p>
                                <p v-if="product.description" class="truncate text-xs text-muted-foreground">{{ product.description }}</p>

                                <!-- Wat dit product doet, in woorden. Een kolom
                                     met "10" zegt niets; "10 beurten" wel. -->
                                <p class="tabular mt-1 flex flex-wrap gap-x-3 text-xs text-muted-foreground">
                                    <span v-if="product.interval">{{ product.interval.toLowerCase() }}</span>
                                    <span v-if="product.credits">{{ product.credits }} beurten</span>
                                    <span v-if="product.validity_months">{{ product.validity_months }} maanden geldig</span>
                                    <span>btw {{ product.vat_rate }}%</span>
                                    <span v-if="product.subscriptions_count">{{ product.subscriptions_count }} lopend</span>
                                    <span v-if="product.purchases_count">{{ product.purchases_count }} afgenomen</span>
                                </p>
                            </div>

                            <div class="shrink-0 text-right">
                                <p class="tabular font-bold">{{ product.amount }}</p>
                                <p v-if="product.vat_rate > 0" class="tabular text-[11px] text-muted-foreground">
                                    {{ product.amount_excl_vat }} ex btw
                                </p>
                            </div>

                            <Link
                                :href="'/products/' + product.id + '/edit'"
                                class="shrink-0 rounded-lg p-2 text-muted-foreground transition hover:bg-secondary hover:text-foreground"
                                :aria-label="product.name + ' bewerken'"
                            >
                                <Pencil class="size-4" />
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="mt-6 rounded-2xl border border-dashed border-border bg-card/50 p-10 text-center">
                <p class="font-medium">Nog geen producten</p>
                <p class="mt-1 text-sm text-muted-foreground">Begin met wat je het vaakst verkoopt: een maandabonnement of een tienrittenkaart.</p>
                <Link
                    href="/products/create"
                    class="mt-4 inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground"
                >
                    <Plus class="size-4" />
                    Nieuw product
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
