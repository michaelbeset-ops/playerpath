<script setup lang="ts">
import FlashMessage from '@/components/FlashMessage.vue';
import GatewayNotice from '@/components/GatewayNotice.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { Pencil, Plus, Tag } from 'lucide-vue-next';

defineProps<{
    plans: {
        id: number;
        name: string;
        description: string | null;
        amount: string;
        interval: string;
        is_active: boolean;
        subscriptions_count: number;
    }[];
    gateway: { connected: boolean; name: string; message: string };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Tarieven', href: '/plans' }];
</script>

<template>
    <Head title="Tarieven" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-3xl p-4">
            <FlashMessage />
            <GatewayNotice :gateway="gateway" />

            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Tarieven</h1>
                    <p class="mt-1 text-sm text-muted-foreground">De abonnementsvormen die je school aanbiedt.</p>
                </div>

                <Link
                    href="/plans/create"
                    class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                >
                    <Plus class="size-4" />
                    Tarief toevoegen
                </Link>
            </div>

            <div v-if="plans.length" class="mt-6 space-y-2">
                <div v-for="plan in plans" :key="plan.id" class="flex items-center gap-4 rounded-xl border border-border bg-card p-4 shadow-sm">
                    <span
                        class="flex size-10 shrink-0 items-center justify-center rounded-lg"
                        :class="plan.is_active ? 'bg-primary/10 text-primary' : 'bg-secondary text-muted-foreground/70'"
                    >
                        <Tag class="size-5" />
                    </span>

                    <div class="min-w-0 flex-1">
                        <p class="flex items-center gap-2 truncate font-medium">
                            {{ plan.name }}
                            <span v-if="!plan.is_active" class="rounded bg-secondary px-1.5 py-0.5 text-[10px] text-muted-foreground">
                                niet actief
                            </span>
                        </p>
                        <p class="truncate text-xs text-muted-foreground">
                            <span v-if="plan.description">{{ plan.description }} &middot; </span>
                            {{ plan.subscriptions_count }}
                            {{ plan.subscriptions_count === 1 ? 'lopend abonnement' : 'lopende abonnementen' }}
                        </p>
                    </div>

                    <div class="shrink-0 text-right">
                        <p class="tabular font-semibold">{{ plan.amount }}</p>
                        <p class="text-xs text-muted-foreground">{{ plan.interval }}</p>
                    </div>

                    <Link
                        :href="'/plans/' + plan.id + '/edit'"
                        class="shrink-0 rounded-lg p-2 text-muted-foreground transition hover:bg-secondary hover:text-foreground"
                        :aria-label="plan.name + ' bewerken'"
                    >
                        <Pencil class="size-4" />
                    </Link>
                </div>
            </div>

            <div v-else class="mt-6 rounded-2xl border border-dashed border-border bg-card/50 p-10 text-center">
                <p class="font-medium">Nog geen tarieven</p>
                <p class="mt-1 text-sm text-muted-foreground">Maak er een aan, bijvoorbeeld "Keeperstraining, per maand".</p>
            </div>
        </div>
    </AppLayout>
</template>
