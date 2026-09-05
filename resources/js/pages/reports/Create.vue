<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';

interface Categorie {
    category: string;
    label: string;
    hint: string;
    rating: number | null;
}

const props = defineProps<{
    player: { id: number; name: string; position: string; age: number | null; overall_rating: number | null };
    categories: Categorie[];
    previousScores: Record<string, number>;
    previousReportedOn: string | null;
    goals: Record<string, { target: number; current: number | null; on_track: boolean }>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Rapporten', href: '/reports' },
    { title: props.player.name, href: '/players/' + props.player.id + '/reports/create' },
];

const cijfers = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];

/**
 * Voorinvullen met het vorige rapport. Dat is de kern van het 30-seconden-
 * scherm: de trainer past alleen aan wat veranderd is, in plaats van zes
 * cijfers opnieuw te kiezen.
 */
const beginwaarden = (): Record<string, number | null> =>
    Object.fromEntries(props.categories.map((c) => [c.category, props.previousScores[c.category] ?? null]));

const form = useForm<{ scores: Record<string, number | null>; note: string }>({
    scores: beginwaarden(),
    note: '',
});

// Welke rij heeft de toetsenbord-focus? Cijfertoetsen vullen die rij en
// springen door naar de volgende, zodat het scherm ook zonder muis snel is.
const actieveRij = ref(0);

const ingevuld = computed(() => props.categories.filter((c) => form.scores[c.category] !== null).length);
const compleet = computed(() => ingevuld.value === props.categories.length);

const gemiddelde = computed(() => {
    const waarden = props.categories.map((c) => form.scores[c.category]).filter((v): v is number => v !== null);

    if (waarden.length === 0) {
        return null;
    }

    return Math.round((waarden.reduce((a, b) => a + b, 0) / waarden.length) * 10);
});

const zet = (categorie: string, cijfer: number, index: number) => {
    form.scores[categorie] = cijfer;
    actieveRij.value = Math.min(index + 1, props.categories.length - 1);
};

const kleurVoor = (cijfer: number) => {
    if (cijfer >= 8) {
        return 'bg-primary text-primary-foreground';
    }

    return cijfer >= 6 ? 'bg-foreground text-background' : 'bg-warning text-warning-foreground';
};

const foutVoor = (categorie: string) => (form.errors as Record<string, string | undefined>)['scores.' + categorie];

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

const opslaan = () => form.post('/players/' + props.player.id + '/reports');
</script>

<template>
    <Head :title="'Rapport - ' + player.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <form class="mx-auto w-full max-w-3xl p-4 pb-32" @submit.prevent="opslaan">
            <!-- Kop: wie beoordeel je, en waar sta je nu -->
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">{{ player.name }}</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ player.position }}<span v-if="player.age"> &middot; {{ player.age }} jaar</span>
                        <span v-if="previousReportedOn"> &middot; vorig rapport {{ previousReportedOn }}</span>
                    </p>
                </div>

                <div class="text-right">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Nieuw gemiddelde</p>
                    <p class="tabular text-3xl font-bold leading-none" :class="gemiddelde ? 'text-primary' : 'text-muted-foreground'">
                        {{ gemiddelde ?? '—' }}
                    </p>
                </div>
            </div>

            <p v-if="previousReportedOn" class="mt-4 rounded-lg bg-secondary px-3 py-2 text-xs text-muted-foreground">
                De cijfers van het vorige rapport staan al ingevuld. Pas alleen aan wat veranderd is.
            </p>

            <!-- De zes categorieen. Eén tik per rij. -->
            <div class="mt-6 space-y-3">
                <div
                    v-for="(categorie, index) in categories"
                    :key="categorie.category"
                    class="rounded-xl border bg-card p-4 shadow-sm transition-colors"
                    :class="actieveRij === index ? 'border-primary' : 'border-border'"
                    @click="actieveRij = index"
                >
                    <div class="flex items-baseline justify-between gap-3">
                        <div>
                            <p class="font-medium leading-tight">{{ categorie.label }}</p>
                            <p class="text-xs text-muted-foreground">{{ categorie.hint }}</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2 text-xs">
                            <!-- Actief doel: een klein signaal, geen extra klik -->
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

                    <!-- h-11 = 44px tikhoogte, ook op telefoon. Vierkante knoppen zouden
                         op 375px maar 30px hoog worden, en dat tikt niet lekker. -->
                    <div class="mt-3 grid grid-cols-10 gap-1 sm:gap-1.5">
                        <button
                            v-for="cijfer in cijfers"
                            :key="cijfer"
                            type="button"
                            class="tabular flex h-11 items-center justify-center rounded-lg border text-sm font-semibold transition sm:h-12 sm:text-base"
                            :class="
                                form.scores[categorie.category] === cijfer
                                    ? kleurVoor(cijfer) + ' border-transparent'
                                    : 'border-border bg-background text-muted-foreground hover:border-primary hover:text-foreground'
                            "
                            :aria-label="categorie.label + ': cijfer ' + cijfer"
                            :aria-pressed="form.scores[categorie.category] === cijfer"
                            @click.stop="zet(categorie.category, cijfer, index)"
                        >
                            {{ cijfer }}
                        </button>
                    </div>

                    <InputError class="mt-2" :message="foutVoor(categorie.category)" />
                </div>
            </div>

            <!-- Toelichting is optioneel: hij mag het 30-seconden-ritme niet breken -->
            <div class="mt-6">
                <label for="note" class="text-sm font-medium">Toelichting <span class="text-muted-foreground">(optioneel)</span></label>
                <textarea
                    id="note"
                    v-model="form.note"
                    rows="3"
                    class="mt-2 w-full rounded-lg border border-input bg-background p-3 text-sm outline-none focus:border-primary"
                    placeholder="Bijvoorbeeld: sterk op de lijn, mag eerder uitkomen bij voorzetten."
                ></textarea>
                <InputError class="mt-2" :message="form.errors.note" />
            </div>

            <!-- Vaste balk onderaan: opslaan is altijd binnen duimbereik -->
            <div class="fixed inset-x-0 bottom-0 border-t border-border bg-card/95 backdrop-blur">
                <div class="mx-auto flex w-full max-w-3xl items-center justify-between gap-4 p-4">
                    <p class="tabular text-sm text-muted-foreground">{{ ingevuld }} van {{ categories.length }} ingevuld</p>
                    <Button type="submit" size="lg" :disabled="form.processing || !compleet">
                        <LoaderCircle v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                        Rapport opslaan
                    </Button>
                </div>
            </div>
        </form>
    </AppLayout>
</template>
