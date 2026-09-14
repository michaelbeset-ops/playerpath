<script setup lang="ts">
import Avatar from '@/components/Avatar.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Check, ChevronLeft, ChevronRight, Ear, Flame, LoaderCircle, NotebookPen, SkipForward, UserCheck, UserX } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

/**
 * De inzetflow na de training: kind na kind, zonder cijfers.
 *
 * Drie tikken per kind - aanwezig, inzet, houding - en door. Er is geen
 * negatieve keuze: niets aantikken is nul extra, nooit minder. De punten
 * staan op de knoppen, zodat de trainer weet wat een tik oplevert.
 */
interface RosterRij {
    id: number;
    name: string;
    first_name: string;
    photo: string | null;
    done: boolean;
}

interface Trede {
    key: string;
    label: string;
    points: number;
}

const props = defineProps<{
    training: { id: number; group: string; date: string; time: string };
    roster: RosterRij[];
    position: number;
    total: number;
    doneCount: number;
    player: {
        id: number;
        name: string;
        first_name: string;
        photo: string | null;
        position: string;
        age: number | null;
        level: { key: string; label: string; xp: number; next: { label: string; remaining: number } | null; progress: number };
        done: boolean;
    };
    current: { present: boolean; effort: string | null; attitude: string | null; note: string | null };
    effortLevels: Trede[];
    attitudeLevels: Trede[];
    attendancePoints: number;
    prev: number | null;
    next: number | null;
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Mijn trainingen', href: '/trainings/mijn' },
    { title: props.training.group, href: '/trainings/' + props.training.id },
    { title: 'Inzet', href: '/trainings/' + props.training.id + '/inzet' },
]);

const beginwaarden = () => ({
    present: props.current.present,
    effort: props.current.effort,
    attitude: props.current.attitude,
    note: props.current.note ?? '',
});

const form = useForm<{ present: boolean; effort: string | null; attitude: string | null; note: string }>(beginwaarden());
const notitieOpen = ref(!!props.current.note);

// Een ander kind is een nieuw formulier; anders blijft de keuze van het vorige staan.
watch(
    () => props.player.id,
    () => {
        form.defaults(beginwaarden());
        form.reset();
        form.clearErrors();
        notitieOpen.value = !!props.current.note;
    },
);

const kies = (veld: 'effort' | 'attitude', key: string) => {
    form[veld] = form[veld] === key ? null : key;
};

const punten = computed(() => {
    if (!form.present) {
        return 0;
    }

    const inzet = props.effortLevels.find((t) => t.key === form.effort)?.points ?? 0;
    const houding = props.attitudeLevels.find((t) => t.key === form.attitude)?.points ?? 0;

    return props.attendancePoints + inzet + houding;
});

const volgendeNaam = computed(() => props.roster.find((r) => !r.done && r.id !== props.player.id)?.first_name ?? null);

const kolommen = (aantal: number) => (aantal >= 4 ? 'grid-cols-2 sm:grid-cols-4' : 'grid-cols-3');

const opslaan = () => form.post('/trainings/' + props.training.id + '/inzet/' + props.player.id, { preserveScroll: false });

const naar = (id: number) => router.get('/trainings/' + props.training.id + '/inzet', { speler: id }, { preserveState: false });

const sla = () => {
    const open = props.roster.filter((r) => !r.done && r.id !== props.player.id);

    if (open.length) {
        naar(open[0].id);
    } else {
        router.get('/trainings/' + props.training.id + '/inzet/klaar');
    }
};
</script>

