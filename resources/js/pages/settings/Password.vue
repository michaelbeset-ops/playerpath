<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import SettingsCard from '@/components/SettingsCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { TransitionRoot } from '@headlessui/vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Check, KeyRound } from 'lucide-vue-next';
import { ref } from 'vue';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type BreadcrumbItem } from '@/types';

interface Props {
    className?: string;
}

defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Instellingen', href: '/settings/profile' },
    { title: 'Wachtwoord', href: '/settings/password' },
];

const passwordInput = ref<HTMLInputElement>();
const currentPasswordInput = ref<HTMLInputElement>();

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('password.settings.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: (errors: any) => {
            if (errors.password) {
                form.reset('password', 'password_confirmation');
                if (passwordInput.value instanceof HTMLInputElement) {
                    passwordInput.value.focus();
                }
            }

            if (errors.current_password) {
                form.reset('current_password');
                if (currentPasswordInput.value instanceof HTMLInputElement) {
                    currentPasswordInput.value.focus();
                }
            }
        },
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Wachtwoord wijzigen" />

        <SettingsLayout>
            <SettingsCard title="Wachtwoord wijzigen" description="Kies een lang wachtwoord dat je nergens anders gebruikt." :icon="KeyRound">
                <form class="space-y-5" @submit.prevent="updatePassword">
                    <div class="grid gap-2">
                        <Label for="current_password">Huidig wachtwoord</Label>
                        <Input
                            id="current_password"
                            ref="currentPasswordInput"
                            v-model="form.current_password"
                            type="password"
                            class="h-11"
                            autocomplete="current-password"
                        />
                        <InputError :message="form.errors.current_password" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="password">Nieuw wachtwoord</Label>
                        <Input id="password" ref="passwordInput" v-model="form.password" type="password" class="h-11" autocomplete="new-password" />
                        <InputError :message="form.errors.password" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="password_confirmation">Nog een keer</Label>
                        <Input
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            type="password"
                            class="h-11"
                            autocomplete="new-password"
                        />
                        <InputError :message="form.errors.password_confirmation" />
                    </div>

                    <div class="flex items-center gap-4">
                        <Button class="h-11 px-5" :disabled="form.processing">Wachtwoord opslaan</Button>

                        <TransitionRoot
                            :show="form.recentlySuccessful"
                            enter="transition ease-in-out"
                            enter-from="opacity-0"
                            leave="transition ease-in-out"
                            leave-to="opacity-0"
                        >
                            <p class="flex items-center gap-1.5 text-sm font-medium text-primary">
                                <Check class="size-4" />
                                Opgeslagen
                            </p>
                        </TransitionRoot>
                    </div>
                </form>
            </SettingsCard>
        </SettingsLayout>
    </AppLayout>
</template>
