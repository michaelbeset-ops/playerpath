<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { MapPin, Pencil, Plus } from 'lucide-vue-next';
import { ref } from 'vue';

interface Locatie {
    id: number;
    name: string;
    address: string | null;
    note: string | null;
    is_active: boolean;
    trainings_count: number;
    products_count: number;
    slots_count: number;
}

defineProps<{
    locations: Locatie[];
    canManage: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Locaties', href: '/locaties' }];

const nieuw = useForm({ name: '', address: '', note: '', is_active: true as boolean });

const bewerkId = ref<number | null>(null);
const bewerk = useForm({ name: '', address: '', note: '', is_active: true as boolean });

const voegToe = () =>
    nieuw.post('/locaties', {
        preserveScroll: true,
        onSuccess: () => nieuw.reset(),
    });

const openBewerken = (locatie: Locatie) => {
    bewerkId.value = locatie.id;
    bewerk.name = locatie.name;
    bewerk.address = locatie.address ?? '';
    bewerk.note = locatie.note ?? '';
    bewerk.is_active = locatie.is_active;
};

const slaOp = () =>
    bewerk.patch('/locaties/' + bewerkId.value, {
        preserveScroll: true,
        onSuccess: () => (bewerkId.value = null),
    });
</script>

<template>
    <Head title="Locaties" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl p-4">

            <h1 class="text-2xl font-semibold tracking-tight">Locaties</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Waar je traint. Je kiest ze bij een training, een aanbod of een privémoment, zodat je ze niet elke keer opnieuw intikt.
            </p>

            <!-- Toevoegen -->
            <form v-if="canManage" class="mt-5 space-y-4 rounded-xl border border-border bg-card p-4 shadow-sm sm:p-5" @submit.prevent="voegToe">
                <p class="font-medium">Locatie toevoegen</p>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="name">Naam</Label>
                        <Input id="name" v-model="nieuw.name" required placeholder="Sportpark De Vliert" class="h-11 sm:h-10" />
                        <InputError :message="nieuw.errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="address">Adres <span class="text-muted-foreground">(optioneel)</span></Label>
                        <Input id="address" v-model="nieuw.address" placeholder="Vlierweg 1, Den Bosch" class="h-11 sm:h-10" />
                        <InputError :message="nieuw.errors.address" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="note">Notitie <span class="text-muted-foreground">(optioneel)</span></Label>
                    <Input id="note" v-model="nieuw.note" placeholder="Kunstgras, kleedkamer 3" class="h-11 sm:h-10" />
                    <InputError :message="nieuw.errors.note" />
                </div>

                <Button type="submit" class="h-11 w-full sm:w-auto" :disabled="nieuw.processing">
                    <Plus class="mr-2 size-4" />
                    Toevoegen
                </Button>
            </form>

            <!-- Wat er staat -->
            <div v-if="locations.length" class="mt-6 space-y-2">
                <div v-for="locatie in locations" :key="locatie.id" class="rounded-xl border border-border bg-card p-4 shadow-sm">
                    <!-- Bewerken gebeurt in de rij zelf: een school heeft er twee
                         of drie, daar hoort geen eigen pagina bij. -->
                    <form v-if="bewerkId === locatie.id" class="space-y-3" @submit.prevent="slaOp">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="grid gap-1">
                                <Label :for="'name-' + locatie.id" class="sr-only">Naam</Label>
                                <Input :id="'name-' + locatie.id" v-model="bewerk.name" required placeholder="Naam" class="h-11 sm:h-10" />
                                <InputError :message="bewerk.errors.name" />
                            </div>
                            <div class="grid gap-1">
                                <Label :for="'address-' + locatie.id" class="sr-only">Adres</Label>
                                <Input :id="'address-' + locatie.id" v-model="bewerk.address" placeholder="Adres" class="h-11 sm:h-10" />
                                <InputError :message="bewerk.errors.address" />
                            </div>
                        </div>
                        <div class="grid gap-1">
                            <Label :for="'note-' + locatie.id" class="sr-only">Notitie</Label>
                            <Input :id="'note-' + locatie.id" v-model="bewerk.note" placeholder="Notitie" class="h-11 sm:h-10" />
                            <InputError :message="bewerk.errors.note" />
                        </div>

                        <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-border p-3">
                            <input v-model="bewerk.is_active" type="checkbox" class="size-4 shrink-0 rounded border-input accent-primary" />
                            <span class="text-sm">
                                <span class="block font-medium">In gebruik</span>
                                <span class="block text-xs text-muted-foreground">
                                    Uitgezet kun je hem niet meer kiezen; wat er al staat blijft kloppen.
                                </span>
                            </span>
                        </label>

                        <div class="flex flex-wrap items-center gap-3">
                            <Button type="submit" class="h-11 sm:h-10" :disabled="bewerk.processing">Opslaan</Button>
                            <button
                                type="button"
                                class="inline-flex min-h-11 items-center text-sm text-muted-foreground underline underline-offset-4"
                                @click="bewerkId = null"
                            >
                                Annuleren
                            </button>
                        </div>
                    </form>

                    <div v-else class="flex items-start gap-3">
                        <span
                            class="flex size-10 shrink-0 items-center justify-center rounded-lg"
                            :class="locatie.is_active ? 'bg-primary/10 text-primary' : 'bg-secondary text-muted-foreground/70'"
                        >
                            <MapPin class="size-5" />
                        </span>

                        <div class="min-w-0 flex-1">
                            <p class="flex flex-wrap items-center gap-2 font-medium">
                                {{ locatie.name }}
                                <span
                                    v-if="!locatie.is_active"
                                    class="rounded bg-secondary px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground"
                                >
                                    niet in gebruik
                                </span>
                            </p>
                            <p v-if="locatie.address" class="text-xs text-muted-foreground">{{ locatie.address }}</p>
                            <p v-if="locatie.note" class="text-xs text-muted-foreground">{{ locatie.note }}</p>

                            <p class="tabular mt-1 flex flex-wrap gap-x-3 text-xs text-muted-foreground">
                                <span v-if="locatie.trainings_count">{{ locatie.trainings_count }} trainingen</span>
                                <span v-if="locatie.products_count">{{ locatie.products_count }} in het aanbod</span>
                                <span v-if="locatie.slots_count">{{ locatie.slots_count }} momenten</span>
                                <span v-if="!locatie.trainings_count && !locatie.products_count && !locatie.slots_count">nog niet gebruikt</span>
                            </p>
                        </div>

                        <button
                            v-if="canManage"
                            type="button"
                            class="flex size-11 shrink-0 items-center justify-center rounded-lg text-muted-foreground transition hover:bg-secondary hover:text-foreground"
                            :aria-label="locatie.name + ' bewerken'"
                            @click="openBewerken(locatie)"
                        >
                            <Pencil class="size-4" />
                        </button>
                    </div>
                </div>
            </div>

            <div v-else class="mt-6 rounded-2xl border border-dashed border-border bg-card/50 p-8 text-center">
                <p class="font-medium">Nog geen locaties</p>
                <p class="mt-1 text-sm text-muted-foreground">Zet je vaste sportpark erbij; daarna kies je hem overal uit een lijst.</p>
            </div>
        </div>
    </AppLayout>
</template>
