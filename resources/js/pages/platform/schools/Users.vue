<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import PlatformLayout from '@/layouts/PlatformLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Eye, KeyRound, Mail, Send, UserPlus } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    school: { id: number; name: string; is_active: boolean };
    users: {
        id: number;
        name: string;
        email: string;
        roles: string[];
        is_active: boolean;
        verified: boolean;
        deactivated_at: string | null;
    }[];
    /** Uitgenodigd maar nog niet geactiveerd: die hebben nog geen account. */
    invitations: {
        id: number;
        name: string;
        email: string;
        role: string;
        status: string;
        expires_on: string;
    }[];
    roles: Record<string, string>;
}>();

const toonFormulier = ref(false);

const form = useForm({ name: '', email: '', role: 'eigenaar' });

const voegToe = () =>
    form.post('/beheer/scholen/' + props.school.id + '/gebruikers', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            toonFormulier.value = false;
        },
    });

const wissel = (id: number, naam: string, actief: boolean) => {
    if (confirm(actief ? `${naam} deactiveren? Hij kan dan niet meer inloggen.` : `${naam} weer activeren?`)) {
        router.patch(`/beheer/scholen/${props.school.id}/gebruikers/${id}/status`, {}, { preserveScroll: true });
    }
};

const reset = (id: number, email: string) => {
    if (confirm(`Een e-mail naar ${email} sturen om een nieuw wachtwoord te kiezen?`)) {
        router.post(`/beheer/scholen/${props.school.id}/gebruikers/${id}/wachtwoord`, {}, { preserveScroll: true });
    }
};

// Een account dat nooit is geactiveerd vervangen door een echte uitnodiging
// met de welkomstmail. Zie SchoolUserController::reinvite.
const opnieuwUitnodigen = (id: number, naam: string, email: string) => {
    if (
        confirm(
            `${naam} opnieuw uitnodigen?\n\nHet huidige account (nog niet geactiveerd) wordt vervangen door een uitnodiging. ${email} krijgt een welkomstmail die veertien dagen geldig is. Heeft hij al een wachtwoord gekozen, dan vervalt dat.`,
        )
    ) {
        router.post(`/beheer/scholen/${props.school.id}/gebruikers/${id}/opnieuw-uitnodigen`, {}, { preserveScroll: true });
    }
};

const bekijkAls = (id: number) => {
    if (confirm('Je gaat de app bekijken als deze gebruiker. Dit wordt vastgelegd.')) {
        router.post('/beheer/gebruikers/' + id + '/bekijken');
    }
};
</script>

