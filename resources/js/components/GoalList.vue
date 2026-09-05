<script setup lang="ts">
import { CheckCircle2, Target, XCircle } from 'lucide-vue-next';

/**
 * De doelen van een speler, leesbaar in één oogopslag.
 *
 * De balk is de afgelegde weg van start naar streef; het streepje erin is
 * waar je nu "hoort" te zijn gezien de verstreken tijd. Zit de balk voorbij
 * het streepje, dan lig je op koers. Meer uitleg is er niet nodig.
 */
export interface Doel {
    id: number;
    category: string;
    label: string;
    target: number;
    start: number;
    current: number | null;
    progress: number;
    expected: number;
    on_track: boolean;
    status: string;
    status_label: string;
    due_on: string;
    days_left: number;
    note: string | null;
    achieved_at: string | null;
}

withDefaults(
    defineProps<{
        goals: Doel[];
        /** Toon een stopknop (trainer). */
        canStop?: boolean;
    }>(),
    { canStop: false },
);

const emit = defineEmits<{ stop: [id: number] }>();

const chip = (doel: Doel) => {
    if (doel.status === 'achieved') {
        return { tekst: 'Gehaald', klas: 'bg-gold/15 text-gold' };
    }

    if (doel.status === 'missed') {
        return { tekst: 'Niet gehaald', klas: 'bg-secondary text-muted-foreground' };
    }

    if (doel.status === 'cancelled') {
        return { tekst: 'Gestopt', klas: 'bg-secondary text-muted-foreground' };
    }

    return doel.on_track ? { tekst: 'Op koers', klas: 'bg-primary/10 text-primary' } : { tekst: 'Achter op schema', klas: 'bg-warning/10 text-warning' };
};
</script>

<template>
    <div class="space-y-2">
        <div v-for="doel in goals" :key="doel.id" class="rounded-lg border border-border p-3" :class="doel.status !== 'active' ? 'opacity-80' : ''">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="flex items-center gap-2 text-sm font-medium">
                    <CheckCircle2 v-if="doel.status === 'achieved'" class="size-4 text-gold" />
                    <XCircle v-else-if="doel.status === 'missed'" class="size-4 text-muted-foreground" />
                    <Target v-else class="size-4 text-primary" />
                    {{ doel.label }} naar <span class="tabular">{{ doel.target }}</span>
                </p>
                <span class="rounded-md px-2 py-0.5 text-xs font-medium" :class="chip(doel).klas">{{ chip(doel).tekst }}</span>
            </div>

            <!-- De weg: start -> nu -> streef, met het verwachte punt als streepje -->
            <div class="relative mt-2 h-2 overflow-hidden rounded-full bg-secondary">
                <div
                    class="h-full rounded-full transition-all duration-500"
                    :class="doel.status === 'achieved' ? 'bg-gold' : doel.on_track ? 'bg-primary' : 'bg-warning'"
                    :style="{ width: doel.progress + '%' }"
                ></div>
                <span
                    v-if="doel.status === 'active'"
                    class="absolute top-0 h-full w-0.5 bg-foreground/50"
                    :style="{ left: doel.expected + '%' }"
                    title="Waar je nu hoort te zijn"
                ></span>
            </div>

            <p class="tabular mt-1.5 flex flex-wrap justify-between gap-x-3 text-xs text-muted-foreground">
                <span>Nu <span class="font-medium text-foreground">{{ doel.current ?? '—' }}</span> · gestart op {{ doel.start }}</span>
                <span v-if="doel.status === 'achieved'">Gehaald op {{ doel.achieved_at }}</span>
                <span v-else-if="doel.status === 'active'">{{ doel.days_left === 0 ? 'Vandaag' : 'Nog ' + doel.days_left + ' dagen' }} · tot {{ doel.due_on }}</span>
                <span v-else>Tot {{ doel.due_on }}</span>
            </p>

            <p v-if="doel.note" class="mt-1 text-xs text-muted-foreground">{{ doel.note }}</p>

            <button
                v-if="canStop && doel.status === 'active'"
                type="button"
                class="mt-2 text-xs text-muted-foreground underline underline-offset-4 hover:text-destructive"
                @click="emit('stop', doel.id)"
            >
                Doel stoppen
            </button>
        </div>
    </div>
</template>
