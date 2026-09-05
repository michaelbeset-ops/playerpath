<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    plan: { id: number; name: string; description: string | null; amount: string; interval: string; is_active: boolean } | null;
    intervals: Record<string, string>;
}>();

const bewerken = computed(() => props.plan !== null);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Tarieven', href: '/plans' },
    bewerken.value
        ? { title: props.plan!.name, href: '/plans/' + props.plan!.id + '/edit' }
        : { title: 'Nieuw tarief', href: '/plans/create' },
]);

const form = useForm({
    name: props.plan?.name ?? '',
    description: props.plan?.description ?? '',
    amount: props.plan?.amount ?? '',
    interval: props.plan?.interval ?? 'monthly',
    is_active: props.plan?.is_active ?? true,
});

const opslaan = () => {
    if (bewerken.value) {
        form.put('/plans/' + props.plan!.id);
    } else {
        form.post('/plans');
    }
};

const verwijderen = () => {
    if (confirm('Dit tarief verwijderen? Lopende abonnementen lopen gewoon door met hun eigen bedrag.')) {
        router.delete('/plans/' + props.plan!.id);
    }
};
</script>

<template>
    <Head :title="bewerken ? 'Tarief bewerken' : 'Tarief toevoegen'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-xl p-4">
            <h1 class="text-2xl font-semibold tracking-tight">
                {{ bewerken ? 'Tarief bewerken' : 'Tarief toevoegen' }}
            </h1>

            <form class="mt-6 space-y-5 rounded-xl border border-border bg-card p-5 shadow-sm" @submit.prevent="opslaan">
                <div class="grid gap-2">
                    <Label for="name">Naam</Label>
                    <Input id="name" v-model="form.name" required autofocus placeholder="Keeperstraining" />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="description">Omschrijving <span class="text-muted-foreground">(optioneel)</span></Label>
                    <Input id="description" v-model="form.description" placeholder="Wekelijkse keeperstraining, inclusief materiaal" />
                    <InputError :message="form.errors.description" />
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="amount">Bedrag</Label>
                        <div class="relative">
                            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-muted-foreground">€</span>
                            <Input id="amount" v-model="form.amount" required inputmode="decimal" placeholder="27,50" class="pl-7" />
                        </div>
                        <p class="text-xs text-muted-foreground">Komma of punt mag allebei.</p>
                        <InputError :message="form.errors.amount" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="interval">Frequentie</Label>
                        <select
                            id="interval"
                            v-model="form.interval"
                            class="h-10 rounded-lg border border-input bg-background px-3 text-sm outline-none focus:border-primary"
                        >
                            <option v-for="(label, waarde) in intervals" :key="waarde" :value="waarde">{{ label }}</option>
                        </select>
                        <InputError :message="form.errors.interval" />
                    </div>
                </div>

                <label class="flex items-center gap-3 rounded-lg border border-border p-3">
                    <input v-model="form.is_active" type="checkbox" class="size-4 accent-[hsl(var(--primary))]" />
                    <span class="text-sm">
                        <span class="font-medium">Actief tarief</span>
                        <span class="block text-xs text-muted-foreground">Alleen actieve tarieven kun je aan een speler koppelen.</span>
                    </span>
                </label>

                <div class="flex items-center gap-3 pt-1">
                    <Button type="submit" :disabled="form.processing">
                        <LoaderCircle v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                        {{ bewerken ? 'Wijzigingen opslaan' : 'Tarief toevoegen' }}
                    </Button>

                    <Link href="/plans" class="text-sm text-muted-foreground underline underline-offset-4 hover:text-foreground">
                        Annuleren
                    </Link>
                </div>
            </form>

            <p v-if="bewerken" class="mt-4 rounded-lg bg-secondary px-3 py-2 text-xs text-muted-foreground">
                Pas je het bedrag aan, dan geldt dat alleen voor nieuwe abonnementen. Lopende abonnementen houden het bedrag waarop ze zijn
                afgesloten — anders zou een tariefwijziging met terugwerkende kracht ingaan.
            </p>

            <div v-if="bewerken" class="mt-4 rounded-xl border border-destructive/25 bg-destructive/5 p-5">
                <p class="font-medium text-destructive">Tarief verwijderen</p>
                <p class="mt-1 text-sm text-muted-foreground">Lopende abonnementen blijven bestaan met hun eigen bedrag.</p>
                <Button variant="destructive" class="mt-4" @click="verwijderen">
                    <Trash2 class="mr-2 size-4" />
                    Verwijderen
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
