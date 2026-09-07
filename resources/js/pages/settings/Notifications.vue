<script setup lang="ts">
import SettingsCard from '@/components/SettingsCard.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { TransitionRoot } from '@headlessui/vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Bell, Check } from 'lucide-vue-next';

const props = defineProps<{
    kinds: { key: string; label: string; enabled: boolean }[];
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Instellingen', href: '/settings/profile' },
    { title: 'Meldingen', href: '/settings/notifications' },
];

const form = useForm({
    preferences: Object.fromEntries(props.kinds.map((k) => [k.key, k.enabled])) as Record<string, boolean>,
});

const opslaan = () => form.patch('/settings/notifications', { preserveScroll: true });
</script>

<template>
    <Head title="Meldingen" />

    <AppLayout :breadcrumbs="breadcrumbItems">
        <SettingsLayout>
            <SettingsCard title="E-mails van de school" description="Zet uit wat je niet per mail wilt krijgen." :icon="Bell">
                <form class="space-y-5" @submit.prevent="opslaan">
                    <!-- Schakelaars in plaats van vinkjes: op een telefoon is een
                         vinkje van 16 pixels een gok, een schakelaar niet. -->
                    <div class="divide-y divide-border rounded-xl border border-border">
                        <label v-for="soort in kinds" :key="soort.key" class="flex cursor-pointer items-center justify-between gap-4 px-4 py-3">
                            <span class="text-sm">{{ soort.label }}</span>

                            <span class="relative inline-flex shrink-0">
                                <input v-model="form.preferences[soort.key]" type="checkbox" class="peer sr-only" />
                                <span
                                    class="block h-7 w-12 rounded-full bg-secondary transition peer-checked:bg-primary peer-focus-visible:ring-2 peer-focus-visible:ring-primary/50"
                                ></span>
                                <span
                                    class="absolute left-0.5 top-0.5 size-6 rounded-full bg-card shadow transition peer-checked:translate-x-5"
                                ></span>
                            </span>
                        </label>
                    </div>

                    <p class="rounded-lg bg-secondary px-3 py-2 text-xs text-muted-foreground">
                        Meldingen in de app blijven altijd staan. Zo mis je een afgelasting nooit, ook niet als je hier alles uitzet.
                    </p>

                    <div class="flex items-center gap-4">
                        <Button type="submit" class="h-11 px-5" :disabled="form.processing">Opslaan</Button>

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
