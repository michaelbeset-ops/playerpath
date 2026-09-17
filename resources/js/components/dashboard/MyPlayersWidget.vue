<script setup lang="ts">
import GradeChip from '@/components/GradeChip.vue';
import Avatar from '@/components/Avatar.vue';
import { Link } from '@inertiajs/vue3';
import { ClipboardList, Sparkles } from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * Mijn spelers, op het dashboard van de trainer.
 *
 * Gesorteerd op wie het langst niets kreeg, niet alfabetisch. Een alfabetische
 * lijst is een telefoonboek; deze lijst beantwoordt de vraag waarvoor je hem
 * opent - wie moet ik nog beoordelen.
 *
 * De kleur is een signaal en geen versiering: recent beoordeeld is groen, langer
 * dan dertig dagen (of nooit) oranje. Dezelfde grens als het aandacht-blok op
 * het dashboard van de eigenaar, zodat "te lang geleden" overal hetzelfde
 * betekent.
 */
export interface MijnSpeler {
    id: number;
    name: string;
    first_name: string;
    photo: string | null;
    rating: number | null;
    last_report_on: string | null;
    days_since_report: number | null;
    tone: 'good' | 'warning';
}

const props = defineProps<{
    data: { players: MijnSpeler[]; total: number; stale: number; staleAfterDays: number; effort?: boolean };
}>();

// Bij de inzetkaart geen cijfers en geen rapporten: het laatste moment is de
// laatste inzet, en inzet geef je vanuit de training.
const inzet = computed(() => props.data.effort === true);

const geleden = (speler: MijnSpeler) => {
    if (speler.days_since_report === null) {
        return inzet.value ? 'nog geen inzet' : 'nog geen rapport';
    }

    if (speler.days_since_report === 0) {
        return inzet.value ? 'vandaag inzet gegeven' : 'vandaag beoordeeld';
    }

    if (speler.days_since_report === 1) {
        return inzet.value ? 'gisteren inzet gegeven' : 'gisteren beoordeeld';
    }

    return speler.days_since_report + ' dagen geleden';
};

const href = (speler: MijnSpeler) => (inzet.value ? '/players/' + speler.id + '/card' : '/players/' + speler.id + '/reports/create');
const lijst = computed(() => (inzet.value ? '/trainings/mijn' : '/reports'));
</script>

<template>
    <section class="flex h-full min-w-0 flex-col rounded-xl border border-border bg-card p-4 shadow-sm sm:p-5">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="font-medium">Mijn spelers</h2>
            <Link :href="lijst" class="inline-flex min-h-11 items-center text-xs text-muted-foreground underline underline-offset-4">Alles</Link>
        </div>

        <p v-if="data.total" class="mt-1 text-xs text-muted-foreground">
            <template v-if="data.stale">
                <span class="font-medium text-warning">{{ data.stale }}</span> van {{ data.total }} wachten langer dan {{ data.staleAfterDays }} dagen
                op {{ inzet ? 'inzetpunten' : 'een rapport' }}.
            </template>
            <template v-else-if="inzet">Alle {{ data.total }} spelers kregen onlangs inzetpunten.</template>
            <template v-else>Alle {{ data.total }} spelers hebben een actueel rapport.</template>
        </p>

        <ul v-if="data.players.length" class="mt-3 min-w-0 flex-1 divide-y divide-border">
            <li v-for="speler in data.players" :key="speler.id">
                <Link :href="href(speler)" class="flex min-h-14 min-w-0 items-center gap-3 py-2">
                    <Avatar :name="speler.name" :photo="speler.photo" size="size-9" />

                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-medium">{{ speler.name }}</span>
                        <span class="block truncate text-xs" :class="speler.tone === 'warning' ? 'text-warning' : 'text-muted-foreground'">
                            {{ geleden(speler) }}
                        </span>
                    </span>

                    <span v-if="!inzet" class="tabular shrink-0 text-right">
                        <GradeChip :rating="speler.rating" size="sm">
                            <span class="block text-base font-bold leading-none" :class="speler.rating ? '' : 'text-muted-foreground'">
                                {{ speler.rating ?? '-' }}
                            </span>
                            <span class="text-[10px] uppercase tracking-wide text-muted-foreground">rating</span>
                        </GradeChip>
                    </span>

                    <Sparkles v-if="inzet" class="size-4 shrink-0 text-muted-foreground" />
                    <ClipboardList v-else class="size-4 shrink-0 text-muted-foreground" />
                </Link>
            </li>
        </ul>

        <p
            v-else
            class="mt-3 flex flex-1 items-center justify-center rounded-xl border border-dashed border-border p-6 text-center text-sm text-muted-foreground"
        >
            Nog geen spelers in jouw groepen.
        </p>

        <Link
            v-if="data.total > data.players.length"
            :href="lijst"
            class="mt-2 inline-flex min-h-11 items-center justify-center text-xs text-muted-foreground underline underline-offset-4"
        >
            Nog {{ data.total - data.players.length }} spelers
        </Link>
    </section>
</template>
