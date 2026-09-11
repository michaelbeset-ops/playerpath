<script setup lang="ts">
import Avatar from '@/components/Avatar.vue';
import InputError from '@/components/InputError.vue';
import ScoreSlider from '@/components/ScoreSlider.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Check, ChevronLeft, ChevronRight, LoaderCircle, SkipForward } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

/**
 * De snelle invulflow: de hele groep achter elkaar.
 *
 * Dit is het scherm waar het product op draait. Een trainer staat na de
 * training op het veld, met één hand aan zijn telefoon, en wil er acht doen
 * zonder ergens op te wachten.
 *
 * Wat het snel houdt:
 *
 * - **De cijfers van het vorige rapport staan al ingevuld**; hij past alleen
 *   aan wat veranderd is.
 * - **Eén knop onderaan**: opslaan én door naar de volgende. Geen tussenscherm.
 * - **De knop staat vast onderin**, binnen duimbereik, en zegt wie er hierna
 *   komt — dan weet je of je nog een ronde te gaan hebt.
 * - **Overslaan zit ernaast**, want een kind dat halverwege naar huis ging
 *   beoordeel je niet.
 * - De cijfertoetsen blijven werken voor wie dit op een laptop doet.
 */
interface Categorie {
    category: string;
    label: string;
    hint: string;
    rating: number | null;
}

interface RosterRij {
    id: number;
    name: string;
    first_name: string;
    photo: string | null;
    done: boolean;
}

const props = defineProps<{
    training: { id: number; group: string; date: string; time: string };
    roster: RosterRij[];
    position: number;
    total: number;
    doneCount: number;
    player: { id: number; name: string; photo: string | null; position: string; age: number | null; overall_rating: number | null; done: boolean };
    categories: Categorie[];
    previousScores: Record<string, number>;
    previousReportedOn: string | null;
    goals: Record<string, { target: number; current: number | null; on_track: boolean }>;
    prev: number | null;
    next: number | null;
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Mijn trainingen', href: '/trainings/mijn' },
    { title: props.training.group, href: '/trainings/' + props.training.id },
    { title: 'Rapporten', href: '/trainings/' + props.training.id + '/rapporten' },
]);

const beginwaarden = () => Object.fromEntries(props.categories.map((c) => [c.category, props.previousScores[c.category] ?? null]));

const form = useForm<{ scores: Record<string, number | null>; note: string }>({
    scores: beginwaarden(),
    note: '',
});

// Van speler wisselen is een nieuw formulier. Zonder dit blijft het cijfer van
// het vorige kind staan, en dat is precies de fout die je niet terugziet.
watch(
    () => props.player.id,
    () => {
        form.defaults({ scores: beginwaarden(), note: '' });
        form.reset();
        form.clearErrors();
        actieveRij.value = 0;
    },
);

const actieveRij = ref(0);

const ingevuld = computed(() => props.categories.filter((c) => form.scores[c.category] !== null).length);
const compleet = computed(() => ingevuld.value === props.categories.length);

/** Naar boven afgerond, net als CalculatePlayerCard::afronden(). */
const gemiddelde = computed(() => {
    const waarden = props.categories.map((c) => form.scores[c.category]).filter((v): v is number => v !== null);

    if (waarden.length === 0) {
        return null;
    }

    return Math.ceil((waarden.reduce((a, b) => a + b, 0) / waarden.length) * 10 - 0.0001);
});

const volgendeNaam = computed(() => {
    const open = props.roster.filter((r) => !r.done && r.id !== props.player.id);

    return open.length ? open[0].first_name : null;
});

const foutVoor = (categorie: string) => (form.errors as Record<string, string | undefined>)['scores.' + categorie];

const zet = (categorie: string, cijfer: number, index: number) => {
    form.scores[categorie] = cijfer;
    actieveRij.value = Math.min(index + 1, props.categories.length - 1);
};

const opToets = (event: KeyboardEvent) => {
    if (event.target instanceof HTMLTextAreaElement || event.target instanceof HTMLInputElement) {
        return;
    }

    const categorie = props.categories[actieveRij.value];

    if (!categorie) {
        return;
    }

    if (event.key >= '1' && event.key <= '9') {
        zet(categorie.category, Number(event.key), actieveRij.value);
        event.preventDefault();
    } else if (event.key === '0') {
        zet(categorie.category, 10, actieveRij.value);
        event.preventDefault();
    } else if (event.key === 'ArrowDown') {
        actieveRij.value = Math.min(actieveRij.value + 1, props.categories.length - 1);
        event.preventDefault();
    } else if (event.key === 'ArrowUp') {
        actieveRij.value = Math.max(actieveRij.value - 1, 0);
        event.preventDefault();
    }
};

onMounted(() => window.addEventListener('keydown', opToets));
onUnmounted(() => window.removeEventListener('keydown', opToets));

const opslaan = () => form.post('/trainings/' + props.training.id + '/rapporten/' + props.player.id, { preserveScroll: false });

const naar = (id: number) => router.get('/trainings/' + props.training.id + '/rapporten', { speler: id }, { preserveState: false });

// Overslaan: de volgende die nog open staat, anders de reeks uit. Wie je
// overslaat blijft open staan en komt aan het eind vanzelf weer langs.
const sla = () => {
    const open = props.roster.filter((r) => !r.done && r.id !== props.player.id);

    if (open.length) {
        naar(open[0].id);
    } else {
        router.get('/trainings/' + props.training.id + '/rapporten/klaar');
    }
};
</script>

