<script setup lang="ts">
import FlashMessage from '@/components/FlashMessage.vue';
import GatewayNotice from '@/components/GatewayNotice.vue';
import InputError from '@/components/InputError.vue';
import LoadMore from '@/components/LoadMore.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { type PageMeta } from '@/types/pagination';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Abonnement {
    id: number;
    player: string | null;
    player_id: number;
    plan: string | null;
    amount: string;
    interval: string;
    status: string;
    status_label: string;
    method: string | null;
    starts_on: string;
    ends_on: string | null;
}

defineProps<{
    subscriptions: Abonnement[];
    subscriptionsPage: PageMeta;
    gateway: { connected: boolean; name: string; message: string };
    playersWithoutSubscription: { id: number; name: string }[];
    products: { id: number; name: string; amount: string; interval: string | null }[];
    methods: Record<string, string>;
    statuses: Record<string, string>;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Abonnementen', href: '/subscriptions' }];

const toonFormulier = ref(false);

const form = useForm({
    player_id: '',
    product_id: '',
    payment_method: 'directdebit',
    // De datum van vandaag in lokale tijd: toISOString() geeft tussen middernacht en twee uur gisteren.
    starts_on: new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10),
    installments: 1,
});

const opslaan = () =>
    form.post('/subscriptions', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            toonFormulier.value = false;
        },
    });

// Stoppen is niet terug te draaien; daar hoort een bevestiging bij. Lukt een
// overgang niet, dan komt de fout onder de lijst en springt de keuze terug.
// De keuzelijst zetten we zelf terug: de lijst opnieuw ophalen met alleen
// "subscriptions" zou hem, omdat hij per pagina wordt samengevoegd, dubbel
// onder elkaar zetten.
const zetStatus = (abonnement: Abonnement, status: string, keuze: HTMLSelectElement) => {
    if (['cancelled', 'ended'].includes(status) && !confirm('Dit abonnement stoppen? Dat is niet terug te draaien.')) {
        keuze.value = abonnement.status;

        return;
    }

    router.patch('/subscriptions/' + abonnement.id, { status }, { preserveScroll: true, onError: () => (keuze.value = abonnement.status) });
};

const statusFout = computed(() => (usePage().props.errors as Record<string, string> | undefined)?.status ?? null);

const kleurVoor = (status: string) =>
    status === 'active' ? 'bg-primary/10 text-primary' : status === 'paused' ? 'bg-warning/10 text-warning' : 'bg-secondary text-muted-foreground';
</script>

