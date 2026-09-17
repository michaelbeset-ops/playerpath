<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { CalendarOff, Check, LoaderCircle, Trash2, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';

/**
 * Mijn beschikbaarheid.
 *
 * Zeven dagen bij drie dagdelen, en daaronder de uitzonderingen. Bewust niet
 * fijner: beschikbaarheid in kwartieren vragen is een agenda bouwen die niemand
 * invult. Een trainer weet wél of hij dinsdagavond kan, en dat is genoeg om een
 * planning op te maken.
 *
 * Op een telefoon is elke dag een rij met drie knoppen van 44 pixels hoog. Een
 * raster van zeven kolommen zou daar per knop nog geen 50 pixels overhouden, en
 * dan zet je met een duim de verkeerde dag aan.
 */
interface Uitzondering {
    id: number;
    starts_on: string;
    ends_on: string;
    daypart: string | null;
    available: boolean;
    note: string | null;
    label: string;
}

const props = defineProps<{
    grid: Record<string, Record<string, boolean>>;
    exceptions: Uitzondering[];
    hasSet: boolean;
    dayparts: { value: string; label: string; hint: string }[];
    canViewTeam: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Mijn beschikbaarheid', href: '/beschikbaarheid' }];

const dagen = [
    { nummer: 1, naam: 'Maandag', kort: 'ma' },
    { nummer: 2, naam: 'Dinsdag', kort: 'di' },
    { nummer: 3, naam: 'Woensdag', kort: 'wo' },
    { nummer: 4, naam: 'Donderdag', kort: 'do' },
    { nummer: 5, naam: 'Vrijdag', kort: 'vr' },
    { nummer: 6, naam: 'Zaterdag', kort: 'za' },
    { nummer: 7, naam: 'Zondag', kort: 'zo' },
];

// De stand in de browser; opslaan gaat in één keer, niet per vinkje. Op een
// veld met een slechte verbinding is één opslag die lukt of niet beter dan
// eenentwintig die half aankomen.
const raster = ref<Record<number, Record<string, boolean>>>(
    Object.fromEntries(
        dagen.map((d) => [d.nummer, Object.fromEntries(props.dayparts.map((p) => [p.value, props.grid?.[d.nummer]?.[p.value] === true]))]),
    ),
);

const beginstand = JSON.stringify(raster.value);
const gewijzigd = computed(() => JSON.stringify(raster.value) !== beginstand);

const aantal = computed(() => Object.values(raster.value).reduce((som, dag) => som + Object.values(dag).filter(Boolean).length, 0));

const wissel = (dag: number, dagdeel: string) => (raster.value[dag][dagdeel] = !raster.value[dag][dagdeel]);

const opslaan = useForm<{ slots: { weekday: number; daypart: string }[] }>({ slots: [] });

const bewaar = () => {
    opslaan.slots = dagen.flatMap((d) =>
        props.dayparts.filter((p) => raster.value[d.nummer][p.value]).map((p) => ({ weekday: d.nummer, daypart: p.value })),
    );

    opslaan.put('/beschikbaarheid', { preserveScroll: true });
};

// --- Uitzonderingen ---

const vandaag = new Date().toISOString().slice(0, 10);

const uitzondering = useForm<{ starts_on: string; ends_on: string; daypart: string | null; available: boolean; note: string }>({
    starts_on: vandaag,
    ends_on: vandaag,
    daypart: null,
    available: false,
    note: '',
});

const formulierOpen = ref(false);

const voegToe = () =>
    uitzondering.post('/beschikbaarheid/uitzonderingen', {
        preserveScroll: true,
        onSuccess: () => {
            uitzondering.reset();
            formulierOpen.value = false;
        },
    });

const verwijder = (id: number) => {
    if (confirm('Deze uitzondering verwijderen?')) {
        router.delete('/beschikbaarheid/uitzonderingen/' + id, { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Mijn beschikbaarheid" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-3xl p-4 pb-28">

            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <h1 class="text-2xl font-semibold tracking-tight">Mijn beschikbaarheid</h1>
                    <p class="mt-1 text-sm text-muted-foreground">Wanneer kun je normaal gesproken training geven?</p>
                </div>

                <Link
                    v-if="canViewTeam"
                    href="/personeel/beschikbaarheid"
                    class="inline-flex min-h-11 items-center gap-2 text-sm text-muted-foreground underline underline-offset-4"
                >
                    <Users class="size-4" />
                    Het hele team
                </Link>
            </div>

            <p v-if="!hasSet" class="mt-4 rounded-lg bg-secondary px-3 py-2 text-xs text-muted-foreground">
                Zolang je niets invult gaat je school ervan uit dat je altijd kunt. Vink aan wanneer het je uitkomt; uitzonderingen zet je eronder.
            </p>

            <!-- Het ritme. Eén rij per dag, drie knoppen van 44 pixels. -->
            <section class="mt-5 overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <div class="flex items-center gap-2 border-b border-border px-4 py-3">
                    <p class="min-w-0 flex-1 font-medium">Gewone week</p>
                    <p class="tabular shrink-0 text-xs text-muted-foreground">{{ aantal }} dagdelen</p>
                </div>

                <div class="divide-y divide-border">
                    <div v-for="dag in dagen" :key="dag.nummer" class="flex items-center gap-2 px-3 py-2 sm:px-4">
                        <p class="w-10 shrink-0 text-sm font-medium sm:w-24">
                            <span class="sm:hidden">{{ dag.kort }}</span>
                            <span class="hidden sm:inline">{{ dag.naam }}</span>
                        </p>

                        <div class="grid min-w-0 flex-1 grid-cols-3 gap-1.5">
                            <button
                                v-for="deel in dayparts"
                                :key="deel.value"
                                type="button"
                                class="flex min-h-11 flex-col items-center justify-center rounded-lg border px-1 text-xs font-medium transition"
                                :class="
                                    raster[dag.nummer][deel.value]
                                        ? 'border-primary bg-primary/10 text-primary'
                                        : 'border-border bg-background text-muted-foreground hover:border-primary/50'
                                "
                                :aria-pressed="raster[dag.nummer][deel.value]"
                                :aria-label="dag.naam + ' ' + deel.label + ' (' + deel.hint + ')'"
                                @click="wissel(dag.nummer, deel.value)"
                            >
                                <span class="flex items-center gap-1">
                                    <Check v-if="raster[dag.nummer][deel.value]" class="size-3.5" />
                                    {{ deel.label }}
                                </span>
                                <span class="text-[10px] font-normal opacity-70">{{ deel.hint }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Uitzonderingen: een vakantie, of één zaterdag die niet uitkomt -->
            <section class="mt-6">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="min-w-0">
                        <h2 class="font-medium">Uitzonderingen</h2>
                        <p class="text-xs text-muted-foreground">Een vakantie, of juist een week waarin je extra kunt.</p>
                    </div>

                    <button
                        type="button"
                        class="inline-flex min-h-11 shrink-0 items-center rounded-lg border border-border bg-card px-3 text-sm font-medium shadow-sm transition hover:border-primary"
                        @click="formulierOpen = !formulierOpen"
                    >
                        {{ formulierOpen ? 'Annuleren' : 'Uitzondering toevoegen' }}
                    </button>
                </div>

                <form v-if="formulierOpen" class="mt-3 rounded-xl border border-border bg-card p-4 shadow-sm" @submit.prevent="voegToe">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label for="starts_on" class="text-sm font-medium">Van</label>
                            <input
                                id="starts_on"
                                v-model="uitzondering.starts_on"
                                type="date"
                                class="mt-1 h-11 w-full rounded-lg border border-input bg-background px-3 text-sm outline-none focus:border-primary"
                            />
                            <InputError class="mt-1" :message="uitzondering.errors.starts_on" />
                        </div>

                        <div>
                            <label for="ends_on" class="text-sm font-medium">Tot en met</label>
                            <input
                                id="ends_on"
                                v-model="uitzondering.ends_on"
                                type="date"
                                class="mt-1 h-11 w-full rounded-lg border border-input bg-background px-3 text-sm outline-none focus:border-primary"
                            />
                            <InputError class="mt-1" :message="uitzondering.errors.ends_on" />
                        </div>
                    </div>

                    <div class="mt-3">
                        <label for="daypart" class="text-sm font-medium">Dagdeel</label>
                        <select
                            id="daypart"
                            v-model="uitzondering.daypart"
                            class="mt-1 h-11 w-full rounded-lg border border-input bg-background px-3 text-sm outline-none focus:border-primary"
                        >
                            <option :value="null">De hele dag</option>
                            <option v-for="deel in dayparts" :key="deel.value" :value="deel.value">{{ deel.label }} ({{ deel.hint }})</option>
                        </select>
                    </div>

                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            class="min-h-11 rounded-lg border px-3 text-sm font-medium transition"
                            :class="
                                !uitzondering.available
                                    ? 'border-destructive bg-destructive/10 text-destructive'
                                    : 'border-border bg-background text-muted-foreground'
                            "
                            @click="uitzondering.available = false"
                        >
                            Niet beschikbaar
                        </button>
                        <button
                            type="button"
                            class="min-h-11 rounded-lg border px-3 text-sm font-medium transition"
                            :class="
                                uitzondering.available
                                    ? 'border-primary bg-primary/10 text-primary'
                                    : 'border-border bg-background text-muted-foreground'
                            "
                            @click="uitzondering.available = true"
                        >
                            Juist wél beschikbaar
                        </button>
                    </div>

                    <div class="mt-3">
                        <label for="note" class="text-sm font-medium">Toelichting <span class="text-muted-foreground">(optioneel)</span></label>
                        <input
                            id="note"
                            v-model="uitzondering.note"
                            type="text"
                            maxlength="120"
                            placeholder="Bijvoorbeeld: vakantie"
                            class="mt-1 h-11 w-full rounded-lg border border-input bg-background px-3 text-sm outline-none focus:border-primary"
                        />
                        <InputError class="mt-1" :message="uitzondering.errors.note" />
                    </div>

                    <Button type="submit" class="mt-4 w-full" size="lg" :disabled="uitzondering.processing">
                        <LoaderCircle v-if="uitzondering.processing" class="mr-2 h-4 w-4 animate-spin" />
                        Toevoegen
                    </Button>
                </form>

                <ul v-if="exceptions.length" class="mt-3 space-y-2">
                    <li v-for="rij in exceptions" :key="rij.id" class="flex items-center gap-3 rounded-xl border border-border bg-card p-3 shadow-sm">
                        <span
                            class="flex size-9 shrink-0 items-center justify-center rounded-lg"
                            :class="rij.available ? 'bg-primary/10 text-primary' : 'bg-destructive/10 text-destructive'"
                        >
                            <Check v-if="rij.available" class="size-4" />
                            <CalendarOff v-else class="size-4" />
                        </span>

                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium first-letter:uppercase">{{ rij.label }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ rij.available ? 'Wel beschikbaar' : 'Niet beschikbaar' }}<span v-if="rij.note"> &middot; {{ rij.note }}</span>
                            </p>
                        </div>

                        <button
                            type="button"
                            class="flex size-11 shrink-0 items-center justify-center rounded-lg text-muted-foreground transition hover:text-destructive"
                            :aria-label="'Uitzondering ' + rij.label + ' verwijderen'"
                            @click="verwijder(rij.id)"
                        >
                            <Trash2 class="size-4" />
                        </button>
                    </li>
                </ul>

                <p
                    v-else-if="!formulierOpen"
                    class="mt-3 rounded-xl border border-dashed border-border bg-card/50 p-6 text-center text-sm text-muted-foreground"
                >
                    Geen uitzonderingen. Je gewone week geldt.
                </p>
            </section>
        </div>

        <!-- Opslaan binnen duimbereik, en alleen als er iets te bewaren valt -->
        <div v-if="gewijzigd" class="fixed inset-x-0 bottom-[var(--pp-tabbar)] border-t border-border bg-card/95 backdrop-blur">
            <div class="mx-auto flex w-full max-w-3xl items-center justify-between gap-4 p-4">
                <p class="min-w-0 text-sm text-muted-foreground">Nog niet opgeslagen</p>
                <Button size="lg" :disabled="opslaan.processing" @click="bewaar">
                    <LoaderCircle v-if="opslaan.processing" class="mr-2 h-4 w-4 animate-spin" />
                    Opslaan
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
