<script setup lang="ts">
import Avatar from '@/components/Avatar.vue';
import { Link } from '@inertiajs/vue3';
import { Check, ClipboardList, MapPin } from 'lucide-vue-next';

/**
 * "Je training is bijna klaar - vul de rapporten in."
 *
 * Het moment waarop een trainer een rapport invult is het moment dat hij nog op
 * het veld staat. Vandaar dit blok bovenaan, in het venster rond de eindtijd.
 *
 * Eén tik per speler: van hier rechtstreeks naar zijn invulscherm. Wie al een
 * rapport heeft staat er grijs bij met een vinkje - je moet kunnen zien wie je
 * nog mist zonder te tellen.
 */
export interface Herinnering {
    id: number;
    /** Naar de snelle invulflow van deze training. */
    href: string;
    group: string;
    time: string;
    date: string;
    location: string | null;
    ended: boolean;
    players: { id: number; name: string; photo: string | null; done: boolean }[];
    done: number;
    open: number;
    total: number;
}

defineProps<{ prompts: Herinnering[] }>();
</script>

<template>
    <div v-if="prompts.length" class="space-y-3">
        <section
            v-for="training in prompts"
            :key="training.id"
            class="rounded-2xl border border-primary/40 bg-primary/5 p-4 sm:p-5"
            :aria-label="'Rapporten invullen voor ' + training.group"
        >
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="flex items-center gap-2 font-medium">
                        <ClipboardList class="size-4 shrink-0 text-primary" />
                        Rapporten invullen &middot; {{ training.group }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground first-letter:uppercase">
                        {{ training.date }} &middot; {{ training.time }}
                        <span v-if="training.location" class="inline-flex items-center gap-1">
                            &middot; <MapPin class="size-3" />{{ training.location }}
                        </span>
                    </p>
                </div>

                <p class="tabular shrink-0 text-sm">
                    <span class="font-bold text-primary">{{ training.done }}</span>
                    <span class="text-muted-foreground">/{{ training.total }} gedaan</span>
                </p>
            </div>

            <!-- De hoofdweg: alle spelers achter elkaar, zonder terug naar een
                 lijst. Groot en bovenaan, want dit is wat je na een training wilt. -->
            <Link
                :href="training.href"
                class="mt-4 flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
            >
                <ClipboardList class="size-4 shrink-0" />
                {{ training.open === 1 ? 'Nog één speler invullen' : training.open + ' spelers invullen' }}
            </Link>

            <p class="mt-3 text-xs text-muted-foreground">Of kies er een:</p>

            <!-- Eén tik naar het invulscherm van die speler. Wie klaar is staat
                 er grijs bij, zodat je ziet wie je nog mist zonder te tellen. -->
            <div class="mt-2 grid gap-2 sm:grid-cols-2">
                <Link
                    v-for="speler in training.players"
                    :key="speler.id"
                    :href="training.href + '?speler=' + speler.id"
                    class="flex min-h-14 items-center gap-3 rounded-xl border p-2.5 transition"
                    :class="speler.done ? 'border-border bg-card/60 text-muted-foreground' : 'border-border bg-card hover:border-primary'"
                >
                    <Avatar :name="speler.name" :photo="speler.photo" size="size-9" />

                    <span class="min-w-0 flex-1 truncate text-sm" :class="speler.done ? '' : 'font-medium'">
                        {{ speler.name }}
                    </span>

                    <span
                        v-if="speler.done"
                        class="flex size-6 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"
                        title="Rapport ingevuld"
                    >
                        <Check class="size-3.5" />
                    </span>
                    <span v-else class="shrink-0 text-xs font-medium text-primary">Invullen</span>
                </Link>
            </div>

            <p v-if="!training.players.length" class="mt-3 text-sm text-muted-foreground">Er zitten geen actieve spelers in deze groep.</p>
        </section>
    </div>
</template>
