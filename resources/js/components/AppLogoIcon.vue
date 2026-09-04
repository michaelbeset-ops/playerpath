<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<!-- PlayerPath-merkteken: een stijgende lijn met een punt op de top (groei). -->
<template>
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" :class="className" v-bind="$attrs">
        <path
            d="M3 17.5 8.5 12l3.5 3.5L20.5 7"
            fill="none"
            stroke="currentColor"
            stroke-width="2.5"
            stroke-linecap="round"
            stroke-linejoin="round"
        />
        <circle cx="20.5" cy="7" r="2.75" fill="currentColor" />
    </svg>
</template>
