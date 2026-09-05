<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { CreditCard, Receipt } from 'lucide-vue-next';
import { ref } from 'vue';

defineProps<{
    players: {
        id: number;
        name: string;
        subscription: {
            plan: string | null;
            amount: string;
            interval: string;
            method: string | null;
            status_label: string;
            starts_on: string;
        } | null;
    }[];
    payments: {
        id: number;
        player: string | null;
        amount: string;
        status: string;
        status_label: string;
        description: string;
        due_on: string;
        paid_at: string | null;
        is_overdue: boolean;
        payable: boolean;
    }[];
    outstanding: string;
    hasOutstanding: boolean;
    gateway: { connected: boolean; name: string };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Mijn abonnement', href: '/billing' }];

// Welke betaling op dit moment onderweg is naar de provider. Zonder dit kun je
// twee keer klikken en sta je twee keer bij de bank.
const bezig = ref<number | null>(null);

const betaal = (id: number) => {
    bezig.value = id;
    router.post('/billing/payments/' + id + '/betalen', {}, { onFinish: () => (bezig.value = null) });
};

const kleurVoor = (status: string) => {
    if (status === 'paid') {
        return 'bg-primary/10 text-primary';
    }

    if (status === 'failed' || status === 'charged_back') {
        return 'bg-destructive/10 text-destructive';
    }

    return 'bg-warning/10 text-warning';
};
</script>

<template>
    <Head title="Mijn abonnement" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl p-4">
            <h1 class="text-2xl font-semibold tracking-tight">Mijn abonnement</h1>
            <p class="mt-1 text-sm text-muted-foreground">Wat je betaalt en wat er nog openstaat.</p>

            <!-- Per kind: het lopende abonnement -->
            <div class="mt-6 space-y-3">
                <div v-for="speler in players" :key="speler.id" class="rounded-xl border border-border bg-card p-5 shadow-sm">
                    <p class="font-medium">{{ speler.name }}</p>

                    <template v-if="speler.subscription">
                        <div class="mt-3 flex flex-wrap items-baseline justify-between gap-2">
                            <p class="text-sm">
                                {{ speler.subscription.plan ?? 'Abonnement' }}
                                <span class="text-muted-foreground">&middot; {{ speler.subscription.status_label.toLowerCase() }}</span>
                            </p>
                            <p class="tabular text-lg font-bold">
                                {{ speler.subscription.amount }}
                                <span class="text-sm font-normal text-muted-foreground">{{ speler.subscription.interval.toLowerCase() }}</span>
                            </p>
                        </div>

                        <p class="mt-2 text-xs text-muted-foreground">
                            Sinds {{ speler.subscription.starts_on }}
                            <span v-if="speler.subscription.method"> &middot; {{ speler.subscription.method }}</span>
                        </p>
                    </template>

                    <p v-else class="mt-2 text-sm text-muted-foreground">
                        Er loopt nog geen abonnement. Je schoolbeheerder regelt dat.
                    </p>
                </div>
            </div>

            <div v-if="hasOutstanding" class="mt-4 rounded-xl border border-warning/30 bg-warning/5 p-4">
                <p class="text-sm">
                    Er staat nog <span class="tabular font-semibold">{{ outstanding }}</span> open.
                    <template v-if="!gateway.connected">
                        Online betalen kan nog niet; je school neemt contact met je op.
                    </template>
                </p>
            </div>

            <!-- Betaalgeschiedenis -->
            <div class="mt-4 rounded-xl border border-border bg-card p-5 shadow-sm">
                <p class="font-medium">Betalingen</p>

                <div v-if="payments.length" class="mt-4 space-y-2">
                    <div v-for="betaling in payments" :key="betaling.id" class="flex items-center gap-3 rounded-lg border border-border p-3">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-secondary text-muted-foreground">
                            <Receipt class="size-4" />
                        </span>

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">{{ betaling.description }}</p>
                            <p class="truncate text-xs text-muted-foreground">
                                <span v-if="betaling.player">{{ betaling.player }} &middot; </span>
                                <template v-if="betaling.paid_at">betaald {{ betaling.paid_at }}</template>
                                <template v-else>vervalt {{ betaling.due_on }}</template>
                            </p>
                        </div>

                        <p class="tabular shrink-0 text-sm font-semibold">{{ betaling.amount }}</p>

                        <span class="shrink-0 rounded-lg px-2 py-1 text-xs font-medium" :class="kleurVoor(betaling.status)">
                            {{ betaling.status_label }}
                        </span>

                        <button
                            v-if="betaling.payable"
                            type="button"
                            class="inline-flex h-9 shrink-0 items-center rounded-lg bg-primary px-3 text-sm font-medium text-primary-foreground transition hover:opacity-90 disabled:opacity-60"
                            :disabled="bezig === betaling.id"
                            @click="betaal(betaling.id)"
                        >
                            <CreditCard class="mr-2 size-4" />
                            {{ bezig === betaling.id ? 'Bezig…' : 'Nu betalen' }}
                        </button>
                    </div>
                </div>

                <p v-else class="mt-3 text-sm text-muted-foreground">Er zijn nog geen betalingen.</p>
            </div>
        </div>
    </AppLayout>
</template>
