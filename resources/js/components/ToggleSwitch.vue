<script setup lang="ts">
/**
 * Een schakelaar met label en uitleg, als één tikbare rij.
 *
 * Op een telefoon is een vinkje van zestien pixels een gok; een rij van 44
 * pixels hoog met een schakelaar rechts niet. Het label is de knop, dus je
 * hoeft niet precies op het schuifje te mikken.
 */
defineProps<{
    label: string;
    description?: string;
}>();

const model = defineModel<boolean>({ required: true });
</script>

<template>
    <label class="flex min-h-11 cursor-pointer items-center justify-between gap-4 py-2">
        <span class="min-w-0">
            <span class="block text-sm font-medium">{{ label }}</span>
            <span v-if="description" class="block text-xs text-muted-foreground">{{ description }}</span>
        </span>

        <span class="relative inline-flex shrink-0">
            <input v-model="model" type="checkbox" class="peer sr-only" />
            <span
                class="block h-7 w-12 rounded-full bg-secondary transition peer-checked:bg-primary peer-focus-visible:ring-2 peer-focus-visible:ring-primary/50"
            ></span>
            <span class="absolute left-0.5 top-0.5 size-6 rounded-full bg-card shadow transition peer-checked:translate-x-5"></span>
        </span>
    </label>
</template>
