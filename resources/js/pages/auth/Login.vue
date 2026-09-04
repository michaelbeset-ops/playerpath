<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <AuthBase title="Inloggen" description="Vul je e-mailadres en wachtwoord in om verder te gaan">
        <Head title="Inloggen" />

        <div v-if="status" class="mb-4 text-center text-sm font-medium text-primary">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="flex flex-col gap-6">
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="email">E-mailadres</Label>
                    <Input
                        id="email"
                        type="email"
                        required
                        autofocus
                        tabindex="1"
                        autocomplete="email"
                        v-model="form.email"
                        placeholder="naam@voorbeeld.nl"
                    />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="grid gap-2">
                    <div class="flex items-center justify-between">
                        <Label for="password">Wachtwoord</Label>
                        <TextLink v-if="canResetPassword" :href="route('password.request')" class="text-sm" tabindex="5"> Wachtwoord vergeten? </TextLink>
                    </div>
                    <Input
                        id="password"
                        type="password"
                        required
                        tabindex="2"
                        autocomplete="current-password"
                        v-model="form.password"
                        placeholder="Wachtwoord"
                    />
                    <InputError :message="form.errors.password" />
                </div>

                <div class="flex items-center justify-between" tabindex="3">
                    <Label for="remember" class="flex items-center space-x-3">
                        <Checkbox id="remember" v-model:checked="form.remember" tabindex="4" />
                        <span>Ingelogd blijven</span>
                    </Label>
                </div>

                <Button type="submit" class="mt-4 w-full" tabindex="4" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                    Inloggen
                </Button>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                Nog geen account?
                <TextLink :href="route('register')" :tabindex="5">Maak er een aan</TextLink>
            </div>
        </form>
    </AuthBase>
</template>
