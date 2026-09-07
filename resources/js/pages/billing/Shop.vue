<script setup lang="ts">
import FlashMessage from '@/components/FlashMessage.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Info, ShoppingBag, Ticket } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Product {
    id: number;
    name: string;
    description: string | null;
    type: string;
    amount: string;
    is_free: boolean;
    credits: number | null;
    validity_months: number | null;
}

const props = defineProps<{
    products: Product[];
    players: { id: number; name: string }[];
    connected: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Shop', href: '/shop' }];

const page = usePage();
const fout = computed(() => (page.props.errors as Record<string, string> | undefined)?.payment ?? null);

// Voor wie koop je? Met één kind is dat geen vraag, en dan hoort er ook geen
// keuzelijst te staan.
const speler = ref<number>(props.players[0]?.id ?? 0);

const bezig = ref<number | null>(null);

const koop = (product: Product) => {
    bezig.value = product.id;

    router.post('/shop/' + product.id, { player_id: speler.value }, { onFinish: () => (bezig.value = null) });
};
</script>

<template>
    <Head title="Shop" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-3xl p-4">
            <FlashMessage />

            <h1 class="text-2xl font-semibold tracking-tight">Shop</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Wat je bij de school kunt afnemen: rittenkaarten, kampen en clinics. Je abonnement regelt de school zelf.
            </p>

            <p v-if="fout" class="mt-4 rounded-xl border border-destructive/40 bg-destructive/10 p-4 text-sm text-destructive">
                {{ fout }}
            </p>

            <!-- Eén kind: geen keuze. Meer kinderen: eerst zeggen voor wie. -->
            <div v-if="players.length > 1" class="mt-5 grid gap-2">
                <label for="speler" class="text-sm font-medium">Voor wie?</label>
                <select
                    id="speler"
                    v-model="speler"
                    class="h-11 rounded-lg border border-input bg-card px-3 text-sm outline-none focus:border-primary"
                >
                    <option v-for="p in players" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
            </div>

            <div v-if="products.length" class="mt-5 space-y-3">
                <article v-for="product in products" :key="product.id" class="rounded-xl border border-border bg-card p-4 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="flex flex-wrap items-center gap-2 font-medium">
                                {{ product.name }}
                                <span
                                    class="rounded-full bg-secondary px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground"
                                >
                                    {{ product.type }}
                                </span>
                            </p>
                            <p v-if="product.description" class="mt-1 text-sm text-muted-foreground">{{ product.description }}</p>

                            <p v-if="product.credits" class="mt-2 flex items-center gap-1.5 text-xs text-muted-foreground">
                                <Ticket class="size-3.5 shrink-0" />
                                {{ product.credits }} beurten<template v-if="product.validity_months">
                                    · {{ product.validity_months }} maanden geldig</template
                                >
                            </p>
                            <p v-else-if="product.validity_months" class="mt-2 text-xs text-muted-foreground">
                                {{ product.validity_months }} maanden geldig
                            </p>
                        </div>

                        <p class="tabular shrink-0 text-lg font-bold">{{ product.is_free ? 'gratis' : product.amount }}</p>
                    </div>

                    <button
                        type="button"
                        class="mt-3 inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground transition hover:opacity-90 disabled:opacity-60 sm:w-auto"
                        :disabled="bezig === product.id"
                        @click="koop(product)"
                    >
                        <ShoppingBag class="size-4" />
                        {{ connected && !product.is_free ? 'Afrekenen' : 'Aanvragen' }}
                    </button>
                </article>
            </div>

            <div v-else class="mt-5 rounded-2xl border border-dashed border-border bg-card/50 p-10 text-center">
                <p class="font-medium">Nog niets te koop</p>
                <p class="mt-1 text-sm text-muted-foreground">Zodra de school rittenkaarten of kampen aanbiedt, staan ze hier.</p>
            </div>

            <!-- Eerlijk zijn over wat er gebeurt na de knop. -->
            <p v-if="products.length" class="mt-4 flex items-start gap-2 text-xs text-muted-foreground">
                <Info class="mt-0.5 size-3.5 shrink-0" />
                <span v-if="connected">Je rekent meteen online af. Daarna staat het bij Mijn abonnement.</span>
                <span v-else>Je aanvraag komt als openstaande rekening bij de school terecht; daar reken je af.</span>
            </p>
        </div>
    </AppLayout>
</template>
