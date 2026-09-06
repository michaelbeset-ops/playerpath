<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CalendarDays } from 'lucide-vue-next';

defineProps<{
    data: { id: number; group: string; date: string; time: string; location: string | null }[];
    canPlan: boolean;
}>();
</script>

<template>
    <section class="flex h-full flex-col overflow-hidden rounded-xl border border-border bg-card p-5 shadow-sm">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <p class="font-medium">Komende trainingen</p>
            <Link href="/trainings" class="text-xs font-medium text-primary underline underline-offset-4">Alles</Link>
        </div>

        <div v-if="data.length" class="mt-3 space-y-2">
            <Link
                v-for="training in data"
                :key="training.id"
                :href="'/trainings/' + training.id"
                class="flex items-center gap-3 rounded-lg border border-border p-2.5 transition hover:border-primary"
            >
                <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <CalendarDays class="size-4" />
                </span>

                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium">{{ training.group }}</p>
                    <p class="truncate text-xs text-muted-foreground first-letter:uppercase">
                        {{ training.date }} &middot; {{ training.time }}
                        <span v-if="training.location"> &middot; {{ training.location }}</span>
                    </p>
                </div>
            </Link>
        </div>

        <p v-else class="mt-3 text-sm text-muted-foreground">
            Er staat niets gepland.
            <Link v-if="canPlan" href="/trainings/create" class="font-medium text-primary underline underline-offset-4"> Plan een training </Link>
        </p>
    </section>
</template>