<template>
    <Head :title="'Rapport - ' + player.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <form class="mx-auto w-full max-w-3xl p-4 pb-36" data-tour="quick-report" @submit.prevent="opslaan">
            <!-- Waar ben ik: welke training, en hoe ver ben ik -->
            <div class="flex items-center justify-between gap-3">
                <p class="min-w-0 truncate text-xs text-muted-foreground first-letter:uppercase">{{ training.group }} &middot; {{ training.date }}</p>
                <p class="tabular shrink-0 text-xs font-medium text-muted-foreground">Speler {{ position }} van {{ total }}</p>
            </div>

            <!-- Voortgangsbalk: één blik, geen tellen -->
            <div class="mt-2 flex gap-1" :aria-label="doneCount + ' van ' + total + ' rapporten gedaan'">
                <span
                    v-for="rij in roster"
                    :key="rij.id"
                    class="h-1.5 flex-1 rounded-full transition"
                    :class="rij.id === player.id ? 'bg-primary' : rij.done ? 'bg-primary/40' : 'bg-secondary'"
                ></span>
            </div>

            <!-- Wie beoordeel je. Pijltjes ernaast, 44 pixels, duimbereik. -->
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
                            {{ player.position }}<span v-if="player.age"> &middot; {{ player.age }} jaar</span>
                            <span v-if="player.overall_rating"> &middot; nu {{ player.overall_rating }}</span>
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
                Deze speler heeft vandaag al een rapport. Opslaan zet er een tweede bij.
            </p>

            <div class="mt-4 flex items-baseline justify-between gap-3 rounded-xl border border-border bg-card px-4 py-3 shadow-sm">
                <p class="text-xs text-muted-foreground">
                    <template v-if="previousReportedOn">Vorig rapport {{ previousReportedOn }} — pas alleen aan wat veranderd is.</template>
                    <template v-else>Eerste rapport voor deze speler.</template>
                </p>
                <p class="shrink-0 text-right">
                    <span class="block text-[10px] uppercase tracking-wide text-muted-foreground">Wordt</span>
                    <span class="tabular block text-2xl font-bold leading-none" :class="gemiddelde ? 'text-primary' : 'text-muted-foreground'">
                        {{ gemiddelde ?? '—' }}
                    </span>
                </p>
            </div>

            <!-- De zes categorieën -->
            <div class="mt-4 space-y-3">
                <div
                    v-for="(categorie, index) in categories"
                    :key="categorie.category"
                    class="rounded-xl border bg-card p-4 shadow-sm transition-colors"
                    :class="actieveRij === index ? 'border-primary' : 'border-border'"
                    @click="actieveRij = index"
                >
                    <div class="flex items-baseline justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-medium leading-tight">{{ categorie.label }}</p>
                            <p class="text-xs text-muted-foreground">{{ categorie.hint }}</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2 text-xs">
                            <span
                                v-if="goals[categorie.category]"
                                class="tabular rounded-md px-1.5 py-0.5 font-medium"
                                :class="goals[categorie.category].on_track ? 'bg-primary/10 text-primary' : 'bg-warning/10 text-warning'"
                                :title="goals[categorie.category].on_track ? 'Op koers' : 'Achter op schema'"
                            >
                                doel {{ goals[categorie.category].target }}
                            </span>
                            <p v-if="categorie.rating !== null" class="tabular text-muted-foreground">nu {{ categorie.rating }}</p>
                        </div>
                    </div>

                    <ScoreSlider
                        :id="'score-' + categorie.category"
                        v-model="form.scores[categorie.category]"
                        class="mt-3"
                        :label="categorie.label"
                    />

                    <InputError class="mt-2" :message="foutVoor(categorie.category)" />
                </div>
            </div>

            <div class="mt-5">
                <label for="note" class="text-sm font-medium">Toelichting <span class="text-muted-foreground">(optioneel)</span></label>
                <textarea
                    id="note"
                    v-model="form.note"
                    rows="2"
                    class="mt-2 w-full rounded-lg border border-input bg-background p-3 text-sm outline-none focus:border-primary"
                    placeholder="Bijvoorbeeld: sterk op de lijn, mag eerder uitkomen bij voorzetten."
                ></textarea>
                <InputError class="mt-2" :message="form.errors.note" />
            </div>

            <!-- Wie komen er nog: één tik om te springen -->
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
                    Stoppen — wat je hebt ingevuld blijft bewaard
                </Link>
            </p>

            <!-- Vaste balk onderaan: één grote knop, binnen duimbereik -->
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

                    <Button type="submit" size="lg" class="h-12 min-w-0 flex-1 px-3" :disabled="form.processing || !compleet">
                        <LoaderCircle v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                        <span class="truncate">
                            <template v-if="!compleet">{{ ingevuld }} van {{ categories.length }} ingevuld</template>
                            <template v-else-if="volgendeNaam">Opslaan &amp; door naar {{ volgendeNaam }}</template>
                            <template v-else>Opslaan &amp; afronden</template>
                        </span>
                    </Button>
                </div>
            </div>
        </form>
    </AppLayout>
</template>
