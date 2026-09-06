<script setup lang="ts">
import { cn } from '@/lib/utils';
import type { HTMLAttributes } from 'vue';

/**
 * De werkvloer naast de zijbalk.
 *
 * `min-w-0` is hier geen cosmetiek. Een flex-item heeft standaard
 * `min-width: auto` en weigert dan onder de min-content-breedte van zijn
 * inhoud te krimpen. Eén brede tabel maakte daardoor de hele pagina 480px
 * breed op een telefoon van 375: een horizontale schuifbalk over álles heen,
 * op elk scherm. Breed materiaal hoort in zijn eigen `overflow-x-auto` te
 * schuiven, niet de pagina mee te trekken.
 */
const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <main
        :class="
            cn(
                'relative flex min-h-svh min-w-0 flex-1 flex-col bg-background',
                'peer-data-[variant=inset]:min-h-[calc(100svh-theme(spacing.4))] md:peer-data-[variant=inset]:m-2 md:peer-data-[state=collapsed]:peer-data-[variant=inset]:ml-2 md:peer-data-[variant=inset]:ml-0 md:peer-data-[variant=inset]:rounded-xl md:peer-data-[variant=inset]:shadow',
                props.class,
            )
        "
    >
        <slot />
    </main>
</template>
