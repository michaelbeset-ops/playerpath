<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { Component } from 'vue';
import { computed } from 'vue';

/**
 * De standaardkaart voor een kerncijfer op de admin-kant.
 *
 * Alle accenten komen uit het groen van het logo; de kaart zelf blijft wit met
 * een rand en een lichte schaduw. Een kaart zonder waarde (nog niet gebouwd)
 * wordt bewust grijs in plaats van groen, zodat kleur iets betekent en het
 * dashboard niet druk wordt.
 */
const props = withDefaults(
    defineProps<{
        label: string;
        value?: number | string | null;
        hint?: string;
        icon: Component;
        href?: string;
        /**
         * Wat de waarde betekent. Groen leest als "goed", dus een cijfer dat om
         * actie vraagt mag nooit groen zijn — dan zegt kleur het tegendeel van
         * wat er staat.
         */
        tone?: 'default' | 'warning' | 'danger';
    }>(),
    { value: null, hint: undefined, href: undefined, tone: 'default' },
);

const heeftWaarde = computed(() => props.value !== null && props.value !== undefined);

const kleuren = computed(() => {
    if (!heeftWaarde.value) {
        return { balk: 'bg-border', tekst: 'text-muted-foreground/60', icoon: 'bg-secondary text-muted-foreground/70' };
    }

    if (props.tone === 'danger') {
        return { balk: 'bg-destructive', tekst: 'text-destructive', icoon: 'bg-destructive/10 text-destructive' };
    }

    if (props.tone === 'warning') {
        return { balk: 'bg-warning', tekst: 'text-warning', icoon: 'bg-warning/10 text-warning' };
    }

    return { balk: 'bg-primary', tekst: 'text-primary', icoon: 'bg-primary/10 text-primary' };
});
</script>

<template>
    <component
        :is="href ? Link : 'div'"
        :href="href"
        class="group relative block overflow-hidden rounded-xl border border-border bg-card p-5 shadow-sm transition"
        :class="href ? 'hover:border-primary/40 hover:shadow' : ''"
    >
        <!-- Het groene streepje bovenaan de kaart -->
        <span class="absolute inset-x-0 top-0 h-1" :class="kleuren.balk" aria-hidden="true"></span>

        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <p class="truncate text-sm font-medium text-muted-foreground">{{ label }}</p>
                <p class="tabular mt-2 text-3xl font-bold leading-none" :class="kleuren.tekst">
                    {{ heeftWaarde ? value : '—' }}
                </p>
            </div>

            <span
                class="flex size-10 shrink-0 items-center justify-center rounded-lg transition"
                :class="kleuren.icoon"
            >
                <component :is="icon" class="size-5" />
            </span>
        </div>

        <p v-if="hint" class="mt-3 text-xs text-muted-foreground">{{ hint }}</p>
    </component>
</template>
