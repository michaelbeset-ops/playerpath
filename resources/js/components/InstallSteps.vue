<script setup lang="ts">
import { useInstall } from '@/composables/useInstall';
import { Share, SquarePlus } from 'lucide-vue-next';

/**
 * De knop of de stappen om de app op je beginscherm te zetten. Wat er
 * staat hangt af van het toestel (zie useInstall); als het al een app is,
 * staat er een regel die dat zegt.
 */
defineProps<{ naam?: string }>();

const emit = defineEmits<{ klaar: [uitkomst: string | null] }>();

const { isApp, isIos, kanKnop, installeer } = useInstall();

const klik = async () => emit('klaar', await installeer());
</script>

<template>
    <div>
        <p v-if="isApp" class="text-sm text-muted-foreground">Je gebruikt {{ naam ?? 'PlayerPath' }} al als app op je beginscherm.</p>

        <button
            v-else-if="kanKnop"
            type="button"
            class="inline-flex h-11 w-full items-center justify-center rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground transition hover:opacity-90 sm:w-auto"
            @click="klik"
        >
            Op beginscherm zetten
        </button>

        <ol v-else-if="isIos" class="space-y-1.5 text-sm">
            <li class="flex items-center gap-2">
                <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-secondary text-xs font-semibold">1</span>
                Tik onderin op <Share class="inline size-4" aria-label="Deel" /> <span class="font-medium">Deel</span>
            </li>
            <li class="flex items-center gap-2">
                <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-secondary text-xs font-semibold">2</span>
                Kies <SquarePlus class="inline size-4" aria-hidden="true" /> <span class="font-medium">Zet op beginscherm</span>
            </li>
        </ol>

        <ol v-else class="space-y-1.5 text-sm">
            <li class="flex items-start gap-2">
                <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-secondary text-xs font-semibold">1</span>
                <span>Open het menu van je browser (de drie puntjes)</span>
            </li>
            <li class="flex items-start gap-2">
                <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-secondary text-xs font-semibold">2</span>
                <span>Kies <span class="font-medium">App installeren</span> of <span class="font-medium">Toevoegen aan startscherm</span></span>
            </li>
        </ol>
    </div>
</template>
