<script setup lang="ts">
import FlashMessage from '@/components/FlashMessage.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Cake } from 'lucide-vue-next';

const props = defineProps<{
    enabled: boolean;
    message: string | null;
    upcoming: { id: number; name: string; first_name: string; date: string; turns: number; today: boolean }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Mededelingen', href: '/announcements' },
    { title: 'Verjaardagen', href: '/announcements/verjaardagen' },
];

const form = useForm({
    enabled: props.enabled,
    message: props.message ?? '',
});

const opslaan = () => form.patch('/announcements/verjaardagen', { preserveScroll: true });
</script>

<template>
    <Head title="Verjaardagsmail" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl p-4">
            <FlashMessage />

            <h1 class="text-2xl font-semibold tracking-tight">Verjaardagsmail</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Elke ochtend om 08:00 wordt gekeken wie er jarig is. De felicitatie gaat naar de speler zelf als die een eigen inlog heeft, en anders
                naar zijn ouders.
            </p>

            <form class="mt-6 space-y-4" @submit.prevent="opslaan">
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-border bg-card p-5 shadow-sm">
                    <input v-model="form.enabled" type="checkbox" class="mt-0.5 size-4 shrink-0 rounded border-input accent-primary" />
                    <span class="min-w-0">
                        <span class="block font-medium">Automatisch feliciteren</span>
                        <span class="block text-sm text-muted-foreground">
                            Staat standaard uit. Het bericht gaat uit naam van je school, dus je zet hem zelf aan.
                        </span>
                    </span>
                </label>

                <div class="rounded-xl border border-border bg-card p-5 shadow-sm">
                    <label for="message" class="font-medium">Je eigen zin <span class="text-muted-foreground">(optioneel)</span></label>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Laat leeg voor de standaardtekst: "Sem is vandaag 12 geworden. Van harte gefeliciteerd namens de hele school!"
                    </p>

                    <textarea
                        id="message"
                        v-model="form.message"
                        rows="3"
                        maxlength="500"
                        class="mt-3 w-full rounded-lg border border-input bg-background px-3 py-2 text-base outline-none focus:border-primary sm:text-sm"
                        placeholder="Van harte gefeliciteerd! Tot zaterdag op het veld."
                    ></textarea>
                    <p class="tabular mt-1 text-xs text-muted-foreground">{{ form.message.length }} / 500</p>
                </div>

                <Button type="submit" :disabled="form.processing">Opslaan</Button>
            </form>

            <div class="mt-8 rounded-xl border border-border bg-card p-5 shadow-sm">
                <p class="flex items-center gap-2 font-medium">
                    <Cake class="size-4 text-muted-foreground" />
                    De komende 60 dagen
                </p>

                <div v-if="upcoming.length" class="mt-3 space-y-2">
                    <Link
                        v-for="jarig in upcoming"
                        :key="jarig.id"
                        :href="'/players/' + jarig.id"
                        class="flex items-center justify-between gap-3 rounded-lg border p-3 text-sm transition"
                        :class="jarig.today ? 'border-primary/40 bg-primary/5' : 'border-border hover:border-primary'"
                    >
                        <span class="min-w-0 truncate font-medium">{{ jarig.name }}</span>
                        <span class="tabular shrink-0 text-xs text-muted-foreground">
                            <template v-if="jarig.today">vandaag</template>
                            <template v-else>{{ jarig.date }}</template>
                            &middot; wordt {{ jarig.turns }}
                        </span>
                    </Link>
                </div>

                <p v-else class="mt-3 text-sm text-muted-foreground">Niemand jarig de komende 60 dagen.</p>
            </div>
        </div>
    </AppLayout>
</template>
