<script setup lang="ts">
import { kleurVan } from '@/lib/grade';
import { ArrowRight, GraduationCap } from 'lucide-vue-next';

/**
 * Eén cursus op de voortgangspagina: per onderdeel een balk van de laagste
 * naar de hoogste kleur, met waar het kind begon en waar het nu staat.
 *
 * Alleen de eigen lijn. Geen getal, geen gemiddelde, geen vergelijking.
 */
export interface Niveau {
    key: string;
    label: string;
    color: string;
    index?: number;
}

export interface Cursus {
    product: { id: number; name: string; starts_on: string | null; ends_on: string | null };
    begin_on: string | null;
    eind_on: string | null;
    begin_note: string | null;
    eind_note: string | null;
    categories: { category: string; label: string; begin: (Niveau & { index: number }) | null; eind: (Niveau & { index: number }) | null }[];
}

defineProps<{ course: Cursus; levels: Niveau[]; firstName: string }>();
</script>

<template>
    <section class="rounded-xl border border-border bg-card p-5 shadow-sm">
        <div class="flex items-start gap-3">
            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                <GraduationCap class="size-5" />
            </span>
            <div class="min-w-0">
                <p class="font-medium leading-tight">{{ course.product.name }}</p>
                <p class="text-xs text-muted-foreground">
                    <template v-if="course.begin_on">Begin {{ course.begin_on }}</template>
                    <template v-if="course.begin_on && course.eind_on"> &middot; </template>
                    <template v-if="course.eind_on">Eind {{ course.eind_on }}</template>
                    <template v-if="!course.eind_on"> &middot; het eindniveau volgt aan het eind van de cursus</template>
                </p>
            </div>
        </div>

        <!-- De schaal zelf, als legenda: van werkpunt naar sterk -->
        <div class="mt-4 flex flex-wrap gap-x-3 gap-y-1 text-[11px] text-muted-foreground">
            <span v-for="n in levels" :key="n.key" class="inline-flex items-center gap-1">
                <span class="inline-block size-2.5 rounded-full" :style="{ backgroundColor: kleurVan(n.color) }"></span>
                {{ n.label }}
            </span>
        </div>

        <ul class="mt-4 space-y-4">
            <li v-for="c in course.categories.filter((c) => c.begin || c.eind)" :key="c.category">
                <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-0.5">
                    <p class="text-sm font-medium">{{ c.label }}</p>
                    <p class="flex items-center gap-1 text-xs font-semibold">
                        <span v-if="c.begin" :style="{ color: kleurVan(c.begin.color) }">{{ c.begin.label }}</span>
                        <template v-if="c.begin && c.eind">
                            <ArrowRight class="size-3 text-muted-foreground" />
                            <span :style="{ color: kleurVan(c.eind.color) }">{{ c.eind.label }}</span>
                        </template>
                        <span v-else-if="c.eind" :style="{ color: kleurVan(c.eind.color) }">{{ c.eind.label }}</span>
                    </p>
                </div>

                <!-- Het balkje schuift van rood naar blauw: het deel tussen begin
                     en eind licht op, de rest blijft zacht. -->
                <div class="mt-1.5 grid gap-1" :style="{ gridTemplateColumns: `repeat(${levels.length}, minmax(0, 1fr))` }">
                    <span
                        v-for="(n, i) in levels"
                        :key="n.key"
                        class="relative h-3 rounded-full"
                        :style="{
                            backgroundColor:
                                i <= (c.eind ?? c.begin)!.index && i >= (c.begin ?? c.eind)!.index
                                    ? kleurVan(n.color)
                                    : i < (c.begin ?? c.eind)!.index
                                      ? kleurVan(n.color, 0.35)
                                      : kleurVan(n.color, 0.12),
                        }"
                    >
                        <span
                            v-if="c.begin && c.eind && i === c.begin.index && c.begin.index !== c.eind.index"
                            class="absolute -top-0.5 left-1/2 size-4 -translate-x-1/2 rounded-full border-2 border-card bg-background"
                            title="Begin"
                        ></span>
                    </span>
                </div>
            </li>
        </ul>

        <div v-if="course.eind_note || course.begin_note" class="mt-5 rounded-lg bg-secondary/60 p-4 text-sm">
            <p class="font-medium">{{ course.eind_note ? 'Verslag van de trainer' : 'Notitie bij de start' }}</p>
            <p class="mt-1 whitespace-pre-line text-muted-foreground">{{ course.eind_note ?? course.begin_note }}</p>
        </div>

        <p class="mt-4 text-xs text-muted-foreground">
            Dit gaat alleen over {{ firstName }}: waar het begon en waar het nu staat. Niet over andere kinderen.
        </p>
    </section>
</template>
