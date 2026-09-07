<script setup lang="ts">
import FlashMessage from '@/components/FlashMessage.vue';
import GatewayNotice from '@/components/GatewayNotice.vue';
import StatCard from '@/components/StatCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { AlertTriangle, Clock, Euro, Search, SlidersHorizontal, Wallet } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';

interface Betaling {
    id: number;
    player: string | null;
    player_id: number;
    amount: string;
    vat_rate: number;
    status: string;
    status_label: string;
    method: string | null;
    method_value: string | null;
    description: string;
    due_on: string;
    paid_at: string | null;
    is_overdue: boolean;
    group_key: string;
    group_label: string;
}

const props = defineProps<{
    payments: Betaling[];
    filters: { tab: string; period: string; method: string; product: number | null; search: string };
    tabs: Record<string, string>;
    periods: Record<string, string>;
    totals: { count: number; total: string; excl_vat: string; vat: string; shown: number };
    statuses: Record<string, string>;
    methods: Record<string, string>;
    products: { id: number; name: string }[];
    summary: {
        revenueThisMonth: string;
        outstanding: string;
        outstandingCount: number;
        overdue: string;
        overdueCount: number;
        activeSubscriptions: number;
        yearlyValue: string;
        needsAttentionCount: number;
    };
    gateway: { connected: boolean; name: string; message: string };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Betalingen', href: '/payments' }];

const filters = reactive({ ...props.filters });

let wachten: ReturnType<typeof setTimeout> | undefined;

watch(
    filters,
    () => {
        clearTimeout(wachten);
        wachten = setTimeout(() => {
            router.get('/payments', { ...filters, product: filters.product ?? '' }, { preserveState: true, replace: true });
        }, 300);
    },
    { deep: true },
);

const kleurVoor = (status: string) => {
    if (status === 'paid') {
        return 'bg-primary/10 text-primary';
    }

    if (status === 'failed' || status === 'charged_back') {
        return 'bg-destructive/10 text-destructive';
    }

    if (status === 'refunded') {
        return 'bg-secondary text-muted-foreground';
    }

    return 'bg-warning/10 text-warning';
};

// Per dag, met de datum één keer boven het groepje in plaats van op elke regel.
// Welke datum dat is bepaalt de server, want dat verschilt per tabblad. De
// volgorde komt daar ook vandaan; hier wordt alleen samengevoegd.
const dagen = computed(() => {
    const uit: { key: string; label: string; items: Betaling[] }[] = [];

    for (const betaling of props.payments) {
        const laatste = uit[uit.length - 1];

        if (laatste?.key === betaling.group_key) {
            laatste.items.push(betaling);
        } else {
            uit.push({ key: betaling.group_key, label: betaling.group_label, items: [betaling] });
        }
    }

    return uit;
});

// Twee keuzelijsten op elke regel maakten van een lijst een formulier. Ze zitten
// nu achter een knopje: kijken is het gewone geval, wijzigen de uitzondering.
const open = ref<number[]>([]);

const wissel = (id: number) => {
    open.value = open.value.includes(id) ? open.value.filter((x) => x !== id) : [...open.value, id];
};

const zetStatus = (betaling: Betaling, status: string) =>
    router.patch('/payments/' + betaling.id, { status }, { preserveScroll: true, preserveState: false });

/**
 * Hoe het geld binnenkwam. Bij contant en overboeking is dit de enige plek
 * waar dat wordt vastgelegd: dat geld loopt buiten het systeem om.
 */
const zetMethode = (betaling: Betaling, method: string) =>
    router.patch('/payments/' + betaling.id, { status: betaling.status, method }, { preserveScroll: true, preserveState: false });
</script>

<template>
    <Head title="Betalingen" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-5xl p-4">
            <FlashMessage />
            <GatewayNotice :gateway="gateway" />

            <h1 class="text-2xl font-semibold tracking-tight">Betalingen</h1>
            <p class="mt-1 text-sm text-muted-foreground">Wat er binnenkomt, wat openstaat en wat misging.</p>

            <div class="mt-6 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                <StatCard label="Ontvangen" :value="summary.revenueThisMonth" hint="deze maand, betaalde facturen" :icon="Euro" />
                <StatCard
                    label="Openstaand"
                    tone="warning"
                    :value="summary.outstanding"
                    :hint="summary.outstandingCount + (summary.outstandingCount === 1 ? ' factuur' : ' facturen')"
                    :icon="Wallet"
                />
                <StatCard
                    label="Achterstallig"
                    :value="summary.overdueCount === 0 ? null : summary.overdue"
                    :hint="summary.overdueCount === 0 ? 'Niets over de vervaldatum' : summary.overdueCount + ' over de vervaldatum'"
                    :icon="Clock"
                    tone="warning"
                />
                <StatCard
                    label="Vraagt om actie"
                    :value="summary.needsAttentionCount === 0 ? null : summary.needsAttentionCount"
                    :hint="summary.needsAttentionCount === 0 ? 'Geen mislukte of gestorneerde' : 'mislukt of gestorneerd'"
                    :icon="AlertTriangle"
                    tone="danger"
                />
            </div>

            <!-- Tabbladen: elk beantwoordt een vraag. Wat kwam er binnen, wat
                 staat er open, wat is te laat, wat komt eraan. -->
            <div class="mt-6 flex gap-1 overflow-x-auto rounded-xl border border-border bg-card p-1 shadow-sm">
                <button
                    v-for="(label, waarde) in tabs"
                    :key="waarde"
                    type="button"
                    class="shrink-0 rounded-lg px-3 py-2 text-sm font-medium transition"
                    :class="filters.tab === waarde ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'"
                    @click="filters.tab = waarde"
                >
                    {{ label }}
                </button>
            </div>

            <!-- Filters -->
            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="relative">
                    <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <input
                        v-model="filters.search"
                        type="search"
                        placeholder="Zoek op speler of omschrijving..."
                        class="w-full rounded-lg border border-input bg-card py-2 pl-9 pr-3 text-sm outline-none focus:border-primary"
                    />
                </div>

                <select v-model="filters.period" class="rounded-lg border border-input bg-card px-3 py-2 text-sm outline-none focus:border-primary">
                    <option v-for="(label, waarde) in periods" :key="waarde" :value="waarde">{{ label }}</option>
                </select>

                <select v-model="filters.method" class="rounded-lg border border-input bg-card px-3 py-2 text-sm outline-none focus:border-primary">
                    <option value="">Alle methodes</option>
                    <option v-for="(label, waarde) in methods" :key="waarde" :value="waarde">{{ label }}</option>
                </select>

                <!-- "Wie heeft het zomerkamp al betaald?" is een vraag over een
                     aanbod, niet over een maand. -->
                <select
                    v-model="filters.product"
                    class="col-span-full rounded-lg border border-input bg-card px-3 py-2 text-sm outline-none focus:border-primary sm:col-span-1"
                >
                    <option :value="null">Al het aanbod</option>
                    <option v-for="product in products" :key="product.id" :value="product.id">{{ product.name }}</option>
                </select>
            </div>

            <!-- De totaalregel telt precies de rijen die eronder staan. Ex btw
                 wordt per rij berekend, want de tarieven verschillen per
                 product; er een percentage van het totaal afhalen zou een getal
                 opleveren dat nergens op slaat. -->
            <div class="mt-3 flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1 rounded-xl bg-secondary px-4 py-3">
                <p class="text-sm font-medium">
                    Totaal <span class="tabular text-muted-foreground">({{ totals.count }})</span>
                </p>
                <p class="tabular flex flex-wrap items-baseline gap-x-4">
                    <span class="text-lg font-bold">{{ totals.total }}</span>
                    <span class="text-sm text-muted-foreground">{{ totals.excl_vat }} excl. btw</span>
                    <span class="text-xs text-muted-foreground">btw {{ totals.vat }}</span>
                </p>
            </div>

            <p v-if="totals.count > totals.shown" class="mt-2 text-xs text-muted-foreground">
                Het totaal gaat over alle <span class="tabular">{{ totals.count }}</span> rekeningen; hieronder staan de
                <span class="tabular">{{ totals.shown }}</span> meest recente. Wil je ze allemaal, gebruik dan
                <Link href="/exports" class="underline underline-offset-4">Overzichten</Link>.
            </p>

            <div v-if="payments.length" class="mt-4 space-y-4">
                <section v-for="dag in dagen" :key="dag.key">
                    <h2 class="text-sm font-semibold first-letter:uppercase">{{ dag.label }}</h2>

                    <div class="mt-2 overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                        <div v-for="(betaling, index) in dag.items" :key="betaling.id" :class="index > 0 ? 'border-t border-border' : ''">
                            <div class="flex items-start gap-3 p-3 sm:items-center sm:p-4">
                                <div class="min-w-0 flex-1">
                                    <p class="font-medium">{{ betaling.player ?? 'Onbekende speler' }}</p>
                                    <p class="text-xs leading-snug text-muted-foreground">
                                        {{ betaling.description }}<span v-if="betaling.method"> &middot; {{ betaling.method }}</span>
                                    </p>

                                    <!-- Smal past het bedrag niet naast de naam zonder die
                                         af te knijpen; daar staat het eronder. -->
                                    <p class="tabular mt-1.5 flex flex-wrap items-center gap-2 sm:hidden">
                                        <span class="font-semibold">{{ betaling.amount }}</span>
                                        <span class="rounded-lg px-2 py-0.5 text-xs font-medium" :class="kleurVoor(betaling.status)">
                                            {{ betaling.status_label }}<span v-if="betaling.is_overdue"> &middot; te laat</span>
                                        </span>
                                    </p>
                                </div>

                                <p class="tabular hidden shrink-0 text-right sm:block">
                                    <span class="font-semibold">{{ betaling.amount }}</span>
                                    <span v-if="betaling.vat_rate > 0" class="block text-[11px] text-muted-foreground">
                                        incl. {{ betaling.vat_rate }}% btw
                                    </span>
                                </p>

                                <span class="hidden shrink-0 rounded-lg px-2 py-1 text-xs font-medium sm:inline" :class="kleurVoor(betaling.status)">
                                    {{ betaling.status_label }}
                                    <span v-if="betaling.is_overdue"> &middot; te laat</span>
                                </span>

                                <button
                                    type="button"
                                    class="flex size-9 shrink-0 items-center justify-center rounded-lg border transition"
                                    :class="
                                        open.includes(betaling.id)
                                            ? 'border-primary text-primary'
                                            : 'border-border text-muted-foreground hover:border-primary'
                                    "
                                    :aria-expanded="open.includes(betaling.id)"
                                    :aria-label="'Betaling van ' + (betaling.player ?? 'onbekend') + ' aanpassen'"
                                    @click="wissel(betaling.id)"
                                >
                                    <SlidersHorizontal class="size-4" />
                                </button>
                            </div>

                            <!-- Met de hand bijwerken; nodig zolang er geen provider is,
                                 en daarna nog steeds voor overboekingen en contant. -->
                            <div
                                v-if="open.includes(betaling.id)"
                                class="grid gap-3 border-t border-border bg-secondary/40 p-3 sm:grid-cols-2 sm:p-4"
                            >
                                <label class="grid gap-1 text-xs font-medium text-muted-foreground">
                                    Status
                                    <select
                                        :value="betaling.status"
                                        class="h-9 rounded-lg border border-input bg-background px-2 text-sm text-foreground outline-none focus:border-primary"
                                        @change="zetStatus(betaling, ($event.target as HTMLSelectElement).value)"
                                    >
                                        <option v-for="(label, waarde) in statuses" :key="waarde" :value="waarde">{{ label }}</option>
                                    </select>
                                </label>

                                <!-- Alleen als er daadwerkelijk geld binnen is: bij een
                                     openstaande rekening valt er nog niets vast te leggen. -->
                                <label v-if="betaling.status === 'paid'" class="grid gap-1 text-xs font-medium text-muted-foreground">
                                    Hoe binnengekomen
                                    <select
                                        :value="betaling.method_value ?? ''"
                                        class="h-9 rounded-lg border border-input bg-background px-2 text-sm text-foreground outline-none focus:border-primary"
                                        @change="zetMethode(betaling, ($event.target as HTMLSelectElement).value)"
                                    >
                                        <option value="" disabled>Kies een methode</option>
                                        <option v-for="(label, waarde) in methods" :key="waarde" :value="waarde">{{ label }}</option>
                                    </select>
                                </label>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <div v-else class="mt-4 rounded-2xl border border-dashed border-border bg-card/50 p-10 text-center">
                <p class="font-medium">Niets gevonden</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    Geen rekeningen die aan deze filters voldoen. Kies een ruimere periode of het tabblad Alles.
                </p>
            </div>
        </div>
    </AppLayout>
</template>
