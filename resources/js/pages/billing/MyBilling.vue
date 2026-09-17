<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { CreditCard, Receipt } from 'lucide-vue-next';
import { computed, ref } from 'vue';

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
            ends_on: string | null;
            status: string;
            id: number;
            can_cancel: boolean;
        } | null;
    }[];
    enrollments: {
        id: number;
        child: string;
        product: string | null;
        starts_on: string | null;
        ends_on: string | null;
        status: string;
        status_label: string;
        can_cancel: boolean;
        refund: string;
        refund_cents: number;
        is_free: boolean;
    }[];
    policy: { cancellation: string; notice_months: number };
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
        offline: boolean;
    }[];
    outstanding: string;
    hasOutstanding: boolean;
    gateway: { connected: boolean; name: string };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Mijn abonnement', href: '/billing' }];

// Wat de server terugmeldt bij betalen, annuleren of opzeggen.
const fouten = computed(() => Object.values((usePage().props.errors as Record<string, string> | undefined) ?? {}));
const demo = computed(() => usePage().props.paymentsDemo === true);

// Welke betaling op dit moment onderweg is naar de provider. Zonder dit kun je
// twee keer klikken en sta je twee keer bij de bank.
const bezig = ref<number | null>(null);

const betaal = (id: number) => {
    bezig.value = id;
    router.post('/billing/payments/' + id + '/betalen', {}, { onFinish: () => (bezig.value = null) });
};

// Annuleren en opzeggen: met een bevestiging die zegt wat er terugkomt, want
// dat is wat een ouder wil weten vóórdat hij klikt.
const annuleer = (e: { id: number; child: string; refund: string; refund_cents: number; is_free: boolean }) => {
    const uitleg = e.refund_cents > 0 ? `Je krijgt ${e.refund} terug.` : 'Er komt volgens de voorwaarden niets terug.';
    const reden = prompt(`De inschrijving van ${e.child} annuleren? ${uitleg} Reden (optioneel):`);

    if (reden !== null) {
        router.post('/billing/inschrijvingen/' + e.id + '/annuleren', { reason: reden }, { preserveScroll: true });
    }
};

