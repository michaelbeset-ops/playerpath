<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { Cake } from 'lucide-vue-next';

/**
 * Wie er binnenkort jarig is: naam, datum, en hoe oud ze worden.
 *
 * De leeftijd is de leeftijd die het kind wórdt - dat is wat je in een
 * berichtje zet. Vandaag jarig staat bovenaan en groen: dat is de enige die nu
 * iets van je vraagt.
 */
defineProps<{
    birthdays: { id: number; name: string; first_name: string; date: string; turns: number; today: boolean }[];
    days: number;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Verjaardagen', href: '/verjaardagen' }];
</script>

<template>
    <Head title="Verjaardagen" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-3xl p-4">
            <h1 class="text-2xl font-semibold tracking-tight">Verjaardagen</h1>
            <p class="mt-1 text-sm text-muted-foreground">De komende {{ days }} dagen.</p>

            <ul v-if="birthdays.length" class="mt-5 overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <li v-for="rij in birthdays" :key="rij.id" class="border-t border-border first:border-t-0">
                    <Link :href="'/players/' + rij.id" class="flex min-h-14 items-center gap-3 px-4 py-2 transition hover:bg-secondary/50">
                        <span
                            class="flex size-9 shrink-0 items-center justify-center rounded-lg"
                            :class="rij.today ? 'bg-primary/15 text-primary' : 'bg-secondary text-muted-foreground'"
                        >
                            <Cake class="size-4" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium">{{ rij.name }}</span>
                            <span class="block text-xs" :class="rij.today ? 'font-medium text-primary' : 'text-muted-foreground'">
                                {{ rij.today ? 'Vandaag jarig' : rij.date }} &middot; wordt {{ rij.turns }}
                            </span>
                        </span>
                    </Link>
                </li>
            </ul>

            <div v-else class="mt-5 rounded-2xl border border-dashed border-border bg-card/50 p-8 text-center">
                <Cake class="mx-auto size-6 text-muted-foreground" />
                <p class="mt-2 text-sm font-medium">Niemand jarig de komende {{ days }} dagen</p>
                <p class="mt-1 text-xs text-muted-foreground">Van jouw spelers, in elk geval.</p>
            </div>
        </div>
    </AppLayout>
</template>
