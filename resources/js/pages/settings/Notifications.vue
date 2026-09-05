<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { TransitionRoot } from '@headlessui/vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    kinds: { key: string; label: string; enabled: boolean }[];
}>();

const breadcrumbItems: BreadcrumbItem[] = [{ title: 'Meldingen', href: '/settings/notifications' }];

const form = useForm({
    preferences: Object.fromEntries(props.kinds.map((k) => [k.key, k.enabled])) as Record<string, boolean>,
});

const opslaan = () => form.patch('/settings/notifications', { preserveScroll: true });
</script>

<template>
    <Head title="Meldingen" />

    <AppLayout :breadcrumbs="breadcrumbItems">
        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall title="Meldingen" description="Kies welke e-mails je van de school wilt ontvangen." />

                <form class="space-y-6" @submit.prevent="opslaan">
                    <div class="space-y-3">
                        <label
                            v-for="soort in kinds"
                            :key="soort.key"
                            class="flex cursor-pointer items-start gap-3 rounded-lg border border-border p-3"
                        >
                            <input
                                v-model="form.preferences[soort.key]"
                                type="checkbox"
                                class="mt-0.5 size-4 rounded border-input accent-primary"
                            />
                            <span class="text-sm">{{ soort.label }}</span>
                        </label>
                    </div>

                    <p class="rounded-lg bg-secondary px-3 py-2 text-xs text-muted-foreground">
                        Meldingen in de app blijven altijd staan. Zo mis je een afgelasting nooit, ook niet als je hier alles uitzet.
                    </p>

                    <div class="flex items-center gap-4">
                        <Button type="submit" :disabled="form.processing">Opslaan</Button>

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
        </SettingsLayout>
    </AppLayout>
</template>
