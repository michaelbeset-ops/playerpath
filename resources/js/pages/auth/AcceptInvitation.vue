<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * De uitnodiging inwisselen: kies een wachtwoord, en je bent binnen.
 *
 * Eén veld, want alles wat we al weten hoeven we niet nog eens te vragen: naam,
 * e-mailadres, school en rol komen uit de uitnodiging en staan er alleen om te
 * laten zien dat het klopt. Elk extra veld hier is een reden om het later te
 * doen, en later is nooit.
 *
 * Na het opslaan ben je meteen ingelogd en sta je op je eigen dashboard. Iemand
 * die net een wachtwoord koos alsnog een inlogscherm voorschotelen is precies
 * waar mensen afhaken.
 *
 * Een verlopen of gebruikte link geeft geen foutpagina maar uitleg: de
 * ontvanger heeft niets fout gedaan, hij was alleen te laat.
 */
const props = defineProps<{
    token: string;
    invitation: {
        name: string;
        email: string;
        role: string;
        school: string;
        logo: string | null;
        children: string[];
    } | null;
}>();

const form = useForm({ password: '', password_confirmation: '' });

const isOuder = computed(() => props.invitation?.role === 'ouder');

const belofte = computed(() => {
    if (!props.invitation) {
        return '';
    }

    // Een eigenaar komt via platformbeheer: die richt de school in, en krijgt
    // na het activeren meteen het welkom en de rondleiding.
    if (props.invitation.role === 'eigenaar') {
        return 'Je school staat klaar. Kies een wachtwoord, dan laten we je in een paar minuten zien hoe alles werkt.';
    }

    if (!isOuder.value) {
        return 'Je ziet je eigen trainingen, vinkt aanwezigheid af en vult na afloop de rapporten in.';
    }

    const kinderen = props.invitation.children;
    const wie = kinderen.length ? kinderen.join(' en ') : 'je kind';

    return `Je ziet de spelerskaart en de voortgang van ${wie}, wanneer de trainingen zijn en wat er nog openstaat.`;
});

const opslaan = () => form.post('/uitnodiging/' + props.token, { onFinish: () => form.reset('password', 'password_confirmation') });
</script>

<template>
    <Head title="Uitnodiging" />

    <AuthLayout
        :title="invitation ? 'Welkom bij ' + invitation.school : 'Deze uitnodiging werkt niet meer'"
        :description="invitation ? belofte : 'Hij is verlopen of al gebruikt.'"
    >
        <!-- Het logo van de school, niet dat van PlayerPath: de ontvanger is
             uitgenodigd door zijn school en kent ons niet. -->
        <img v-if="invitation?.logo" :src="invitation.logo" :alt="invitation.school" class="mx-auto mb-6 max-h-16 max-w-[12rem] object-contain" />

        <form v-if="invitation" class="flex flex-col gap-6" @submit.prevent="opslaan">
            <div class="rounded-xl border border-border bg-card/50 p-3 text-sm">
                <p class="font-medium">{{ invitation.name }}</p>
                <p class="text-muted-foreground">{{ invitation.email }}</p>
            </div>

            <div class="grid gap-2">
                <label for="password" class="text-sm font-medium">Kies een wachtwoord</label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    autocomplete="new-password"
                    required
                    autofocus
                    class="h-11 w-full rounded-lg border border-input bg-background px-3 text-base outline-none focus:border-primary sm:text-sm"
                />
                <InputError :message="form.errors.password" />
            </div>

            <div class="grid gap-2">
                <label for="password_confirmation" class="text-sm font-medium">Nog een keer</label>
                <input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    required
                    class="h-11 w-full rounded-lg border border-input bg-background px-3 text-base outline-none focus:border-primary sm:text-sm"
                />
                <InputError :message="form.errors.password_confirmation" />
            </div>

            <button
                type="submit"
                class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground transition hover:opacity-90 disabled:opacity-60"
                :disabled="form.processing"
            >
                <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                Account activeren
            </button>
        </form>

        <div v-else class="text-center">
            <p class="text-sm text-muted-foreground">Vraag je school om een nieuwe uitnodiging. Heb je al een account? Dan kun je gewoon inloggen.</p>

            <Link
                href="/login"
                class="mt-5 inline-flex min-h-12 items-center justify-center rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
            >
                Naar inloggen
            </Link>
        </div>
    </AuthLayout>
</template>
