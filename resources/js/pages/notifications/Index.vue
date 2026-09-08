<script setup lang="ts">
import FlashMessage from '@/components/FlashMessage.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ClipboardList, Megaphone } from 'lucide-vue-next';
import { computed } from 'vue';

interface Melding {
    id: string;
    type: string | null;
    title: string;
    body: string | null;
    url: string | null;
    player_name: string | null;
    overall_rating: number | null;
    groei: number | null;
    read: boolean;
    when: string;
}

const props = defineProps<{ notifications: Melding[] }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Meldingen', href: '/notifications' },
];

const ongelezen = computed(() => props.notifications.filter((m) => !m.read).length);

const allesGelezen = () => router.post('/notifications/read', {}, { preserveScroll: true });
</script>

<template>
    <Head title="Meldingen" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl p-4">
            <FlashMessage />

            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Meldingen</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ ongelezen === 0 ? 'Je bent bij.' : ongelezen + (ongelezen === 1 ? ' ongelezen melding' : ' ongelezen meldingen') }}
                    </p>
                </div>

                <button
                    v-if="ongelezen > 0"
                    type="button"
                    class="inline-flex min-h-11 items-center text-sm font-medium text-primary underline underline-offset-4"
                    @click="allesGelezen"
                >
                    Alles als gelezen markeren
                </button>
            </div>

            <div v-if="notifications.length" class="mt-6 space-y-2">
                <component
                    :is="melding.url ? Link : 'div'"
                    v-for="melding in notifications"
                    :key="melding.id"
                    :href="melding.url"
                    class="flex items-start gap-3 rounded-xl border bg-card p-4 shadow-sm transition"
                    :class="melding.read ? 'border-border' : 'border-primary/40'"
                >
                    <span
                        class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg"
                        :class="melding.read ? 'bg-secondary text-muted-foreground' : 'bg-primary/10 text-primary'"
                    >
                        <Megaphone v-if="melding.type === 'mededeling'" class="size-4" />
                        <ClipboardList v-else class="size-4" />
                    </span>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <p class="text-sm font-medium">{{ melding.title }}</p>
                            <p class="shrink-0 text-xs text-muted-foreground">{{ melding.when }}</p>
                        </div>

                        <p v-if="melding.body" class="mt-1 whitespace-pre-line text-sm text-muted-foreground">{{ melding.body }}</p>

                        <p v-if="melding.overall_rating !== null" class="tabular mt-1 text-sm text-muted-foreground">
                            De kaart staat nu op <span class="font-semibold text-foreground">{{ melding.overall_rating }}</span>
                            <span v-if="melding.groei !== null && melding.groei > 0" class="text-primary"> (+{{ melding.groei }})</span>
                        </p>
                    </div>
                </component>
            </div>

            <div v-else class="mt-6 rounded-2xl border border-dashed border-border bg-card/50 p-10 text-center">
                <p class="font-medium">Nog geen meldingen</p>
                <p class="mt-1 text-sm text-muted-foreground">Zodra er een rapport wordt ingevuld of de school iets laat weten, lees je het hier.</p>
            </div>
        </div>
    </AppLayout>
</template>
