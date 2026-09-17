<script setup lang="ts">
import Avatar from '@/components/Avatar.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { CalendarRange, Check, MapPin, UserCheck, X } from 'lucide-vue-next';
import { computed } from 'vue';

interface Deelnemer {
    id: number;
    player_id: number;
    name: string;
    photo: string | null;
    age: number | null;
    position: string;
    since: string;
    payment_status: 'paid' | 'open' | 'overdue' | 'none';
    payment_label: string;
    amount: string | null;
}

const props = defineProps<{
    /** Inzetkaart: begin- en eindniveau per kind bij deze cursus. */
    courseProgress?: boolean;
    product: {
        id: number;
        name: string;
        type: string;
        amount: string;
        billing: string;
        capacity: number | null;
        taken: number;
        is_full: boolean;
        starts_on: string | null;
        ends_on: string | null;
        location: string | null;
        group_id: number | null;
    };
    paidCount: number;
    confirmed: Deelnemer[];
    waitlist: Deelnemer[];
    cancelled: Deelnemer[];
}>();

/** Betaald is groen, te laat is rood, de rest is rustig. */
const betaalKlasse = (deelnemer: Deelnemer) => {
    if (deelnemer.payment_status === 'paid') {
        return 'bg-success/10 text-success';
    }

    if (deelnemer.payment_status === 'overdue') {
        return 'bg-destructive/10 text-destructive';
    }

    if (deelnemer.payment_status === 'open') {
        return 'bg-warning/10 text-warning';
    }

    return 'bg-secondary text-muted-foreground';
};

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Aanbod', href: '/aanbod' },
    { title: props.product.name, href: '/aanbod/' + props.product.id + '/deelnemers' },
]);

const periode = computed(() => {
    if (!props.product.starts_on) {
        return null;
    }

    return props.product.ends_on && props.product.ends_on !== props.product.starts_on
        ? props.product.starts_on + ' – ' + props.product.ends_on
        : props.product.starts_on;
});

const geefPlek = (deelnemer: Deelnemer) => {
    if (confirm(`${deelnemer.name} een plek geven? De ouders krijgen bericht en de rekening gaat open staan.`)) {
        router.post(`/aanbod/${props.product.id}/deelnemers/${deelnemer.id}/plek`, {}, { preserveScroll: true });
    }
};

const haalVanLijst = (deelnemer: Deelnemer) => {
    if (confirm(`${deelnemer.name} van de lijst halen? Wat er al betaald is blijft staan.`)) {
        router.delete(`/aanbod/${props.product.id}/deelnemers/${deelnemer.id}`, { preserveScroll: true });
    }
};
</script>