<template>
    <Head title="Abonnementen" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-4xl p-4">
            <FlashMessage />
            <GatewayNotice :gateway="gateway" />

            <p v-if="statusFout" class="mt-4 rounded-xl border border-destructive/40 bg-destructive/10 p-4 text-sm text-destructive" role="alert">
                {{ statusFout }}
            </p>

            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Abonnementen</h1>
                    <p class="mt-1 text-sm text-muted-foreground">Wie zit op welk tarief, en hoe wordt er betaald.</p>
                </div>

                <Button v-if="!toonFormulier && products.length" @click="toonFormulier = true">
                    <Plus class="mr-2 size-4" />
                    Abonnement toevoegen
                </Button>
            </div>

            <p v-if="!products.length" class="mt-6 rounded-xl border border-dashed border-border bg-card/50 p-6 text-center text-sm">
                Je hebt nog geen aanbod dat per maand betaald wordt.
                <Link href="/aanbod/create" class="font-medium text-primary underline underline-offset-4">Maak er eerst een aan</Link>.
            </p>

            <!-- Inschrijven: hier wordt straks ook de incasso of iDEAL-betaling gestart -->
            <form v-if="toonFormulier" class="mt-6 space-y-5 rounded-xl border border-border bg-card p-5 shadow-sm" @submit.prevent="opslaan">
                <p class="font-medium">Nieuw abonnement</p>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="player_id">Speler</Label>
                        <select
                            id="player_id"
                            v-model="form.player_id"
                            class="min-h-11 rounded-lg border border-input bg-background px-3 text-sm outline-none focus:border-primary"
                        >
                            <option value="">Kies een speler...</option>
                            <option v-for="speler in playersWithoutSubscription" :key="speler.id" :value="speler.id">{{ speler.name }}</option>
                        </select>
                        <p class="text-xs text-muted-foreground">Alleen spelers zonder lopend abonnement.</p>
                        <InputError :message="form.errors.player_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="product_id">Tarief</Label>
                        <select
                            id="product_id"
                            v-model="form.product_id"
                            class="min-h-11 rounded-lg border border-input bg-background px-3 text-sm outline-none focus:border-primary"
                        >
                            <option value="">Kies een tarief...</option>
                            <option v-for="plan in products" :key="plan.id" :value="plan.id">
                                {{ plan.name }} - {{ plan.amount }} {{ plan.interval?.toLowerCase() ?? '' }}
                            </option>
                        </select>
                        <InputError :message="form.errors.product_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="payment_method">Betaalmethode</Label>
                        <select
                            id="payment_method"
                            v-model="form.payment_method"
                            class="min-h-11 rounded-lg border border-input bg-background px-3 text-sm outline-none focus:border-primary"
                        >
                            <option v-for="(label, waarde) in methods" :key="waarde" :value="waarde">{{ label }}</option>
                        </select>
                        <InputError :message="form.errors.payment_method" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="starts_on">Ingangsdatum</Label>
                        <Input id="starts_on" v-model="form.starts_on" type="date" required />
                        <InputError :message="form.errors.starts_on" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="installments">In termijnen</Label>
                        <select
                            id="installments"
                            v-model.number="form.installments"
                            class="min-h-11 rounded-lg border border-input bg-background px-3 text-sm outline-none focus:border-primary"
                        >
                            <option :value="1">In één keer</option>
                            <option v-for="n in 11" :key="n + 1" :value="n + 1">{{ n + 1 }} termijnen</option>
                        </select>
                        <p class="text-xs text-muted-foreground">Het bedrag per periode wordt dan gespreid over evenveel maandrekeningen.</p>
                        <InputError :message="form.errors.installments" />
                    </div>
                </div>

                <p v-if="!gateway.connected" class="rounded-lg bg-secondary px-3 py-2 text-xs text-muted-foreground">
                    Je legt nu alleen vast wie waarop zit. Er wordt niets geïncasseerd zolang {{ gateway.name }} niet is aangesloten.
                </p>

                <div class="flex items-center gap-3">
                    <Button type="submit" :disabled="form.processing || !form.player_id || !form.product_id">Abonnement vastleggen</Button>
                    <button type="button" class="text-sm text-muted-foreground underline underline-offset-4" @click="toonFormulier = false">
                        Annuleren
                    </button>
                </div>
            </form>

            <div v-if="subscriptions.length" class="mt-4 overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <div
                    v-for="(abonnement, index) in subscriptions"
                    :key="abonnement.id"
                    class="flex flex-col gap-3 p-4 sm:flex-row sm:flex-wrap sm:items-center sm:gap-4"
                    :class="index > 0 ? 'border-t border-border' : ''"
                >
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium">{{ abonnement.player ?? 'Onbekende speler' }}</p>
                        <p class="text-xs leading-snug text-muted-foreground">
                            {{ abonnement.plan ?? 'Tarief verwijderd' }} &middot; sinds {{ abonnement.starts_on }}
                            <span v-if="abonnement.method"> &middot; {{ abonnement.method }}</span>
                            <span v-if="abonnement.ends_on"> &middot; tot {{ abonnement.ends_on }}</span>
                        </p>
                    </div>

                    <!-- Zelfde reden als bij Betalingen: op mobiel een tweede regel. -->
                    <div class="flex flex-wrap items-center gap-3 sm:contents">
                        <div class="shrink-0 sm:text-right">
                            <p class="tabular font-semibold">{{ abonnement.amount }}</p>
                            <p class="text-xs text-muted-foreground">{{ abonnement.interval.toLowerCase() }}</p>
                        </div>

                        <span class="shrink-0 rounded-lg px-2 py-1 text-xs font-medium" :class="kleurVoor(abonnement.status)">
                            {{ abonnement.status_label }}
                        </span>

                        <select
                            :value="abonnement.status"
                            class="min-h-11 shrink-0 rounded-lg border border-input bg-background px-2 py-1.5 text-xs outline-none focus:border-primary"
                            :aria-label="'Status van het abonnement van ' + (abonnement.player ?? 'onbekend')"
                            @change="zetStatus(abonnement, ($event.target as HTMLSelectElement).value, $event.target as HTMLSelectElement)"
                        >
                            <option v-for="(label, waarde) in statuses" :key="waarde" :value="waarde">{{ label }}</option>
                        </select>
                    </div>
                </div>
            </div>

            <LoadMore
                v-if="subscriptions.length"
                prop="subscriptions"
                meta-prop="subscriptionsPage"
                :meta="subscriptionsPage"
                noun="abonnementen"
                label="Meer abonnementen laden"
            />

            <div v-else-if="products.length" class="mt-4 rounded-2xl border border-dashed border-border bg-card/50 p-10 text-center">
                <p class="font-medium">Nog geen abonnementen</p>
                <p class="mt-1 text-sm text-muted-foreground">Koppel een speler aan een tarief om te beginnen.</p>
            </div>
        </div>
    </AppLayout>
</template>
