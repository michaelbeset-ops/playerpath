<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { AlertTriangle, ClipboardList, CreditCard, UserCog } from 'lucide-vue-next';
import type { Component } from 'vue';

/**
 * Het antwoord op "wat moet ik doen?".
 *
 * Staat vast bovenaan en is geen widget: het is de reden dat je een dashboard
 * opent, en dat hoort niet weg te klikken te zijn.
 *
 * Elk item heeft een knop. Een signaal zonder knop is een cijfer, en cijfers
 * horen in de kerncijfers thuis.
 */
export interface AandachtItem {
    key: string;
    tone: 'danger' | 'warning' | 'neutral';
    icon: string;
    title: string;
    body: string;
    href: string;
    action: string;
}

defineProps<{ items: AandachtItem[] }>();

const iconen: Record<string, Component> = {
    payment: CreditCard,
    report: ClipboardList,
    trainer: UserCog,
};

// Groen leest als "goed", dus een signaal is nooit groen. Zie CLAUDE.md 4.
const kleuren: Record<string, { rand: string; vlak: string; tekst: string }> = {
    danger: { rand: 'border-destructive/30', vlak: 'bg-destructive/10 text-destructive', tekst: 'text-destructive' },
    warning: { rand: 'border-warning/30', vlak: 'bg-warning/10 text-warning', tekst: 'text-warning' },
    neutral: { rand: 'border-border', vlak: 'bg-secondary text-muted-foreground', tekst: 'text-foreground' },
};
</script>

<template>
    <section
        class="rounded-2xl border p-4 sm:p-5"
        :class="items.length ? 'border-warning/30 bg-warning/5' : 'border-primary/25 bg-primary/5'"
        aria-label="Vraagt om aandacht"
    >
        <p class="flex items-center gap-2 font-medium">
            <AlertTriangle v-if="items.length" class="size-4 shrink-0 text-warning" />
            {{ items.length ? 'Vraagt om aandacht' : 'Alles loopt' }}
        </p>

        <!-- Niets te doen is ook een uitkomst, en verdient één rustige regel
             in plaats van een leeg vak met een kopje. -->
        <p v-if="!items.length" class="mt-1 text-sm text-muted-foreground">Niks te doen.</p>

        <div v-else class="mt-3 space-y-2">
            <div
                v-for="item in items"
                :key="item.key"
                class="flex flex-col gap-3 rounded-xl border bg-card p-3 sm:flex-row sm:items-center sm:gap-4"
                :class="kleuren[item.tone].rand"
            >
                <span class="flex size-9 shrink-0 items-center justify-center rounded-lg" :class="kleuren[item.tone].vlak">
                    <component :is="iconen[item.icon] ?? AlertTriangle" class="size-4" />
                </span>

                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium" :class="kleuren[item.tone].tekst">{{ item.title }}</p>
                    <p class="text-xs text-muted-foreground">{{ item.body }}</p>
                </div>

                <Link
                    :href="item.href"
                    class="inline-flex h-9 shrink-0 items-center justify-center rounded-lg border border-border bg-background px-3 text-sm font-medium transition hover:border-primary"
                >
                    {{ item.action }}
                </Link>
            </div>
        </div>
    </section>
</template>
