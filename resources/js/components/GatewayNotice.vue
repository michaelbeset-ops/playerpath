<script setup lang="ts">
import { Plug } from 'lucide-vue-next';

/**
 * De eerlijke melding dat er nog geen betaalprovider hangt.
 *
 * Bewust geen alarmerende kleur: er is niets stuk, dit is gewoon de stand van
 * zaken. Zodra de gateway wel is aangesloten verdwijnt hij vanzelf.
 */
defineProps<{
    gateway: { connected: boolean; name: string; message?: string };
}>();
</script>

<template>
    <div v-if="!gateway.connected" class="mb-4 flex items-start gap-3 rounded-xl border border-dashed border-border bg-card/60 p-3 sm:p-4">
        <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-secondary text-muted-foreground sm:size-9">
            <Plug class="size-4" />
        </span>

        <div class="min-w-0">
            <p class="text-sm font-medium">{{ gateway.name }} is nog niet aangesloten</p>
            <p v-if="gateway.message" class="mt-0.5 text-xs leading-snug text-muted-foreground sm:mt-1 sm:text-sm">{{ gateway.message }}</p>
        </div>
    </div>
</template>
