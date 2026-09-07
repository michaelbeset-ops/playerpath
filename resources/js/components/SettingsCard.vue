<script setup lang="ts">
import { type Component } from 'vue';

/**
 * Eén kaart op een instellingenpagina: icoon, titel, uitleg, inhoud.
 *
 * Dezelfde vorm voor profiel, wachtwoord en meldingen, zodat de pagina's
 * er niet elk anders uitzien. `tone="danger"` is voor de gevaarlijke hoek
 * onderaan (account verwijderen).
 */
withDefaults(
    defineProps<{
        title: string;
        description?: string;
        icon?: Component;
        tone?: 'default' | 'danger';
    }>(),
    { description: undefined, icon: undefined, tone: 'default' },
);
</script>

<template>
    <section class="overflow-hidden rounded-xl border bg-card shadow-sm" :class="tone === 'danger' ? 'border-destructive/25' : 'border-border'">
        <header class="flex items-start gap-3 border-b px-5 py-4" :class="tone === 'danger' ? 'border-destructive/15' : 'border-border'">
            <span
                v-if="icon"
                class="flex size-9 shrink-0 items-center justify-center rounded-lg"
                :class="tone === 'danger' ? 'bg-destructive/10 text-destructive' : 'bg-primary/10 text-primary'"
            >
                <component :is="icon" class="size-4" />
            </span>
            <div class="min-w-0">
                <h2 class="font-semibold" :class="tone === 'danger' ? 'text-destructive' : ''">{{ title }}</h2>
                <p v-if="description" class="text-sm text-muted-foreground">{{ description }}</p>
            </div>
        </header>

        <div class="p-5">
            <slot />
        </div>
    </section>
</template>
