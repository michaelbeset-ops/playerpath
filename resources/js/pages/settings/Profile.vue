<script setup lang="ts">
import { TransitionRoot } from '@headlessui/vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

import DeleteUser from '@/components/DeleteUser.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import PhotoUpload from '@/components/PhotoUpload.vue';
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

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Profielinstellingen',
        href: '/settings/profile',
    },
];

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
        <Head title="Profielinstellingen" />

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <HeadingSmall title="Profielgegevens" description="Pas je foto, naam en e-mailadres aan" />

                <PhotoUpload :name="user.name" :photo="user.photo_url ?? null" :action="'/users/' + user.id + '/photo'" />

                <form @submit.prevent="submit" class="space-y-6">
                    <div class="grid gap-2">
                        <Label for="name">Naam</Label>
                        <Input
                            id="name"
                            class="mt-1 block w-full"
                            v-model="form.name"
                            required
                            autocomplete="name"
                            placeholder="Voor- en achternaam"
                        />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">E-mailadres</Label>
                        <Input
                            id="email"
                            type="email"
                            class="mt-1 block w-full"
                            v-model="form.email"
                            required
                            autocomplete="username"
                            placeholder="naam@voorbeeld.nl"
                        />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <div v-if="mustVerifyEmail && !user.email_verified_at">
                        <p class="mt-2 text-sm text-muted-foreground">
                            Je e-mailadres is nog niet bevestigd.
                            <Link
                                :href="route('verification.send')"
                                method="post"
                                as="button"
                                class="focus:outline-hidden rounded-md text-sm text-foreground underline hover:text-primary focus:ring-2 focus:ring-offset-2"
                            >
                                Klik hier om de bevestigingsmail opnieuw te versturen.
                            </Link>
                        </p>

                        <div v-if="status === 'verification-link-sent'" class="mt-2 text-sm font-medium text-primary">
                            We hebben een nieuwe bevestigingslink naar je e-mailadres gestuurd.
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <Button :disabled="form.processing">Opslaan</Button>

                        <TransitionRoot
                            :show="form.recentlySuccessful"
                            enter="transition ease-in-out"
                            enter-from="opacity-0"
                            leave="transition ease-in-out"
                            leave-to="opacity-0"
                        >
                            <p class="text-sm text-muted-foreground">Opgeslagen.</p>
                        </TransitionRoot>
                    </div>
                </form>
            </div>

            <DeleteUser />
        </SettingsLayout>
    </AppLayout>
</template>