<template>
    <Head :title="product.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-3xl p-4">

            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">{{ product.type }}</p>
                    <h1 class="text-2xl font-semibold tracking-tight">{{ product.name }}</h1>

                    <p v-if="periode" class="tabular mt-1 flex items-center gap-1.5 text-sm text-muted-foreground">
                        <CalendarRange class="size-4 shrink-0" />
                        {{ periode }}
                    </p>
                    <p v-if="product.location" class="mt-0.5 flex items-center gap-1.5 text-sm text-muted-foreground">
                        <MapPin class="size-4 shrink-0" />
                        {{ product.location }}
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Link
                        v-if="courseProgress"
                        :href="'/aanbod/' + product.id + '/voortgang'"
                        class="inline-flex h-11 items-center rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground shadow-sm transition hover:opacity-90"
                    >
                        Niveaus per kind
                    </Link>
                    <Link
                        :href="'/aanbod/' + product.id + '/edit'"
                        class="inline-flex h-11 items-center rounded-xl border border-border bg-card px-4 text-sm font-medium shadow-sm transition hover:border-primary"
                    >
                        Bewerken
                    </Link>
                </div>
            </div>

            <!-- Hoe vol het zit. Zonder capaciteit is er geen grens, en dan
                 hoort er ook geen balk te staan die suggereert van wel. -->
            <div class="mt-5 rounded-xl border border-border bg-card p-4 shadow-sm">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <p class="font-medium">
                        <template v-if="product.capacity">
                            <span class="tabular">{{ product.taken }} van {{ product.capacity }}</span> plekken bezet
                        </template>
                        <template v-else>
                            <span class="tabular">{{ product.taken }}</span> {{ product.taken === 1 ? 'deelnemer' : 'deelnemers' }}
                        </template>
                    </p>
                    <p class="text-sm text-muted-foreground">
                        <template v-if="waitlist.length"
                            ><span class="tabular">{{ waitlist.length }}</span> op de wachtlijst ·
                        </template>
                        <span class="tabular">{{ paidCount }} van {{ confirmed.length }}</span> betaald
                    </p>
                </div>

                <div v-if="product.capacity" class="mt-2 h-2 overflow-hidden rounded-full bg-secondary">
                    <div
                        class="h-full rounded-full transition-all"
                        :class="product.is_full ? 'bg-warning' : 'bg-primary'"
                        :style="{ width: Math.min(100, (product.taken / product.capacity) * 100) + '%' }"
                    ></div>
                </div>

                <p v-if="product.is_full" class="mt-2 text-sm text-warning">Vol. Nieuwe aanmeldingen komen automatisch op de wachtlijst.</p>
            </div>

            <!-- Wie er meedoet -->
            <section class="mt-6">
                <h2 class="text-sm font-semibold">Ingeschreven</h2>

                <div v-if="confirmed.length" class="mt-2 overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                    <div
                        v-for="(deelnemer, index) in confirmed"
                        :key="deelnemer.id"
                        class="flex items-center gap-3 p-3 sm:p-4"
                        :class="index > 0 ? 'border-t border-border' : ''"
                    >
                        <Avatar :name="deelnemer.name" :photo="deelnemer.photo" size="size-10" />

                        <Link :href="'/players/' + deelnemer.player_id" class="min-w-0 flex-1 hover:text-primary">
                            <span class="block font-medium">{{ deelnemer.name }}</span>
                            <span class="block text-xs text-muted-foreground">
                                {{ deelnemer.position }}<template v-if="deelnemer.age"> · {{ deelnemer.age }} jaar</template> · sinds
                                {{ deelnemer.since }}
                            </span>
                        </Link>

                        <!-- Wie moet er nog betalen: de vraag die een school stelt
                             op de dag dat het kamp begint. -->
                        <span class="shrink-0 rounded-lg px-2 py-1 text-xs font-medium" :class="betaalKlasse(deelnemer)">
                            <span class="tabular">{{ deelnemer.payment_status === 'none' ? '' : deelnemer.amount + ' ' }}</span>
                            {{ deelnemer.payment_label }}
                        </span>

                        <button
                            type="button"
                            class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-border text-muted-foreground transition hover:border-destructive hover:text-destructive"
                            :aria-label="deelnemer.name + ' van de lijst halen'"
                            @click="haalVanLijst(deelnemer)"
                        >
                            <X class="size-4" />
                        </button>
                    </div>
                </div>

                <p v-else class="mt-2 rounded-xl border border-dashed border-border bg-card/50 p-6 text-center text-sm text-muted-foreground">
                    Nog niemand ingeschreven.
                </p>
            </section>

            <!-- De wachtlijst, op volgorde van aanmelden -->
            <section v-if="waitlist.length" class="mt-6">
                <h2 class="text-sm font-semibold">Wachtlijst</h2>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    Op volgorde van aanmelden. Jij bepaalt wie er doorschuift - jij weet wie je al gesproken hebt.
                </p>

                <div class="mt-2 overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                    <div
                        v-for="(deelnemer, index) in waitlist"
                        :key="deelnemer.id"
                        class="flex flex-col gap-3 p-3 sm:flex-row sm:items-center sm:p-4"
                        :class="index > 0 ? 'border-t border-border' : ''"
                    >
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="tabular flex size-7 shrink-0 items-center justify-center rounded-full bg-secondary text-xs font-semibold">
                                {{ index + 1 }}
                            </span>
                            <Avatar :name="deelnemer.name" :photo="deelnemer.photo" size="size-10" />

                            <Link :href="'/players/' + deelnemer.player_id" class="min-w-0 flex-1 hover:text-primary">
                                <span class="block font-medium">{{ deelnemer.name }}</span>
                                <span class="block text-xs text-muted-foreground">
                                    {{ deelnemer.position }}<template v-if="deelnemer.age"> · {{ deelnemer.age }} jaar</template> · wacht sinds
                                    {{ deelnemer.since }}
                                </span>
                            </Link>
                        </div>

                        <div class="flex shrink-0 items-center gap-2 sm:ml-auto">
                            <button
                                type="button"
                                class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-primary px-3 text-xs font-semibold text-primary-foreground transition hover:opacity-90 disabled:opacity-50"
                                :disabled="product.is_full"
                                :title="product.is_full ? 'Er is nog geen plek vrij' : ''"
                                @click="geefPlek(deelnemer)"
                            >
                                <Check class="size-3.5" />
                                Plek geven
                            </button>

                            <button
                                type="button"
                                class="flex size-9 items-center justify-center rounded-lg border border-border text-muted-foreground transition hover:border-destructive hover:text-destructive"
                                :aria-label="deelnemer.name + ' van de lijst halen'"
                                @click="haalVanLijst(deelnemer)"
                            >
                                <X class="size-4" />
                            </button>
                        </div>
                    </div>
                </div>

                <p v-if="product.is_full" class="mt-2 flex items-start gap-2 text-xs text-muted-foreground">
                    <UserCheck class="mt-0.5 size-3.5 shrink-0" />
                    Er is nu geen plek vrij. Haal eerst iemand van de ingeschrevenen af.
                </p>
            </section>

            <!-- Afgezegd: wel bewaren, niet in de weg zetten -->
            <section v-if="cancelled.length" class="mt-6">
                <h2 class="text-sm font-semibold text-muted-foreground">Niet meer op de lijst</h2>

                <div class="mt-2 space-y-1">
                    <p v-for="deelnemer in cancelled" :key="deelnemer.id" class="text-sm text-muted-foreground">
                        {{ deelnemer.name }} <span class="text-xs">· aangemeld {{ deelnemer.since }}</span>
                    </p>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
