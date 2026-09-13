<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { MapPin, UserCog } from 'lucide-vue-next';

/**
 * Wat er staat te gebeuren.
 *
 * Op een telefoon is dit het antwoord op "moet ik ergens zijn", en dan wil je
 * vier dingen zien zonder te tikken: wanneer, welke groep, waar, en wie erbij
 * staat. Dat laatste juist ook als het antwoord "geen trainer" is - dat is het
 * gat in de planning waar een eigenaar iets aan moet doen.
 *
 * De dag staat als blokje links in plaats van als zin in de regel eronder: zo
 * blijft er breedte over voor de naam van de groep, en scan je de kolom met
 * datums in één beweging.
 */
defineProps<{
    data: {
        id: number;
        group: string;
        date: string;
        day: string;
        time: string;
        location: string | null;
        trainers: string[];
        isToday: boolean;
        cancelled: boolean;
    }[];
    canPlan: boolean;
}>();
</script>

<template>
    <section class="flex h-full flex-col overflow-hidden rounded-xl border border-border bg-card p-4 shadow-sm sm:p-5">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <p class="font-medium">Komende trainingen</p>
            <Link href="/trainings" class="inline-flex min-h-11 items-center text-xs font-medium text-primary underline underline-offset-4">
                Alles
            </Link>
        </div>

        <div v-if="data.length" class="mt-2 space-y-2">
            <Link
                v-for="training in data"
                :key="training.id"
                :href="'/trainings/' + training.id"
                class="flex min-h-14 items-center gap-3 rounded-lg border p-2.5 transition hover:border-primary"
                :class="training.isToday ? 'border-primary/40 bg-primary/5' : 'border-border'"
            >
                <span
                    class="flex w-14 shrink-0 flex-col items-center justify-center rounded-lg py-1"
                    :class="training.isToday ? 'bg-primary/15 text-primary' : 'bg-secondary text-muted-foreground'"
                >
                    <span class="tabular text-sm font-bold leading-none">{{ training.time }}</span>
                    <span class="mt-0.5 text-[10px] uppercase leading-none">{{ training.isToday ? 'vandaag' : training.day }}</span>
                </span>

                <span class="min-w-0 flex-1">
                    <span class="flex items-center gap-2">
                        <span class="min-w-0 truncate text-sm font-medium" :class="training.cancelled ? 'line-through' : ''">
                            {{ training.group }}
                        </span>
                        <span
                            v-if="training.cancelled"
                            class="shrink-0 rounded bg-destructive/10 px-1.5 py-0.5 text-[10px] font-medium text-destructive"
                        >
                            afgezegd
                        </span>
                    </span>

                    <span class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-muted-foreground">
                        <span class="inline-flex min-w-0 items-center gap-1">
                            <UserCog class="size-3 shrink-0" />
                            <!-- Geen trainer is geen leeg veld maar een signaal. -->
                            <span class="truncate" :class="training.trainers.length ? '' : 'text-warning'">
                                {{ training.trainers.join(', ') || 'geen trainer' }}
                            </span>
                        </span>
                        <span v-if="training.location" class="inline-flex min-w-0 items-center gap-1">
                            <MapPin class="size-3 shrink-0" />
                            <span class="truncate">{{ training.location }}</span>
                        </span>
                    </span>
                </span>
            </Link>
        </div>

        <p v-else class="mt-3 text-sm text-muted-foreground">
            Er staat niets gepland.
            <Link v-if="canPlan" href="/trainings/create" class="font-medium text-primary underline underline-offset-4"> Plan een training </Link>
        </p>
    </section>
</template>
