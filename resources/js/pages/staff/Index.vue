<script setup lang="ts">
import FlashMessage from '@/components/FlashMessage.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ClipboardList, Plus, Trash2, UserCog } from 'lucide-vue-next';
import { ref } from 'vue';

defineProps<{
    trainers: { id: number; name: string; email: string; is_owner: boolean; trainings_count: number; reports_count: number }[];
    can: { manageAccounts: boolean };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Mijn bedrijf', href: '/staff' },
    { title: 'Personeel', href: '/staff' },
];

const toonFormulier = ref(false);

const form = useForm({ name: '', email: '' });

const nodigUit = () =>
    form.post('/staff/trainers', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            toonFormulier.value = false;
        },
    });

const verwijder = (id: number, naam: string) => {
    if (confirm(`Het account van ${naam} verwijderen? Zijn rapporten blijven bewaard.`)) {
        router.delete('/staff/trainers/' + id, { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Personeel" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-4xl p-4">
            <FlashMessage />

            <h1 class="text-2xl font-semibold tracking-tight">Personeel</h1>
            <p class="mt-1 text-sm text-muted-foreground">Wie er training geeft bij jouw school.</p>

            <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-muted-foreground">
                    <span class="tabular">{{ trainers.length }}</span>
                    {{ trainers.length === 1 ? 'medewerker' : 'medewerkers' }}
                </p>

                <Button v-if="can.manageAccounts && !toonFormulier" @click="toonFormulier = true">
                    <Plus class="mr-2 size-4" />
                    Trainer uitnodigen
                </Button>
            </div>

            <form v-if="toonFormulier" class="mt-3 space-y-4 rounded-xl border border-border bg-card p-5 shadow-sm" @submit.prevent="nodigUit">
                <p class="text-sm font-medium">Nieuwe trainer uitnodigen</p>
                <p class="text-xs text-muted-foreground">
                    De trainer krijgt een e-mail om zelf een wachtwoord te kiezen. Jij hoeft er geen te bedenken.
                </p>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="trainer_name">Naam</Label>
                        <Input id="trainer_name" v-model="form.name" required />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="trainer_email">E-mailadres</Label>
                        <Input id="trainer_email" v-model="form.email" type="email" required placeholder="naam@voorbeeld.nl" />
                        <InputError :message="form.errors.email" />
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <Button type="submit" :disabled="form.processing">Uitnodigen</Button>
                    <button type="button" class="text-sm text-muted-foreground underline underline-offset-4" @click="toonFormulier = false">
                        Annuleren
                    </button>
                </div>
            </form>

            <div v-if="trainers.length" class="mt-4 overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <div
                    v-for="(trainer, index) in trainers"
                    :key="trainer.id"
                    class="flex items-center gap-4 p-4"
                    :class="index > 0 ? 'border-t border-border' : ''"
                >
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <UserCog class="size-5" />
                    </span>

                    <div class="min-w-0 flex-1">
                        <p class="flex items-center gap-2 truncate font-medium">
                            {{ trainer.name }}
                            <span v-if="trainer.is_owner" class="rounded bg-secondary px-1.5 py-0.5 text-[10px] text-muted-foreground">
                                eigenaar
                            </span>
                        </p>
                        <p class="truncate text-xs text-muted-foreground">{{ trainer.email }}</p>
                        <p class="tabular mt-1 flex flex-wrap gap-x-3 text-xs text-muted-foreground">
                            <span>{{ trainer.trainings_count }} {{ trainer.trainings_count === 1 ? 'training' : 'trainingen' }}</span>
                            <span class="inline-flex items-center gap-1">
                                <ClipboardList class="size-3" />
                                {{ trainer.reports_count }} {{ trainer.reports_count === 1 ? 'rapport' : 'rapporten' }}
                            </span>
                        </p>
                    </div>

                    <button
                        v-if="can.manageAccounts && !trainer.is_owner"
                        type="button"
                        class="shrink-0 rounded-lg p-2 text-muted-foreground transition hover:bg-secondary hover:text-destructive"
                        :aria-label="'Account van ' + trainer.name + ' verwijderen'"
                        @click="verwijder(trainer.id, trainer.name)"
                    >
                        <Trash2 class="size-4" />
                    </button>
                </div>
            </div>

            <div v-else class="mt-4 rounded-2xl border border-dashed border-border bg-card/50 p-10 text-center">
                <p class="font-medium">Nog geen trainers</p>
                <p class="mt-1 text-sm text-muted-foreground">Nodig er een uit om trainingen en rapporten te verdelen.</p>
            </div>
        </div>
    </AppLayout>
</template>
