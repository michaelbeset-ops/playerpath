<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

defineProps<{
    className?: HTMLAttributes['class'];
}>();
</script>

<!--
    Het merkteken van PlayerPath: twee P's op een donkere tegel met een groen
    streepje eronder.

    Het bestand zelf, niet een natekening ervan: een logo hoort overal precies
    hetzelfde te zijn. De tegel hoort erbij — het teken staat op een beginscherm,
    in een tabblad en in een lichte balk, en zonder eigen achtergrond verdwijnt
    het daar.
-->
<template>
    <img src="/brand/mark-64.png" alt="PlayerPath" class="shrink-0 object-contain" :class="className" v-bind="$attrs" />
</template>