<template>
    <Head :title="'Gebruikers ' + school.name" />

    <PlatformLayout>
        <Link :href="'/beheer/scholen/' + school.id" class="text-sm text-muted-foreground underline underline-offset-4">
            Terug naar {{ school.name }}
        </Link>

        <div class="mt-2 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Gebruikers</h1>
                <p class="tabular text-sm text-muted-foreground">
                    {{ users.length }} accounts bij {{ school.name }}<template v-if="invitations.length"> &middot; {{ invitations.length }} uitgenodigd</template>
                </p>
            </div>

            <Button v-if="!toonFormulier" @click="toonFormulier = true">
                <UserPlus class="mr-2 size-4" />
                Iemand uitnodigen
            </Button>
        </div>

        <form v-if="toonFormulier" class="mt-4 rounded-xl border border-border bg-card p-5 shadow-sm" @submit.prevent="voegToe">
            <p class="font-medium">Iemand uitnodigen</p>
            <p class="mt-1 text-sm text-muted-foreground">
                Hij krijgt een welkomstmail, activeert daarmee zijn account en kiest zelf een wachtwoord. De uitnodiging is veertien dagen geldig;
                het account ontstaat pas bij activeren.
            </p>

            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                <div class="grid gap-2">
                    <Label for="name">Naam</Label>
                    <Input id="name" v-model="form.name" required />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">E-mailadres</Label>
                    <Input id="email" v-model="form.email" type="email" required />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="role">Rol</Label>
                    <select
                        id="role"
                        v-model="form.role"
                        class="h-10 rounded-lg border border-input bg-background px-3 text-base outline-none focus:border-primary sm:text-sm"
                    >
                        <option v-for="(label, waarde) in roles" :key="waarde" :value="waarde">{{ label }}</option>
                    </select>
                    <InputError :message="form.errors.role" />
                </div>
            </div>

            <div class="mt-4 flex items-center gap-3">
                <Button type="submit" :disabled="form.processing">Uitnodiging sturen</Button>
                <button type="button" class="text-sm text-muted-foreground underline underline-offset-4" @click="toonFormulier = false">
                    Annuleren
                </button>
            </div>
        </form>

        <!-- Uitgenodigd, nog niet geactiveerd -->
        <div v-if="invitations.length" class="mt-4 overflow-hidden rounded-xl border border-border bg-card shadow-sm">
            <p class="border-b border-border px-4 py-2 text-xs font-medium uppercase tracking-wide text-muted-foreground">Uitgenodigd</p>
            <div
                v-for="(rij, index) in invitations"
                :key="rij.id"
                class="flex flex-col gap-1 p-4 sm:flex-row sm:items-center sm:gap-4"
                :class="index > 0 ? 'border-t border-border' : ''"
            >
                <div class="min-w-0 flex-1">
                    <p class="flex flex-wrap items-center gap-2 font-medium">
                        <Mail class="size-4 text-muted-foreground" />
                        {{ rij.name }}
                        <span class="rounded bg-secondary px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground">{{ rij.role }}</span>
                        <span
                            class="rounded px-1.5 py-0.5 text-[10px] font-medium"
                            :class="rij.status === 'verlopen' ? 'bg-warning/15 text-warning' : 'bg-primary/10 text-primary'"
                        >
                            {{ rij.status === 'verlopen' ? 'verlopen' : 'wacht op activeren' }}
                        </span>
                    </p>
                    <p class="break-all text-xs text-muted-foreground">{{ rij.email }} &middot; geldig tot {{ rij.expires_on }}</p>
                </div>
            </div>
        </div>

        <div v-if="users.length" class="mt-4 overflow-hidden rounded-xl border border-border bg-card shadow-sm">
            <div
                v-for="(user, index) in users"
                :key="user.id"
                class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:gap-4"
                :class="[index > 0 ? 'border-t border-border' : '', user.is_active ? '' : 'opacity-60']"
            >
                <div class="min-w-0 flex-1">
                    <p class="flex flex-wrap items-center gap-2 font-medium">
                        {{ user.name }}
                        <span
                            v-for="rol in user.roles"
                            :key="rol"
                            class="rounded bg-secondary px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground"
                        >
                            {{ rol }}
                        </span>
                        <span v-if="!user.is_active" class="rounded bg-warning/15 px-1.5 py-0.5 text-[10px] font-medium text-warning">
                            gedeactiveerd
                        </span>
                    </p>
                    <p class="truncate text-xs text-muted-foreground">
                        {{ user.email }}
                        <!-- Bevestigd = via de link in zijn mail een wachtwoord gekozen of een uitnodiging geactiveerd. -->
                        <template v-if="!user.verified"> &middot; nog niet geactiveerd</template>
                        <template v-if="user.deactivated_at"> &middot; sinds {{ user.deactivated_at }}</template>
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <!-- Nog nooit geactiveerd: de welkomstmail opnieuw, in plaats van een wachtwoordmail. -->
                    <button
                        v-if="!user.verified"
                        type="button"
                        class="inline-flex h-8 items-center gap-1.5 rounded-lg bg-primary px-2.5 text-xs font-semibold text-primary-foreground hover:opacity-90"
                        @click="opnieuwUitnodigen(user.id, user.name, user.email)"
                    >
                        <Send class="size-3.5" />
                        Opnieuw uitnodigen
                    </button>

                    <button
                        v-if="user.is_active"
                        type="button"
                        class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-border px-2.5 text-xs font-medium hover:border-primary"
                        @click="bekijkAls(user.id)"
                    >
                        <Eye class="size-3.5" />
                        Bekijk als
                    </button>

                    <button
                        type="button"
                        class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-border px-2.5 text-xs font-medium hover:border-primary"
                        @click="reset(user.id, user.email)"
                    >
                        <KeyRound class="size-3.5" />
                        Wachtwoord
                    </button>

                    <button
                        type="button"
                        class="h-8 rounded-lg border border-border px-2.5 text-xs font-medium hover:border-destructive hover:text-destructive"
                        @click="wissel(user.id, user.name, user.is_active)"
                    >
                        {{ user.is_active ? 'Deactiveren' : 'Activeren' }}
                    </button>
                </div>
            </div>
        </div>

        <div v-else-if="!invitations.length" class="mt-4 rounded-2xl border border-dashed border-border bg-card/50 p-10 text-center">
            <p class="font-medium">Nog geen accounts</p>
            <p class="mt-1 text-sm text-muted-foreground">Nodig een eigenaar uit zodat deze school kan beginnen.</p>
        </div>
    </PlatformLayout>
</template>
