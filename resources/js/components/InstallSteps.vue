<script setup lang="ts">
import { useInstall } from '@/composables/useInstall';
import { Ellipsis, Share, SquarePlus } from 'lucide-vue-next';

/**
 * De knop of de stappen om de app op je beginscherm te zetten, voor precies
 * dit toestel en deze browser (zie useInstall). Als het al een app is, staat
 * er een regel die dat zegt.
 */
withDefaults(defineProps<{ naam?: string; klein?: boolean; knop?: boolean }>(), { knop: true });

const emit = defineEmits<{ klaar: [uitkomst: string | null] }>();

const { isApp, weg, installeer } = useInstall();

const klik = async () => emit('klaar', await installeer());
</script>

<template>
    <div :class="klein ? 'text-xs' : 'text-sm'">
        <p v-if="isApp" class="text-muted-foreground">Je gebruikt {{ naam ?? 'PlayerPath' }} al als app op je beginscherm.</p>

        <button
            v-else-if="weg === 'knop' && knop"
            type="button"
            class="inline-flex h-11 w-full items-center justify-center rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground transition hover:opacity-90 sm:w-auto"
            @click="klik"
        >
            Op beginscherm zetten
        </button>

        <!-- Ingebouwde browser (WhatsApp, Instagram): daar kan het niet -->
        <div v-else-if="weg === 'ingebouwd'" class="space-y-1.5">
            <p class="font-medium">Open deze pagina eerst in Safari of Chrome.</p>
            <ol class="space-y-1.5">
                <li class="flex items-start gap-2">
                    <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-secondary text-[10px] font-semibold">1</span>
                    <span>Tik op <Ellipsis class="inline size-4" aria-label="de drie puntjes" /> of het kompas, rechtsboven of onderin</span>
                </li>
                <li class="flex items-start gap-2">
                    <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-secondary text-[10px] font-semibold">2</span>
                    <span>Kies <span class="font-medium">Openen in Safari</span> (of in je browser)</span>
                </li>
                <li class="flex items-start gap-2">
                    <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-secondary text-[10px] font-semibold">3</span>
                    <span>Daar: <Share class="inline size-4" aria-label="Deel" /> <span class="font-medium">Deel</span>, dan <span class="font-medium">Zet op beginscherm</span></span>
                </li>
            </ol>
        </div>

        <ol v-else-if="weg === 'iphone' || weg === 'ipad' || weg === 'ios-andere-browser'" class="space-y-1.5">
            <li class="flex items-start gap-2">
                <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-secondary text-[10px] font-semibold">1</span>
                <span>
                    Tik
                    {{ weg === 'iphone' ? 'onderin' : weg === 'ipad' ? 'bovenin' : 'rechtsboven in de adresbalk' }}
                    op <Share class="inline size-4" aria-label="Deel" /> <span class="font-medium">Deel</span>
                    <span v-if="weg === 'iphone'" class="text-muted-foreground"> (of eerst op <Ellipsis class="inline size-4" aria-label="de drie puntjes" />)</span>
                </span>
            </li>
            <li class="flex items-start gap-2">
                <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-secondary text-[10px] font-semibold">2</span>
                <span>Scroll omlaag en kies <SquarePlus class="inline size-4" aria-hidden="true" /> <span class="font-medium">Zet op beginscherm</span></span>
            </li>
            <li class="flex items-start gap-2">
                <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-secondary text-[10px] font-semibold">3</span>
                <span>Tik rechtsboven op <span class="font-medium">Voeg toe</span></span>
            </li>
        </ol>

        <ol v-else class="space-y-1.5">
            <li class="flex items-start gap-2">
                <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-secondary text-[10px] font-semibold">1</span>
                <span>Open het menu van je browser (de drie puntjes)</span>
            </li>
            <li class="flex items-start gap-2">
                <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-secondary text-[10px] font-semibold">2</span>
                <span>Kies <span class="font-medium">App installeren</span> of <span class="font-medium">Toevoegen aan startscherm</span></span>
            </li>
        </ol>
    </div>
</template>
