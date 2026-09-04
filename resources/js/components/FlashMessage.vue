<script setup lang="ts">
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { CheckCircle2 } from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * Bevestiging na een actie. Groen, want er is echt iets gebeurd —
 * zie de huisstijlregel "groen betekent iets" in CLAUDE.md.
 */
const page = usePage<SharedData>();
const status = computed(() => page.props.flash?.status ?? null);
</script>

<template>
    <div
        v-if="status"
        class="mb-4 flex items-start gap-3 rounded-xl border border-primary/30 bg-primary/5 p-4 text-sm"
        role="status"
        aria-live="polite"
    >
        <CheckCircle2 class="mt-0.5 size-4 shrink-0 text-primary" />
        <p>{{ status }}</p>
    </div>
</template>
