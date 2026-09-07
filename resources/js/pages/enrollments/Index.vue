<script setup lang="ts">
import FlashMessage from '@/components/FlashMessage.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Check, Copy, CreditCard, Inbox, Link2, Mail, Phone, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

/**
 * De inschrijvingen-inbox: gegroepeerd op wat er van de school gevraagd wordt.
 * Goedkeuren, wachten op betaling, de wachtlijst, en wat afgehandeld is.
 */
interface Inschrijving {
    id: number;
    child_name: string;
    age: number | null;
    date_of_birth: string;
    position: string;
    guardian_name: string;
    guardian_email: string;
    guardian_phone: string | null;
    relationship: string | null;
    plan: string | null;
    payment_option: string | null;
    order_total: string | null;
    order_status: string | null;
    waitlist: boolean;
    payment_method: string | null;
    note: string | null;
    details: Record<string, string | null> | null;
    status: string;
    status_label: string;
    received: string;
    handled_at: string | null;
    player_id: number | null;
    can_approve: boolean;
    can_decline: boolean;
    first_payment_id: number | null;
}

const props = defineProps<{
    pending: Inschrijving[];
    awaitingPayment: Inschrijving[];
    waitlist: Inschrijving[];
    handled: Inschrijving[];
    formUrl: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Inschrijvingen', href: '/enrollments' }];

const page = usePage<SharedData>();
const fout = computed(() => (page.props as any).errors?.enrollment as string | undefined);

const tab = ref<'pending' | 'awaitingPayment' | 'waitlist' | 'handled'>(
    props.pending.length ? 'pending' : props.awaitingPayment.length ? 'awaitingPayment' : props.waitlist.length ? 'waitlist' : 'handled',
);

const tabs = computed(() => [
    { key: 'pending' as const, label: 'Goedkeuren', count: props.pending.length },
    { key: 'awaitingPayment' as const, label: 'Wacht op betaling', count: props.awaitingPayment.length },
    { key: 'waitlist' as const, label: 'Wachtlijst', count: props.waitlist.length },
    { key: 'handled' as const, label: 'Afgehandeld', count: props.handled.length },
]);

const lijst = computed(() => props[tab.value]);

const gekopieerd = ref(false);

const kopieer = async () => {
    await navigator.clipboard.writeText(props.formUrl);
    gekopieerd.value = true;
    setTimeout(() => (gekopieerd.value = false), 2000);
};

const keurGoed = (i: Inschrijving) => {
    const wat = i.order_total ? ` De ouder krijgt een betaalverzoek van ${i.order_total}.` : '';

    if (confirm(`De inschrijving van ${i.child_name} goedkeuren?${wat}`)) {
        router.post('/enrollments/' + i.id + '/approve', {}, { preserveScroll: true });
    }
};

const wijsAf = (i: Inschrijving) => {
    if (confirm(`De inschrijving van ${i.child_name} afwijzen?`)) {
        router.post('/enrollments/' + i.id + '/decline', {}, { preserveScroll: true });
    }
};

const statusKleur: Record<string, string> = {
    awaiting_approval: 'bg-warning/15 text-warning',
    awaiting_payment: 'bg-primary/10 text-primary',
    payment_failed: 'bg-destructive/10 text-destructive',
    waitlist: 'bg-secondary text-muted-foreground',
    confirmed: 'bg-primary/10 text-primary',
    active: 'bg-primary/10 text-primary',
    declined: 'bg-secondary text-muted-foreground',
    cancelled: 'bg-secondary text-muted-foreground',
    expired: 'bg-secondary text-muted-foreground',
};

const detailLabels: Record<string, string> = { kledingmaat: 'Kledingmaat', niveau: 'Niveau', medisch: 'Medisch' };
</script>

<template>
    <Head title="Inschrijvingen" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-3xl p-4">
            <FlashMessage />

            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <h1 class="text-2xl font-semibold tracking-tight">Inschrijvingen</h1>
                    <p class="mt-1 text-sm text-muted-foreground">Wat er binnenkomt via je inschrijfpagina.</p>
                </div>

                <button
                    type="button"
                    class="inline-flex h-10 items-center gap-2 rounded-xl border border-border bg-card px-3 text-sm font-medium shadow-sm transition hover:border-primary"
                    @click="kopieer"
                >
                    <Check v-if="gekopieerd" class="size-4 text-primary" />
                    <Copy v-else class="size-4" />
                    {{ gekopieerd ? 'Gekopieerd' : 'Link naar de inschrijfpagina' }}
                </button>
            </div>

            <p v-if="fout" class="mt-4 rounded-lg border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive">{{ fout }}</p>

            <!-- Tabbladen; op een telefoon schuifbaar. -->
            <div class="-mx-4 mt-5 flex gap-2 overflow-x-auto px-4 pb-1">
                <button
                    v-for="t in tabs"
                    :key="t.key"
                    type="button"
                    class="flex h-10 shrink-0 items-center gap-2 rounded-full border px-4 text-sm font-medium transition"
                    :class="
                        tab === t.key
                            ? 'border-primary bg-primary/10 text-primary'
                            : 'border-border bg-card text-muted-foreground hover:border-primary'
                    "
                    @click="tab = t.key"
                >
                    {{ t.label }}
                    <span
                        class="tabular rounded-full px-1.5 text-xs"
                        :class="tab === t.key ? 'bg-primary text-primary-foreground' : 'bg-secondary'"
                        >{{ t.count }}</span
                    >
                </button>
            </div>

            <div v-if="!lijst.length" class="mt-6 rounded-xl border border-dashed border-border p-8 text-center">
                <Inbox class="mx-auto size-8 text-muted-foreground" />
                <p class="mt-2 text-sm text-muted-foreground">Niets in dit vak.</p>
            </div>

            <div v-else class="mt-4 space-y-3">
                <article v-for="i in lijst" :key="i.id" class="rounded-xl border border-border bg-card p-4 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="font-semibold">
                                {{ i.child_name }}
                                <span v-if="i.age" class="font-normal text-muted-foreground">· {{ i.age }} jaar · {{ i.position }}</span>
                            </p>
                            <p class="text-sm text-muted-foreground">
                                {{ i.plan ?? 'Zonder aanbod' }}<template v-if="i.payment_option"> · {{ i.payment_option }}</template>
                            </p>
                        </div>
                        <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium" :class="statusKleur[i.status] ?? 'bg-secondary'">{{
                            i.status_label
                        }}</span>
                    </div>

                    <div class="mt-3 grid gap-1 text-sm">
                        <p>
                            {{ i.guardian_name }}<span v-if="i.relationship" class="text-muted-foreground"> ({{ i.relationship }})</span>
                        </p>
                        <a :href="'mailto:' + i.guardian_email" class="flex items-center gap-1.5 text-muted-foreground hover:text-foreground">
                            <Mail class="size-3.5" />{{ i.guardian_email }}
                        </a>
                        <a
                            v-if="i.guardian_phone"
                            :href="'tel:' + i.guardian_phone"
                            class="flex items-center gap-1.5 text-muted-foreground hover:text-foreground"
                        >
                            <Phone class="size-3.5" />{{ i.guardian_phone }}
                        </a>
                        <p v-if="i.order_total" class="tabular flex items-center gap-1.5 text-muted-foreground">
                            <CreditCard class="size-3.5" />{{ i.order_total }} · {{ i.order_status
                            }}<template v-if="i.payment_method"> · {{ i.payment_method }}</template>
                        </p>
                    </div>

                    <dl v-if="i.details && Object.values(i.details).some(Boolean)" class="mt-2 grid gap-0.5 text-xs text-muted-foreground">
                        <template v-for="(waarde, sleutel) in i.details" :key="sleutel">
                            <div v-if="waarde" class="flex gap-2">
                                <dt class="shrink-0 font-medium">{{ detailLabels[sleutel] ?? sleutel }}:</dt>
                                <dd class="min-w-0 break-words">{{ waarde }}</dd>
                            </div>
                        </template>
                    </dl>

                    <p v-if="i.note" class="mt-2 rounded-lg bg-secondary px-3 py-2 text-sm">{{ i.note }}</p>

                    <p class="mt-2 text-xs text-muted-foreground">
                        Ontvangen {{ i.received }}<template v-if="i.handled_at"> · afgehandeld op {{ i.handled_at }}</template>
                    </p>

                    <div class="mt-3 flex flex-wrap gap-2">
                        <button
                            v-if="i.can_approve"
                            type="button"
                            class="inline-flex h-10 items-center gap-2 rounded-lg bg-primary px-4 text-sm font-semibold text-primary-foreground"
                            @click="keurGoed(i)"
                        >
                            <Check class="size-4" />
                            {{ i.waitlist ? 'Plek geven' : 'Goedkeuren' }}
                        </button>
                        <Link
                            v-if="i.first_payment_id"
                            :href="'/payments?tab=open'"
                            class="inline-flex h-10 items-center gap-2 rounded-lg border border-border px-4 text-sm font-medium hover:border-primary"
                        >
                            <CreditCard class="size-4" />
                            Betaling markeren
                        </Link>
                        <a
                            v-if="i.first_payment_id"
                            :href="'/enrollments/' + i.id + '/betaallink'"
                            target="_blank"
                            class="inline-flex h-10 items-center gap-2 rounded-lg border border-border px-4 text-sm font-medium hover:border-primary"
                        >
                            <Link2 class="size-4" />
                            Betaallink
                        </a>
                        <Link
                            v-if="i.player_id"
                            :href="'/players/' + i.player_id"
                            class="inline-flex h-10 items-center rounded-lg border border-border px-4 text-sm font-medium hover:border-primary"
                        >
                            Naar de speler
                        </Link>
                        <button
                            v-if="i.can_decline"
                            type="button"
                            class="inline-flex h-10 items-center gap-2 rounded-lg px-3 text-sm text-muted-foreground hover:text-destructive"
                            @click="wijsAf(i)"
                        >
                            <X class="size-4" />
                            Afwijzen
                        </button>
                    </div>
                </article>
            </div>
        </div>
    </AppLayout>
</template>
