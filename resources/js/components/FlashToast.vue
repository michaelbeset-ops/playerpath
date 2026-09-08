<script setup lang="ts">
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { CheckCircle2, X } from 'lucide-vue-next';
import { onBeforeUnmount, ref, watch } from 'vue';

/**
 * De bevestiging na een actie, als melding onderin het scherm.
 *
 * Hij stond eerst als regel bovenaan de pagina, maar de knop Opslaan staat
 * onderaan — en op een telefoon zie je de bovenkant dan niet. Een melding die
 * even verschijnt waar je kijkt, en vanzelf weer weggaat, is wat je verwacht
 * na een tik op Opslaan. Hij staat in de schil, zodat élke opslag een
 * bevestiging geeft en niet alleen de pagina's die eraan gedacht hebben.
 *
 * Groen, want er is echt iets gebeurd — zie "groen betekent iets" in CLAUDE.md.
 * Boven de tabbalk, zodat hij er niet achter valt als de app als app draait.
 */
const page = usePage<SharedData>();

const tekst = ref<string | null>(null);
let timer: ReturnType<typeof setTimeout> | undefined;

const toon = (status: string) => {
    tekst.value = status;
    clearTimeout(timer);
    timer = setTimeout(() => (tekst.value = null), 5000);
};

// Elke navigatie levert een nieuw flash-object op, ook als de tekst dezelfde
// is. Daarom op het object, niet op de tekst: twee keer achter elkaar opslaan
// hoort twee keer een bevestiging te geven.
watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.status) {
            toon(flash.status);
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
    <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="translate-y-3 opacity-0"
        leave-active-class="transition duration-150 ease-in"
        leave-to-class="translate-y-3 opacity-0"
    >
        <div
            v-if="tekst"
            class="pointer-events-none fixed inset-x-0 bottom-[calc(var(--pp-tabbar)+1rem)] z-[70] flex justify-center px-4"
            role="status"
            aria-live="polite"
        >
            <div class="pointer-events-auto flex w-full max-w-md items-start gap-3 rounded-xl border border-primary/30 bg-card p-4 text-sm shadow-lg">
                <CheckCircle2 class="mt-0.5 size-4 shrink-0 text-primary" aria-hidden="true" />
                <p class="min-w-0 flex-1">{{ tekst }}</p>
                <button
                    type="button"
                    class="-m-2 flex size-11 shrink-0 items-center justify-center rounded-lg text-muted-foreground transition hover:text-foreground"
                    aria-label="Sluiten"
                    @click="tekst = null"
                >
                    <X class="size-4" />
                </button>
            </div>
        </div>
    </Transition>
</template>
