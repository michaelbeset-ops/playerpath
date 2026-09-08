<script setup lang="ts">
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { SlidersHorizontal } from 'lucide-vue-next';
import { ref } from 'vue';

/**
 * Filters achter één knop op een telefoon, gewoon in beeld op een groot scherm.
 *
 * Vier rijen keuzelijsten onder elkaar op 375 pixels duwen de inhoud een half
 * scherm omlaag, en je moet ze allemaal lezen om te weten of er een filter
 * aanstaat. Eén knop met een teller zegt dat in één blik, en het paneel
 * eronder heeft ruimte voor alles.
 *
 * De inhoud wordt één keer meegegeven en twee keer getekend: inline (alleen op
 * een groot scherm) en in het paneel (alleen op een telefoon). Dezelfde
 * v-models, dus wat je in het paneel kiest staat op je laptop gewoon in beeld.
 *
 * - `count` is het aantal filters dat afwijkt van de standaard; dat is wat
 *   in het bolletje staat. Een weergavekeuze (lijst of raster) telt niet mee:
 *   dat is geen filter.
 * - Het `mobile`-slot is voor keuzes die op een groot scherm al ergens anders
 *   staan, zoals de weergave in de kalender.
 */
withDefaults(
    defineProps<{
        count: number;
        title?: string;
        description?: string;
        /** Op een groot scherm ook inline tekenen. Uit als de pagina daar zelf iets neerzet. */
        inline?: boolean;
        /** Wat er op de knop staat; standaard "Filters". */
        label?: string;
    }>(),
    { inline: true, label: 'Filters' },
);

const open = ref(false);
</script>

<template>
    <!-- Groot scherm: gewoon in beeld, in het raster of de rij van de pagina. -->
    <div v-if="inline" class="hidden sm:contents">
        <slot />
    </div>

    <!-- Telefoon: één knop met een teller, en een paneel van onderen. -->
    <div class="sm:hidden">
        <button
            type="button"
            class="relative inline-flex min-h-11 items-center gap-2 rounded-lg border bg-card px-3 text-sm font-medium shadow-sm transition"
            :class="count > 0 ? 'border-primary text-primary' : 'border-border text-foreground hover:border-primary'"
            :aria-label="count > 0 ? label + ', ' + count + ' actief' : label"
            @click="open = true"
        >
            <SlidersHorizontal class="size-4" aria-hidden="true" />
            {{ label }}
            <span
                v-if="count > 0"
                class="tabular inline-flex size-5 items-center justify-center rounded-full bg-primary text-[11px] font-bold text-primary-foreground"
            >
                {{ count }}
            </span>
        </button>

        <Sheet v-model:open="open">
            <SheetContent
                side="bottom"
                class="max-h-[85vh] overflow-y-auto rounded-t-2xl border-border bg-card p-5 pb-[calc(1.25rem+env(safe-area-inset-bottom))]"
            >
                <SheetHeader class="text-left">
                    <SheetTitle>{{ title ?? 'Filters' }}</SheetTitle>
                    <SheetDescription v-if="description">{{ description }}</SheetDescription>
                </SheetHeader>

                <div class="mt-4 space-y-4">
                    <slot name="mobile" />
                    <slot />
                </div>

                <button
                    type="button"
                    class="mt-5 inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-primary text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                    @click="open = false"
                >
                    Klaar
                </button>
            </SheetContent>
        </Sheet>
    </div>
</template>
