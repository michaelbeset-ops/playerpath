<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <AuthBase title="Account aanmaken" description="Vul je gegevens in om een account aan te maken">
        <Head title="Account aanmaken" />

        <form @submit.prevent="submit" class="flex flex-col gap-6">
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="name">Naam</Label>
                    <Input id="name" type="text" required autofocus tabindex="1" autocomplete="name" v-model="form.name" placeholder="Voor- en achternaam" />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">E-mailadres</Label>
                    <Input id="email" type="email" required tabindex="2" autocomplete="email" v-model="form.email" placeholder="naam@voorbeeld.nl" />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Wachtwoord</Label>
                    <Input
                        id="password"
                        type="password"
                        required
                        tabindex="3"
                        autocomplete="new-password"
                        v-model="form.password"
                        placeholder="Wachtwoord"
                    />
                    <InputError :message="form.errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Wachtwoord bevestigen</Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        required
                        tabindex="4"
                        autocomplete="new-password"
                        v-model="form.password_confirmation"
                        placeholder="Herhaal je wachtwoord"
                    />
                    <InputError :message="form.errors.password_confirmation" />
                </div>

                <Button type="submit" class="mt-2 w-full" tabindex="5" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                    Account aanmaken
                </Button>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                Heb je al een account?
                <TextLink :href="route('login')" class="underline underline-offset-4" tabindex="6">Inloggen</TextLink>
            </div>
        </form>
    </AuthBase>
</template>
