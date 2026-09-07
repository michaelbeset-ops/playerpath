<script setup lang="ts">
import { TransitionRoot } from '@headlessui/vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Camera, Check, ShieldAlert, UserRound } from 'lucide-vue-next';

import DeleteUser from '@/components/DeleteUser.vue';
import InputError from '@/components/InputError.vue';
import PhotoUpload from '@/components/PhotoUpload.vue';
import SettingsCard from '@/components/SettingsCard.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem, type SharedData, type User } from '@/types';

interface Props {
    mustVerifyEmail: boolean;
    status?: string;
    className?: string;
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Instellingen', href: '/settings/profile' }];

const page = usePage<SharedData>();
const user = page.props.auth.user as User;

const form = useForm({
    name: user.name,
    email: user.email,
});

const submit = () => {
    form.patch(route('profile.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Instellingen" />

        <SettingsLayout>
            <!-- De foto eerst: dat is wat op de kaart en in de balk staat, en
                 het is de instelling die mensen het vaakst komen doen. -->
            <SettingsCard title="Je foto" description="Staat in de balk bovenin en bij je naam." :icon="Camera">
                <PhotoUpload :name="user.name" :photo="user.photo_url ?? null" :action="'/users/' + user.id + '/photo'" />
            </SettingsCard>

            <SettingsCard title="Je gegevens" description="Je naam en het e-mailadres waarmee je inlogt." :icon="UserRound">
                <form class="space-y-5" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="name">Naam</Label>
                        <Input id="name" v-model="form.name" class="h-11" required autocomplete="name" placeholder="Voor- en achternaam" />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">E-mailadres</Label>
                        <Input
                            id="email"
                            v-model="form.email"
                            type="email"
                            class="h-11"
                            required
                            autocomplete="username"
                            placeholder="naam@voorbeeld.nl"
                        />
                        <InputError :message="form.errors.email" />
                    </div>

                    <div v-if="mustVerifyEmail && !user.email_verified_at" class="rounded-lg bg-secondary px-3 py-2 text-sm">
                        Je e-mailadres is nog niet bevestigd.
                        <Link
                            :href="route('verification.send')"
                            method="post"
                            as="button"
                            class="font-medium text-primary underline underline-offset-4"
                        >
                            Stuur de bevestigingsmail opnieuw.
                        </Link>

                        <p v-if="status === 'verification-link-sent'" class="mt-1 font-medium text-primary">
                            We hebben een nieuwe bevestigingslink gestuurd.
                        </p>
                    </div>

                    <div class="flex items-center gap-4">
                        <Button class="h-11 px-5" :disabled="form.processing">Opslaan</Button>

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

            <SettingsCard title="Account verwijderen" :icon="ShieldAlert" tone="danger">
                <DeleteUser />
            </SettingsCard>
        </SettingsLayout>
    </AppLayout>
</template>
