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
    <div v-if="!gateway.connected" class="mb-4 flex items-start gap-3 rounded-xl border border-dashed border-border bg-card/60 p-4">
        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-secondary text-muted-foreground">
            <Plug class="size-4" />
        </span>

        <div class="min-w-0">
            <p class="text-sm font-medium">{{ gateway.name }} is nog niet aangesloten</p>
            <p v-if="gateway.message" class="mt-1 text-sm text-muted-foreground">{{ gateway.message }}</p>
        </div>
    </div>
</template>
