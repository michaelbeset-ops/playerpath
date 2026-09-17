<script setup lang="ts">
import Avatar from '@/components/Avatar.vue';
import InputError from '@/components/InputError.vue';
import InviteForm, { type Uitnodiging } from '@/components/onboarding/InviteForm.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Input } from '@/components/ui/input';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ClipboardList, KeyRound, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

defineProps<{
    trainers: {
        id: number;
        name: string;
        photo: string | null;
        email: string | null;
        invited_email: string | null;
        is_owner: boolean;
        trainings_count: number;
        reports_count: number;
    }[];
    can: { manageAccounts: boolean };
    invitations: Uitnodiging[];
    invitationDays: number;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Mijn bedrijf', href: '/staff' },
    { title: 'Personeel', href: '/staff' },
];

const toonFormulier = ref(false);

// Waarom verwijderen niet kon (je eigen account, de eigenaar).
const page = usePage();
const verwijderFout = computed(() => (page.props.errors as Record<string, string | undefined>).user);

// Een trainer zonder inlog (uit een import) een inlog geven: één regel met
// een adres, en hij krijgt een welkomstmail voor zijn bestaande account.
const inlogVoor = ref<number | null>(null);
const inlogForm = useForm({ email: '' });

const openInlog = (id: number, adres: string | null) => {
    inlogVoor.value = id;
    inlogForm.reset();
    inlogForm.clearErrors();
    inlogForm.email = adres ?? '';
};

const geefInlog = (id: number) => {
    inlogForm.post('/staff/trainers/' + id + '/inlog', {
        preserveScroll: true,
        onSuccess: () => (inlogVoor.value = null),
    });
};

const verwijder = (id: number, naam: string) => {
    if (confirm(`Het account van ${naam} verwijderen? Zijn rapporten blijven bewaard.`)) {
        router.delete('/staff/trainers/' + id, { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Personeel" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-4xl p-4" data-tour="business">

            <h1 class="text-2xl font-semibold tracking-tight">Personeel</h1>
            <p class="mt-1 text-sm text-muted-foreground">Wie er training geeft bij jouw school.</p>

            <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-muted-foreground">
                    <span class="tabular">{{ trainers.length }}</span>
                    {{ trainers.length === 1 ? 'medewerker' : 'medewerkers' }}
                </p>

                <Button v-if="can.manageAccounts && !toonFormulier && !invitations.length" @click="toonFormulier = true">
                    <Plus class="mr-2 size-4" />
                    Trainer uitnodigen
                </Button>
            </div>

            <InviteForm
                v-if="toonFormulier || invitations.length"
                class="mt-3"
                role="trainer"
                :invitations="invitations"
                :valid-days="invitationDays"
                title="Trainers uitnodigen"
            />

            <InputError class="mt-4" :message="verwijderFout" />

            <div v-if="trainers.length" class="mt-4 overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <div v-for="(trainer, index) in trainers" :key="trainer.id" :class="index > 0 ? 'border-t border-border' : ''">
                <div class="flex items-center gap-4 p-4">
                    <Avatar :name="trainer.name" :photo="trainer.photo" size="size-11" />

                    <div class="min-w-0 flex-1">
                        <!-- truncate op de flexrij zelf kapte het label "eigenaar"
                             af tot een streepje: de naam is een los tekstknooppunt
                             en krimpt niet, dus het pilletje ernaast kreeg de
                             klappen. Nu krimpt de naam en blijft het label heel. -->
                        <p class="flex flex-wrap items-center gap-2 font-medium">
                            <span class="min-w-0">{{ trainer.name }}</span>
                            <span v-if="trainer.is_owner" class="shrink-0 rounded bg-secondary px-1.5 py-0.5 text-[10px] text-muted-foreground">
                                eigenaar
                            </span>
                        </p>
                        <!-- Een trainer uit een import heeft soms nog geen adres, en dus geen inlog. -->
                        <p class="truncate text-xs text-muted-foreground">
                            <template v-if="trainer.email">{{ trainer.email }}</template>
                            <template v-else-if="trainer.invited_email">Uitgenodigd op {{ trainer.invited_email }}</template>
                            <template v-else>Nog geen inlog</template>
                        </p>
                        <p class="tabular mt-1 flex flex-wrap gap-x-3 text-xs text-muted-foreground">
                            <span>{{ trainer.trainings_count }} {{ trainer.trainings_count === 1 ? 'training' : 'trainingen' }}</span>
                            <span class="inline-flex items-center gap-1">
                                <ClipboardList class="size-3" />
                                {{ trainer.reports_count }} {{ trainer.reports_count === 1 ? 'rapport' : 'rapporten' }}
                            </span>
                        </p>
                    </div>

                    <button
                        v-if="can.manageAccounts && !trainer.email && inlogVoor !== trainer.id"
                        type="button"
                        class="inline-flex min-h-11 shrink-0 items-center gap-1.5 rounded-lg border border-border px-3 text-xs font-medium hover:border-primary"
                        @click="openInlog(trainer.id, trainer.invited_email)"
                    >
                        <KeyRound class="size-3.5" />
                        {{ trainer.invited_email ? 'Opnieuw sturen' : 'Inlog geven' }}
                    </button>

                    <button
                        v-if="can.manageAccounts && !trainer.is_owner"
                        type="button"
                        class="flex size-11 shrink-0 items-center justify-center rounded-lg text-muted-foreground transition hover:bg-secondary hover:text-destructive"
                        :aria-label="'Account van ' + trainer.name + ' verwijderen'"
                        @click="verwijder(trainer.id, trainer.name)"
                    >
                        <Trash2 class="size-4" />
                    </button>
                </div>

                <form
                    v-if="inlogVoor === trainer.id"
                    class="flex flex-col gap-2 px-4 pb-4 sm:flex-row sm:items-start"
                    @submit.prevent="geefInlog(trainer.id)"
                >
                    <div class="min-w-0 flex-1">
                        <Input
                            v-model="inlogForm.email"
                            type="email"
                            required
                            placeholder="E-mailadres van de trainer"
                            :aria-label="'E-mailadres van ' + trainer.name"
                        />
                        <InputError class="mt-1" :message="inlogForm.errors.email" />
                        <p class="mt-1 text-xs text-muted-foreground">Hij krijgt een welkomstmail en kiest zelf een wachtwoord. Zijn trainingen blijven van hem.</p>
                    </div>
                    <div class="flex gap-2">
                        <Button type="submit" :disabled="inlogForm.processing">Versturen</Button>
                        <Button type="button" variant="outline" @click="inlogVoor = null">Annuleren</Button>
                    </div>
                </form>
                </div>
            </div>

            <div v-else class="mt-4 rounded-2xl border border-dashed border-border bg-card/50 p-10 text-center">
                <p class="font-medium">Nog geen trainers</p>
                <p class="mt-1 text-sm text-muted-foreground">Nodig er een uit om trainingen en rapporten te verdelen.</p>
            </div>
        </div>
    </AppLayout>
</template>