const zegOp = (id: number, maanden: number) => {
    const termijn = maanden === 0 ? 'per direct' : `met ${maanden} ${maanden === 1 ? 'maand' : 'maanden'} opzegtermijn`;

    if (confirm(`Het abonnement opzeggen, ${termijn}? Tot die tijd loopt het gewoon door.`)) {
        router.post('/billing/abonnementen/' + id + '/opzeggen', {}, { preserveScroll: true });
    }
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

            <div v-if="fouten.length" class="mt-4 rounded-xl border border-destructive/40 bg-destructive/10 p-4 text-sm text-destructive" role="alert">
                <p v-for="fout in fouten" :key="fout">{{ fout }}</p>
            </div>
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
                            <span v-if="speler.subscription.ends_on"> &middot; loopt tot {{ speler.subscription.ends_on }}</span>
                        </p>

                        <button
                            v-if="speler.subscription.can_cancel"
                            type="button"
                            class="mt-2 inline-flex min-h-11 items-center text-sm text-muted-foreground underline underline-offset-4 hover:text-destructive"
                            @click="zegOp(speler.subscription.id, policy.notice_months)"
                        >
                            Abonnement opzeggen
                        </button>
                    </template>

                    <p v-else class="mt-2 text-sm text-muted-foreground">Er loopt nog geen abonnement. Je schoolbeheerder regelt dat.</p>
                </div>
            </div>

            <!-- De inschrijvingen: wat loopt, en wat je nog kunt annuleren. -->
            <div v-if="enrollments.length" class="mt-4 rounded-xl border border-border bg-card p-5 shadow-sm">
                <p class="font-medium">Inschrijvingen</p>
                <p class="mt-1 text-xs text-muted-foreground">{{ policy.cancellation }}</p>

                <div class="mt-4 space-y-2">
                    <div
                        v-for="e in enrollments"
                        :key="e.id"
                        class="flex flex-col gap-2 rounded-lg border border-border p-3 sm:flex-row sm:items-center"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium">{{ e.child }} &middot; {{ e.product ?? 'aanbod' }}</p>
                            <p class="text-xs text-muted-foreground">
                                <template v-if="e.starts_on">Vanaf {{ e.starts_on }}</template
                                ><template v-if="e.ends_on"> t/m {{ e.ends_on }}</template>
                            </p>
                        </div>
                        <span class="shrink-0 self-start rounded-lg bg-secondary px-2 py-1 text-xs font-medium sm:self-auto">{{
                            e.status_label
                        }}</span>
                        <button
                            v-if="e.can_cancel"
                            type="button"
                            class="inline-flex min-h-11 shrink-0 items-center self-start text-xs text-muted-foreground underline underline-offset-4 hover:text-destructive sm:self-auto"
                            @click="annuleer(e)"
                        >
                            Annuleren{{ e.refund_cents > 0 ? (e.is_free ? ' (kosteloos)' : ' (' + e.refund + ' terug)') : '' }}
                        </button>
                    </div>
                </div>
            </div>

            <div v-if="hasOutstanding" class="mt-4 rounded-xl border border-warning/30 bg-warning/5 p-4">
                <p class="text-sm">
                    Er staat nog <span class="tabular font-semibold">{{ outstanding }}</span> open.
                    <template v-if="!gateway.connected"> Online betalen kan nog niet; je school neemt contact met je op. </template>
                </p>
            </div>

            <!-- Betaalgeschiedenis -->
            <div class="mt-4 rounded-xl border border-border bg-card p-5 shadow-sm">
                <p class="font-medium">Betalingen</p>

                <div v-if="payments.length" class="mt-4 space-y-2">
                    <!-- Op een telefoon stapelt de rij: naam, dan bedrag en status. Met alles
                         naast elkaar werd "Keeperstraining september 2026" afgekapt. -->
                    <div
                        v-for="betaling in payments"
                        :key="betaling.id"
                        class="flex flex-col gap-2 rounded-lg border border-border p-3 sm:flex-row sm:items-center sm:gap-3"
                    >
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-secondary text-muted-foreground">
                            <Receipt class="size-4" />
                        </span>

                        <div class="min-w-0 flex-1">
                            <p class="break-words text-sm font-medium">{{ betaling.description }}</p>
                            <p class="break-words text-xs text-muted-foreground">
                                <span v-if="betaling.player">{{ betaling.player }} &middot; </span>
                                <template v-if="betaling.paid_at">betaald {{ betaling.paid_at }}</template>
                                <template v-else>vervalt {{ betaling.due_on }}</template>
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2 sm:contents">
                            <p class="tabular shrink-0 text-sm font-semibold">{{ betaling.amount }}</p>

                            <span class="shrink-0 rounded-lg px-2 py-1 text-xs font-medium" :class="kleurVoor(betaling.status)">
                                {{ betaling.status_label }}
                            </span>

                            <p v-if="betaling.offline && betaling.status !== 'paid'" class="shrink-0 text-xs text-muted-foreground">
                                {{ betaling.method_value === 'cash' ? (betaling.cash_at_training ? 'Contant bij de training' : 'Contant bij de school') : 'Via overboeking' }}
                            </p>

                            <button
                                v-if="betaling.payable"
                                type="button"
                                class="inline-flex min-h-11 shrink-0 items-center rounded-lg bg-primary px-3 text-sm font-medium text-primary-foreground transition hover:opacity-90 disabled:opacity-60"
                                :disabled="bezig === betaling.id"
                                @click="betaal(betaling.id)"
                            >
                                <CreditCard class="mr-2 size-4" />
                                {{ bezig === betaling.id ? 'Bezig…' : demo ? 'Nu betalen (demo)' : 'Nu betalen' }}
                            </button>
                        </div>
                    </div>
                </div>

                <p v-else class="mt-3 text-sm text-muted-foreground">Er zijn nog geen betalingen.</p>
            </div>
        </div>
    </AppLayout>
</template>
