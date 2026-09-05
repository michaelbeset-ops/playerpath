<script setup lang="ts">
import FlashMessage from '@/components/FlashMessage.vue';
import GatewayNotice from '@/components/GatewayNotice.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { ref } from 'vue';

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

const props = defineProps<{
    subscriptions: Abonnement[];
    gateway: { connected: boolean; name: string; message: string };
    playersWithoutSubscription: { id: number; name: string }[];
    plans: { id: number; name: string; amount: string; interval: string }[];
    methods: Record<string, string>;
    statuses: Record<string, string>;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Abonnementen', href: '/subscriptions' }];

const toonFormulier = ref(false);

const form = useForm({
    player_id: '',
    plan_id: '',
    payment_method: 'directdebit',
    starts_on: new Date().toISOString().slice(0, 10),
});

const opslaan = () =>
    form.post('/subscriptions', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            toonFormulier.value = false;
        },
    });

const zetStatus = (abonnement: Abonnement, status: string) =>
    router.patch('/subscriptions/' + abonnement.id, { status }, { preserveScroll: true });

const kleurVoor = (status: string) =>
    status === 'active' ? 'bg-primary/10 text-primary' : status === 'paused' ? 'bg-warning/10 text-warning' : 'bg-secondary text-muted-foreground';
</script>

<template>
    <Head title="Abonnementen" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-4xl p-4">
            <FlashMessage />
            <GatewayNotice :gateway="gateway" />

            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Abonnementen</h1>
                    <p class="mt-1 text-sm text-muted-foreground">Wie zit op welk tarief, en hoe wordt er betaald.</p>
                </div>

                <Button v-if="!toonFormulier && plans.length" @click="toonFormulier = true">
                    <Plus class="mr-2 size-4" />
                    Abonnement toevoegen
                </Button>
            </div>

            <p v-if="!plans.length" class="mt-6 rounded-xl border border-dashed border-border bg-card/50 p-6 text-center text-sm">
                Je hebt nog geen actieve tarieven.
                <Link href="/plans/create" class="font-medium text-primary underline underline-offset-4">Maak er eerst een aan</Link>.
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
                            class="h-10 rounded-lg border border-input bg-background px-3 text-sm outline-none focus:border-primary"
                        >
                            <option value="">Kies een speler...</option>
                            <option v-for="speler in playersWithoutSubscription" :key="speler.id" :value="speler.id">{{ speler.name }}</option>
                        </select>
                        <p class="text-xs text-muted-foreground">Alleen spelers zonder lopend abonnement.</p>
                        <InputError :message="form.errors.player_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="plan_id">Tarief</Label>
                        <select
                            id="plan_id"
                            v-model="form.plan_id"
                            class="h-10 rounded-lg border border-input bg-background px-3 text-sm outline-none focus:border-primary"
                        >
                            <option value="">Kies een tarief...</option>
                            <option v-for="plan in plans" :key="plan.id" :value="plan.id">
                                {{ plan.name }} — {{ plan.amount }} {{ plan.interval.toLowerCase() }}
                            </option>
                        </select>
                        <InputError :message="form.errors.plan_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="payment_method">Betaalmethode</Label>
                        <select
                            id="payment_method"
                            v-model="form.payment_method"
                            class="h-10 rounded-lg border border-input bg-background px-3 text-sm outline-none focus:border-primary"
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
                </div>

                <p v-if="!gateway.connected" class="rounded-lg bg-secondary px-3 py-2 text-xs text-muted-foreground">
                    Je legt nu alleen vast wie waarop zit. Er wordt niets geïncasseerd zolang {{ gateway.name }} niet is aangesloten.
                </p>

                <div class="flex items-center gap-3">
                    <Button type="submit" :disabled="form.processing || !form.player_id || !form.plan_id">Abonnement vastleggen</Button>
                    <button type="button" class="text-sm text-muted-foreground underline underline-offset-4" @click="toonFormulier = false">
                        Annuleren
                    </button>
                </div>
            </form>

            <div v-if="subscriptions.length" class="mt-4 overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <div
                    v-for="(abonnement, index) in subscriptions"
                    :key="abonnement.id"
                    class="flex flex-wrap items-center gap-4 p-4"
                    :class="index > 0 ? 'border-t border-border' : ''"
                >
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium">{{ abonnement.player ?? 'Onbekende speler' }}</p>
                        <p class="truncate text-xs text-muted-foreground">
                            {{ abonnement.plan ?? 'Tarief verwijderd' }} &middot; sinds {{ abonnement.starts_on }}
                            <span v-if="abonnement.method"> &middot; {{ abonnement.method }}</span>
                            <span v-if="abonnement.ends_on"> &middot; tot {{ abonnement.ends_on }}</span>
                        </p>
                    </div>

                    <div class="shrink-0 text-right">
                        <p class="tabular font-semibold">{{ abonnement.amount }}</p>
                        <p class="text-xs text-muted-foreground">{{ abonnement.interval.toLowerCase() }}</p>
                    </div>

                    <span class="shrink-0 rounded-lg px-2 py-1 text-xs font-medium" :class="kleurVoor(abonnement.status)">
                        {{ abonnement.status_label }}
                    </span>

                    <select
                        :value="abonnement.status"
                        class="shrink-0 rounded-lg border border-input bg-background px-2 py-1.5 text-xs outline-none focus:border-primary"
                        :aria-label="'Status van het abonnement van ' + (abonnement.player ?? 'onbekend')"
                        @change="zetStatus(abonnement, ($event.target as HTMLSelectElement).value)"
                    >
                        <option v-for="(label, waarde) in statuses" :key="waarde" :value="waarde">{{ label }}</option>
                    </select>
                </div>
            </div>

            <div v-else-if="plans.length" class="mt-4 rounded-2xl border border-dashed border-border bg-card/50 p-10 text-center">
                <p class="font-medium">Nog geen abonnementen</p>
                <p class="mt-1 text-sm text-muted-foreground">Koppel een speler aan een tarief om te beginnen.</p>
            </div>
        </div>
    </AppLayout>
</template>
