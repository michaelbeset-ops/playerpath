<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { AlertTriangle, CheckCircle2, ClipboardList, CreditCard, UserCog, X } from 'lucide-vue-next';
import type { Component } from 'vue';

/**
 * Het antwoord op "wat moet ik doen?".
 *
 * Staat vast bovenaan en is geen widget: het is de reden dat je een dashboard
 * opent, dus je kunt het niet verplaatsen. Wegklikken kan wel, maar dat
 * betekent "gezien": zodra er iets verandert staat het er weer.
 *
 * **Is er niets, dan staat er één regel.** "Alles loopt — niks te doen" is het
 * antwoord op de vraag waarvoor je het dashboard opende; zwijgen laat je
 * twijfelen of je iets mist. Het blijft één rustige regel, geen vak met een
 * kopje: dat leest als een fout.
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

const props = defineProps<{
    items: AandachtItem[];
    /**
     * De vingerafdruk van wat er nu in staat. Wegklikken slaat die op, zodat
     * het blok terugkomt zodra er iets verandert; zie AttentionItems.
     */
    signature?: string | null;
}>();

const wegklikken = () => {
    if (!props.signature) {
        return;
    }

    router.post('/dashboard/aandacht/gezien', { signature: props.signature }, { preserveScroll: true });
};

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
    <!-- Niets te doen: één regel, groen, zonder kop. Dat is het antwoord op de
         vraag waarvoor je het dashboard opendeed. -->
    <p
        v-if="!items.length"
        class="flex items-center gap-2 rounded-2xl border border-success/30 bg-success/5 px-4 py-3 text-sm text-success"
        aria-label="Vraagt om aandacht"
    >
        <CheckCircle2 class="size-4 shrink-0" />
        Alles loopt — niks te doen.
    </p>

    <section v-else class="rounded-2xl border border-warning/30 bg-warning/5 p-3 sm:p-5" aria-label="Vraagt om aandacht">
        <div class="flex items-start justify-between gap-3">
            <p class="flex min-w-0 items-center gap-2 font-medium">
                <AlertTriangle class="size-4 shrink-0 text-warning" />
                Vraagt om aandacht
            </p>

            <!-- Wegklikken betekent "gezien", niet "waarschuw me nooit meer":
                 zodra er iets verandert staat het er weer. -->
            <button
                v-if="signature"
                type="button"
                class="-my-2.5 flex size-11 shrink-0 items-center justify-center rounded-lg text-muted-foreground transition hover:bg-card hover:text-foreground"
                aria-label="Aandacht-blok wegklikken tot er iets verandert"
                title="Gezien. Komt terug zodra er iets verandert."
                @click="wegklikken"
            >
                <X class="size-4" />
            </button>
        </div>

        <div class="mt-2 space-y-2">
            <div
                v-for="item in items"
                :key="item.key"
                class="flex flex-col gap-2 rounded-xl border bg-card p-3 sm:flex-row sm:items-center sm:gap-4"
                :class="kleuren[item.tone].rand"
            >
                <div class="flex min-w-0 flex-1 items-start gap-3">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-lg" :class="kleuren[item.tone].vlak">
                        <component :is="iconen[item.icon] ?? AlertTriangle" class="size-4" />
                    </span>

                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium" :class="kleuren[item.tone].tekst">{{ item.title }}</p>
                        <p class="text-xs text-muted-foreground">{{ item.body }}</p>
                    </div>
                </div>

                <Link
                    :href="item.href"
                    class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-lg border border-border bg-background px-3 text-sm font-medium transition hover:border-primary"
                >
                    {{ item.action }}
                </Link>
            </div>
        </div>
    </section>
</template>
