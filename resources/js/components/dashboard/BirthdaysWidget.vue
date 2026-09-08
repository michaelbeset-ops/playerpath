<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Cake } from 'lucide-vue-next';

/**
 * Klein en bescheiden. Aardig om te weten, geen reden om een dashboard te
 * openen — vandaar onderaan.
 */
defineProps<{
    data: { id: number; name: string; first_name: string; date: string; turns: number; today: boolean }[];
}>();
</script>

<template>
    <!-- Leeg is weg op een telefoon: een trainer scrolt niet langs een vak dat
         zegt dat er niets is. Op een groot scherm blijft het vak staan, anders
         valt er een gat in het raster. -->
    <section
        class="h-full flex-col overflow-hidden rounded-xl border border-border bg-card p-4 shadow-sm sm:p-5"
        :class="data.length ? 'flex' : 'hidden lg:flex'"
    >
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <p class="flex items-center gap-2 font-medium">
                <Cake class="size-4 text-muted-foreground" />
                Verjaardagen
            </p>
            <Link href="/verjaardagen" class="inline-flex min-h-11 items-center text-xs font-medium text-primary underline underline-offset-4">
                Bekijk meer
            </Link>
        </div>

        <div v-if="data.length" class="mt-3 space-y-1.5">
            <Link
                v-for="jarig in data"
                :key="jarig.id"
                :href="'/players/' + jarig.id"
                class="flex min-h-11 items-center justify-between gap-2 rounded-lg border px-3 py-2 text-sm transition"
                :class="jarig.today ? 'border-primary/40 bg-primary/5' : 'border-border hover:border-primary'"
            >
                <span class="min-w-0 truncate">{{ jarig.name }}</span>
                <span class="tabular shrink-0 text-xs text-muted-foreground">
                    <template v-if="jarig.today">vandaag</template>
                    <template v-else>{{ jarig.date }}</template>
                    &middot; {{ jarig.turns }}
                </span>
            </Link>
        </div>

        <p v-else class="mt-3 text-sm text-muted-foreground">Niemand jarig de komende 30 dagen.</p>
    </section>
</template>
