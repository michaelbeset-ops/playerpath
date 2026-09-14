<script setup lang="ts">
import CardVariantChoice from '@/components/cards/CardVariantChoice.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { kleurVan } from '@/lib/grade';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Ear, Flame, LoaderCircle, Palette, Plus, TriangleAlert, Trash2, UserCheck } from 'lucide-vue-next';
import { computed, ref } from 'vue';

/**
 * Mijn bedrijf → Spelerskaart.
 *
 * Bovenaan de keuze tussen de twee kaarten, met een waarschuwing vóórdat je
 * wisselt: wat er met de punten en de bestaande gegevens gebeurt. Daaronder
 * de instellingen van de gekozen kaart.
 */
interface Trede {
    key?: string;
    label: string;
    points: number;
}

interface Niveau {
    key?: string;
    label: string;
    color: string;
}

const props = defineProps<{
    mode: 'prestatie' | 'inzet';
    grading: 'kleuren' | 'cijfers';
    attendancePoints: number;
    effortLevels: Trede[];
    attitudeLevels: Trede[];
    progressLevels: Niveau[];
    palette: string[];
    counts: { reports: number; efforts: number; courses: number };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Spelerskaart', href: '/instellingen/spelerskaart' }];

// De keuze: pas na "Ja, overstappen" gaat hij naar de server.
const gekozen = ref(props.mode);
const wisselt = computed(() => gekozen.value !== props.mode);
const bezig = ref(false);

const bevestig = () => {
    bezig.value = true;
    router.post(
        '/instellingen/spelerskaart/variant',
        { mode: gekozen.value },
        { preserveScroll: true, onFinish: () => (bezig.value = false) },
    );
};

const form = useForm({
    grading: props.grading,
    attendance_points: props.attendancePoints,
    effort_levels: props.effortLevels.map((t) => ({ label: t.label, points: t.points })),
    attitude_levels: props.attitudeLevels.map((t) => ({ label: t.label, points: t.points })),
    progress_levels: props.progressLevels.map((n) => ({ label: n.label, color: n.color })),
});

const opslaan = () => form.patch('/instellingen/spelerskaart', { preserveScroll: true });

const fout = (pad: string) => (form.errors as Record<string, string | undefined>)[pad];

const invoer = 'h-11 w-full rounded-lg border border-input bg-background px-3 text-sm outline-none focus:border-primary';

const kleurNaam: Record<string, string> = { rood: 'Rood', oranje: 'Oranje', geel: 'Geel', groen: 'Groen', blauw: 'Blauw', paars: 'Paars' };
</script>

<template>
    <Head title="Spelerskaart" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-3xl p-4 pb-16">
            <h1 class="text-2xl font-semibold tracking-tight">Spelerskaart</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Hoe de ontwikkeling van jullie spelers zichtbaar wordt: met ratings, of met een kaart die inzet beloont.
            </p>

            <section class="mt-5">
                <CardVariantChoice v-model="gekozen" />

                <!-- Eerst lezen wat er gebeurt, dan pas wisselen. -->
                <div v-if="wisselt" class="mt-4 rounded-xl border border-warning/40 bg-warning/5 p-4" role="alert">
                    <p class="flex items-center gap-2 font-semibold text-warning">
                        <TriangleAlert class="size-5 shrink-0" />
                        Overstappen naar de {{ gekozen === 'inzet' ? 'inzetkaart' : 'prestatiekaart' }}?
                    </p>

                    <ul v-if="gekozen === 'inzet'" class="mt-2 list-disc space-y-1 pl-5 text-sm text-muted-foreground">
                        <li>De kaart toont geen rating en geen cijfers per categorie meer, ook niet bij spelers die al een kaart hebben.</li>
                        <li>
                            De punten van elke speler worden opnieuw opgeteld: alleen aanwezigheid en inzetpunten tellen mee.
                            <template v-if="counts.reports">Punten uit {{ counts.reports }} rapporten tellen niet meer mee, dus sommige kaarten zakken een level.</template>
                        </li>
                        <li>Rapporten worden niet verwijderd. Stap je later terug, dan staan ze er weer.</li>
                        <li>Trainers geven na de training inzetpunten in plaats van rapporten, en leggen per cursus een begin- en eindniveau vast.</li>
                    </ul>
                    <ul v-else class="mt-2 list-disc space-y-1 pl-5 text-sm text-muted-foreground">
                        <li>De kaart toont weer een rating per categorie en overall, uit de rapporten.</li>
                        <li>
                            De punten van elke speler worden opnieuw opgeteld: aanwezigheid, rapporten en groei tellen mee.
                            <template v-if="counts.efforts">Punten uit {{ counts.efforts }} keer inzet tellen niet meer mee.</template>
                        </li>
                        <li>
                            Inzetpunten<template v-if="counts.courses"> en {{ counts.courses }} cursusniveaus</template> worden niet verwijderd, maar ouders zien
                            ze niet meer. Stap je later terug, dan staan ze er weer.
                        </li>
                        <li>Trainers vullen na de training weer rapporten met cijfers in.</li>
                    </ul>

                    <div class="mt-4 flex flex-col gap-2 sm:flex-row">
                        <Button type="button" :disabled="bezig" @click="bevestig">
                            <LoaderCircle v-if="bezig" class="mr-2 size-4 animate-spin" />
                            Ja, overstappen
                        </Button>
                        <Button type="button" variant="outline" :disabled="bezig" @click="gekozen = mode">Annuleren</Button>
                    </div>
                </div>
            </section>

            <form class="mt-8 space-y-5" @submit.prevent="opslaan">
                <!-- Prestatiekaart: cijfers of kleuren in het rapport -->
                <section v-if="mode === 'prestatie'" class="rounded-xl border border-border bg-card p-5 shadow-sm">
                    <p class="font-medium">Wat trainers invullen</p>
                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                        <label
                            v-for="optie in [
                                { value: 'cijfers', title: 'Cijfers van 1 tot 10', text: 'Een 7,4 per onderdeel wordt 74 op de kaart.' },
                                { value: 'kleuren', title: 'Vier kleuren', text: 'Werkpunt, op weg, goed of top. Op de kaart staat de kleur.' },
                            ]"
                            :key="optie.value"
                            class="flex min-h-11 cursor-pointer gap-3 rounded-xl border-2 p-3 transition"
                            :class="form.grading === optie.value ? 'border-primary bg-primary/5' : 'border-border'"
                        >
                            <input v-model="form.grading" type="radio" :value="optie.value" class="mt-1 accent-[hsl(var(--primary))]" />
                            <span>
                                <span class="block text-sm font-semibold">{{ optie.title }}</span>
                                <span class="block text-xs text-muted-foreground">{{ optie.text }}</span>
                            </span>
                        </label>
                    </div>
                </section>

                <template v-else>
                    <!-- Punten -->
                    <section class="rounded-xl border border-border bg-card p-5 shadow-sm">
                        <p class="flex items-center gap-2 font-medium">
                            <UserCheck class="size-4 text-primary" />
                            Punten voor aanwezig
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">De basis: wie er is, groeit.</p>
                        <input v-model.number="form.attendance_points" type="number" min="0" max="100" :class="invoer" class="mt-3 max-w-32" />
                        <InputError class="mt-2" :message="form.errors.attendance_points" />
                    </section>

                    <section
                        v-for="soort in [
                            { veld: 'effort_levels', titel: 'Inzet', uitleg: 'Hoe hard werkte een kind? Van laag naar hoog.', icoon: Flame },
                            { veld: 'attitude_levels', titel: 'Houding en luisteren', uitleg: 'Luisterde een kind en deed het mee?', icoon: Ear },
                        ] as const"
                        :key="soort.veld"
                        class="rounded-xl border border-border bg-card p-5 shadow-sm"
                    >
                        <p class="flex items-center gap-2 font-medium">
                            <component :is="soort.icoon" class="size-4 text-primary" />
                            {{ soort.titel }}
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">{{ soort.uitleg }} Drie of vier niveaus, elk met punten. Er is geen negatieve keuze.</p>

                        <div class="mt-3 space-y-2">
                            <div v-for="(trede, i) in form[soort.veld]" :key="i" class="flex items-center gap-2">
                                <input v-model="trede.label" type="text" maxlength="30" :class="invoer" class="min-w-0 flex-1" :aria-label="'Naam niveau ' + (i + 1)" />
                                <span class="flex shrink-0 items-center gap-1 text-sm text-muted-foreground">
                                    +<input
                                        v-model.number="trede.points"
                                        type="number"
                                        min="1"
                                        max="100"
                                        :class="invoer"
                                        class="w-20"
                                        :aria-label="'Punten niveau ' + (i + 1)"
                                    />
                                </span>
                                <button
                                    type="button"
                                    class="flex size-11 shrink-0 items-center justify-center rounded-lg text-muted-foreground transition hover:text-destructive disabled:opacity-30"
                                    :disabled="form[soort.veld].length <= 3"
                                    :aria-label="'Niveau ' + (i + 1) + ' weghalen'"
                                    @click="form[soort.veld].splice(i, 1)"
                                >
                                    <Trash2 class="size-4" />
                                </button>
                            </div>
                        </div>
                        <button
                            v-if="form[soort.veld].length < 4"
                            type="button"
                            class="mt-2 inline-flex min-h-11 items-center gap-2 text-sm font-medium text-primary"
                            @click="form[soort.veld].push({ label: '', points: 20 })"
                        >
                            <Plus class="size-4" />
                            Niveau erbij
                        </button>
                        <InputError class="mt-2" :message="fout(soort.veld) ?? fout(soort.veld + '.0.label') ?? fout(soort.veld + '.0.points')" />
                    </section>

                    <!-- De kleurenschaal van de voortgang -->
                    <section class="rounded-xl border border-border bg-card p-5 shadow-sm">
                        <p class="flex items-center gap-2 font-medium">
                            <Palette class="size-4 text-primary" />
                            Kleuren voor de voortgang per cursus
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Aan het begin en eind van een cursus kiest de trainer per onderdeel een niveau. Van laag naar hoog, drie tot vijf.
                        </p>

                        <div class="mt-3 space-y-2">
                            <div v-for="(niveau, i) in form.progress_levels" :key="i" class="flex items-center gap-2">
                                <span class="inline-block size-4 shrink-0 rounded-full" :style="{ backgroundColor: kleurVan(niveau.color) }"></span>
                                <input v-model="niveau.label" type="text" maxlength="20" :class="invoer" class="min-w-0 flex-1" :aria-label="'Naam kleur ' + (i + 1)" />
                                <select v-model="niveau.color" :class="invoer" class="w-28 shrink-0" :aria-label="'Kleur ' + (i + 1)">
                                    <option v-for="kleur in palette" :key="kleur" :value="kleur">{{ kleurNaam[kleur] ?? kleur }}</option>
                                </select>
                                <button
                                    type="button"
                                    class="flex size-11 shrink-0 items-center justify-center rounded-lg text-muted-foreground transition hover:text-destructive disabled:opacity-30"
                                    :disabled="form.progress_levels.length <= 3"
                                    :aria-label="'Kleur ' + (i + 1) + ' weghalen'"
                                    @click="form.progress_levels.splice(i, 1)"
                                >
                                    <Trash2 class="size-4" />
                                </button>
                            </div>
                        </div>
                        <button
                            v-if="form.progress_levels.length < 5"
                            type="button"
                            class="mt-2 inline-flex min-h-11 items-center gap-2 text-sm font-medium text-primary"
                            @click="form.progress_levels.push({ label: '', color: 'paars' })"
                        >
                            <Plus class="size-4" />
                            Kleur erbij
                        </button>
                        <InputError class="mt-2" :message="fout('progress_levels') ?? fout('progress_levels.0.label')" />
                        <p class="mt-2 text-xs text-muted-foreground">
                            Wat al is vastgelegd blijft kloppen: een niveau wordt omgerekend naar de nieuwe schaal.
                        </p>
                    </section>
                </template>

                <div class="flex justify-end">
                    <Button type="submit" :disabled="form.processing">
                        <LoaderCircle v-if="form.processing" class="mr-2 size-4 animate-spin" />
                        Opslaan
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
