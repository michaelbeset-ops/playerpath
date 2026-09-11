<script setup lang="ts">
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { FlaskConical, Plug } from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * De eerlijke melding dat er nog geen betaalprovider hangt — of dat de
 * demo-provider aanstaat en er dus geen echt geld beweegt.
 *
 * Bewust geen alarmerende kleur: er is niets stuk, dit is gewoon de stand van
 * zaken. Zodra de gateway wel is aangesloten verdwijnt hij vanzelf.
 */
defineProps<{
    gateway: { connected: boolean; name: string; message?: string };
}>();

const demo = computed(() => usePage<SharedData>().props.paymentsDemo === true);
</script>

<template>
    <div v-if="demo" class="mb-4 flex items-start gap-3 rounded-xl border border-warning/40 bg-warning/10 p-3 sm:p-4">
        <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-warning/20 text-warning sm:size-9">
            <FlaskConical class="size-4" />
        </span>

        <div class="min-w-0">
            <p class="text-sm font-medium">Demo-betalingen staan aan</p>
            <p class="mt-0.5 text-xs leading-snug text-muted-foreground sm:mt-1 sm:text-sm">
                Je ziet de hele betaalflow, maar er wordt geen geld afgeschreven. Voor een echte koppeling vul je een Mollie-sleutel in.
            </p>
        </div>
    </div>

    <div v-else-if="!gateway.connected" class="mb-4 flex items-start gap-3 rounded-xl border border-dashed border-border bg-card/60 p-3 sm:p-4">
        <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-secondary text-muted-foreground sm:size-9">
            <Plug class="size-4" />
        </span>

        <div class="min-w-0">
            <p class="text-sm font-medium">{{ gateway.name }} is nog niet aangesloten</p>
            <p v-if="gateway.message" class="mt-0.5 text-xs leading-snug text-muted-foreground sm:mt-1 sm:text-sm">{{ gateway.message }}</p>
        </div>
    </div>
</template>