<template>
    <Head :title="'Inzet - ' + player.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <form class="mx-auto w-full max-w-3xl p-4 pb-36" @submit.prevent="opslaan">
            <div class="flex items-center justify-between gap-3">
                <p class="min-w-0 truncate text-xs text-muted-foreground first-letter:uppercase">{{ training.group }} &middot; {{ training.date }}</p>
                <p class="tabular shrink-0 text-xs font-medium text-muted-foreground">Speler {{ position }} van {{ total }}</p>
            </div>

            <div class="mt-2 flex gap-1" :aria-label="doneCount + ' van ' + total + ' gedaan'">
                <span
                    v-for="rij in roster"
                    :key="rij.id"
                    class="h-1.5 flex-1 rounded-full transition"
                    :class="rij.id === player.id ? 'bg-primary' : rij.done ? 'bg-primary/40' : 'bg-secondary'"
                ></span>
            </div>

            <!-- Wie, met pijltjes van 44 pixels ernaast -->
            <div class="mt-4 flex items-center gap-3">
                <button
                    type="button"
                    class="flex size-11 shrink-0 items-center justify-center rounded-xl border border-border bg-card text-muted-foreground shadow-sm transition enabled:hover:border-primary disabled:opacity-40"
                    :disabled="prev === null"
                    aria-label="Vorige speler"
                    @click="prev !== null && naar(prev)"
                >
                    <ChevronLeft class="size-5" />
                </button>

                <div class="flex min-w-0 flex-1 items-center gap-3">
                    <Avatar :name="player.name" :photo="player.photo" size="size-12" />
                    <div class="min-w-0">
                        <h1 class="truncate text-xl font-semibold leading-tight tracking-tight">{{ player.name }}</h1>
                        <p class="truncate text-xs text-muted-foreground">
                            {{ player.position }}<span v-if="player.age"> &middot; {{ player.age }} jaar</span> &middot; {{ player.level.label }}
                        </p>
                    </div>
                </div>

                <button
                    type="button"
                    class="flex size-11 shrink-0 items-center justify-center rounded-xl border border-border bg-card text-muted-foreground shadow-sm transition enabled:hover:border-primary disabled:opacity-40"
                    :disabled="next === null"
                    aria-label="Volgende speler"
                    @click="next !== null && naar(next)"
                >
                    <ChevronRight class="size-5" />
                </button>
            </div>

            <p v-if="player.done" class="mt-3 flex items-center gap-2 rounded-lg bg-primary/10 px-3 py-2 text-xs text-primary">
                <Check class="size-4 shrink-0" />
                Al ingevuld voor deze training. Opslaan vervangt wat er stond.
            </p>

            <!-- Aanwezig: de basispunten -->
            <section class="mt-4 rounded-xl border border-border bg-card p-4 shadow-sm">
                <p class="font-medium leading-tight">Was {{ player.first_name }} er?</p>
                <div class="mt-3 grid grid-cols-2 gap-2">
                    <button
                        type="button"
                        class="flex min-h-12 items-center justify-center gap-2 rounded-xl border-2 text-sm font-semibold transition"
                        :class="form.present ? 'border-primary bg-primary/10 text-primary' : 'border-border text-muted-foreground hover:border-primary/50'"
                        :aria-pressed="form.present"
                        @click="form.present = true"
                    >
                        <UserCheck class="size-4" />
                        Aanwezig <span class="tabular text-xs font-medium">+{{ attendancePoints }}</span>
                    </button>
                    <button
                        type="button"
                        class="flex min-h-12 items-center justify-center gap-2 rounded-xl border-2 text-sm font-semibold transition"
                        :class="!form.present ? 'border-foreground/40 bg-secondary text-foreground' : 'border-border text-muted-foreground hover:border-foreground/30'"
                        :aria-pressed="!form.present"
                        @click="form.present = false"
                    >
                        <UserX class="size-4" />
                        Afwezig
                    </button>
                </div>
            </section>

            <template v-if="form.present">
                <!-- Inzet -->
                <section class="mt-3 rounded-xl border border-border bg-card p-4 shadow-sm">
                    <p class="flex items-center gap-2 font-medium leading-tight">
                        <Flame class="size-4 text-primary" />
                        Inzet
                    </p>
                    <p class="text-xs text-muted-foreground">Hoe hard werkte {{ player.first_name }}? Niets kiezen mag ook.</p>
                    <div class="mt-3 grid gap-2" :class="kolommen(effortLevels.length)">
                        <button
                            v-for="trede in effortLevels"
                            :key="trede.key"
                            type="button"
                            class="flex min-h-14 flex-col items-center justify-center rounded-xl border-2 px-1.5 py-2 text-center text-sm font-semibold leading-tight transition"
                            :class="form.effort === trede.key ? 'border-primary bg-primary/10 text-primary' : 'border-border hover:border-primary/50'"
                            :aria-pressed="form.effort === trede.key"
                            @click="kies('effort', trede.key)"
                        >
                            {{ trede.label }}
                            <span class="tabular mt-0.5 text-xs font-medium text-muted-foreground">+{{ trede.points }}</span>
                        </button>
                    </div>
                    <InputError class="mt-2" :message="form.errors.effort" />
                </section>

                <!-- Houding en luisteren -->
                <section class="mt-3 rounded-xl border border-border bg-card p-4 shadow-sm">
                    <p class="flex items-center gap-2 font-medium leading-tight">
                        <Ear class="size-4 text-primary" />
                        Houding en luisteren
                    </p>
                    <p class="text-xs text-muted-foreground">Luisterde {{ player.first_name }} en deed het mee?</p>
                    <div class="mt-3 grid gap-2" :class="kolommen(attitudeLevels.length)">
                        <button
                            v-for="trede in attitudeLevels"
                            :key="trede.key"
                            type="button"
                            class="flex min-h-14 flex-col items-center justify-center rounded-xl border-2 px-1.5 py-2 text-center text-sm font-semibold leading-tight transition"
                            :class="form.attitude === trede.key ? 'border-primary bg-primary/10 text-primary' : 'border-border hover:border-primary/50'"
                            :aria-pressed="form.attitude === trede.key"
                            @click="kies('attitude', trede.key)"
                        >
                            {{ trede.label }}
                            <span class="tabular mt-0.5 text-xs font-medium text-muted-foreground">+{{ trede.points }}</span>
                        </button>
                    </div>
                    <InputError class="mt-2" :message="form.errors.attitude" />
                </section>

                <!-- Notitie: optioneel, dus achter één tik -->
                <div class="mt-3">
                    <button
                        v-if="!notitieOpen"
                        type="button"
                        class="inline-flex min-h-11 items-center gap-2 text-sm font-medium text-primary"
                        @click="notitieOpen = true"
                    >
                        <NotebookPen class="size-4" />
                        Notitie toevoegen
                    </button>
                    <template v-else>
                        <label for="note" class="text-sm font-medium">Notitie <span class="text-muted-foreground">(optioneel)</span></label>
                        <textarea
                            id="note"
                            v-model="form.note"
                            rows="2"
                            class="mt-2 w-full rounded-lg border border-input bg-background p-3 text-sm outline-none focus:border-primary"
                            placeholder="Bijvoorbeeld: bleef positief na een lastige oefening."
                        ></textarea>
                        <p class="mt-1 text-xs text-muted-foreground">Ouders en {{ player.first_name }} zien dit op de achterkant van de kaart.</p>
                        <InputError class="mt-2" :message="form.errors.note" />
                    </template>
                </div>
            </template>

            <!-- De hele groep: één tik om te springen -->
            <div class="mt-6">
                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">De hele groep</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    <button
                        v-for="rij in roster"
                        :key="rij.id"
                        type="button"
                        class="inline-flex min-h-11 items-center gap-2 rounded-xl border px-3 text-sm transition"
                        :class="
                            rij.id === player.id
                                ? 'border-primary bg-primary/10 font-medium text-primary'
                                : rij.done
                                  ? 'border-border bg-card/60 text-muted-foreground'
                                  : 'border-border bg-card hover:border-primary'
                        "
                        @click="naar(rij.id)"
                    >
                        <Check v-if="rij.done" class="size-3.5 shrink-0" />
                        {{ rij.first_name }}
                    </button>
                </div>
            </div>

            <p class="mt-4 text-center text-xs text-muted-foreground">
                <Link :href="'/trainings/' + training.id" class="inline-flex min-h-11 items-center underline underline-offset-4">
                    Stoppen - wat je hebt opgeslagen blijft bewaard
                </Link>
            </p>

            <div class="fixed inset-x-0 bottom-[var(--pp-tabbar)] border-t border-border bg-card/95 backdrop-blur">
                <div class="mx-auto flex w-full max-w-3xl items-center gap-2 p-3 sm:p-4">
                    <button
                        type="button"
                        class="inline-flex min-h-12 shrink-0 items-center gap-1.5 rounded-xl border border-border bg-background px-3 text-sm font-medium text-muted-foreground transition hover:border-primary"
                        @click="sla"
                    >
                        <SkipForward class="size-4" />
                        Overslaan
                    </button>

                    <Button type="submit" size="lg" class="h-12 min-w-0 flex-1 px-3" :disabled="form.processing">
                        <LoaderCircle v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                        <span class="truncate">
                            <template v-if="volgendeNaam">Opslaan &amp; door naar {{ volgendeNaam }}</template>
                            <template v-else>Opslaan &amp; afronden</template>
                        </span>
                        <span v-if="punten" class="tabular ml-1.5 shrink-0 rounded-md bg-primary-foreground/20 px-1.5 py-0.5 text-xs">+{{ punten }}</span>
                    </Button>
                </div>
            </div>
        </form>
    </AppLayout>
</template>
