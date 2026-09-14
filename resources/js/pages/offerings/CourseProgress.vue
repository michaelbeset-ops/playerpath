<script setup lang="ts">
import Avatar from '@/components/Avatar.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { kleurVan } from '@/lib/grade';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Check, ChevronLeft, ChevronRight, GraduationCap, LoaderCircle } from 'lucide-vue-next';
import { computed, watch } from 'vue';

/**
 * Begin- en eindniveau per cursus, kind na kind, in kleuren.
 *
 * Aan het begin van een blok tikt de trainer per onderdeel aan waar een kind
 * staat, aan het eind nog eens, met een kort verslag. Ouders zien daarna bij
 * Voortgang hoe het van de ene kleur naar de andere schoof - alleen de eigen
 * lijn, nooit naast andere kinderen.
 */
interface Niveau {
    key: string;
    label: string;
    color: string;
}

const props = defineProps<{
    product: { id: number; name: string; type: string; starts_on: string | null; ends_on: string | null };
    canManage: boolean;
    levels: Niveau[];
    moment: 'begin' | 'eind';
    roster: { id: number; name: string; first_name: string; photo: string | null; begin: boolean; eind: boolean }[];
    player: { id: number; name: string; first_name: string; photo: string | null; position: string } | null;
    categories: { category: string; label: string; hint: string }[];
    current: { levels: Record<string, number>; note: string | null; date: string } | null;
    other: { levels: Record<string, number>; note: string | null; date: string } | null;
    prev: number | null;
    next: number | null;
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    ...(props.canManage ? [{ title: 'Aanbod', href: '/aanbod' }, { title: props.product.name, href: '/aanbod/' + props.product.id + '/deelnemers' }] : []),
    { title: 'Niveaus', href: '/aanbod/' + props.product.id + '/voortgang' },
]);

const beginwaarden = () => ({
    moment: props.moment,
    levels: Object.fromEntries(props.categories.map((c) => [c.category, props.current?.levels[c.category] ?? null])) as Record<string, number | null>,
    note: props.current?.note ?? '',
});

const form = useForm(beginwaarden());

watch(
    () => [props.player?.id, props.moment],
    () => {
        form.defaults(beginwaarden());
        form.reset();
        form.clearErrors();
    },
);

const gekozen = computed(() => Object.values(form.levels).filter((v) => v !== null).length);
const klaarVoorMoment = computed(() => props.roster.filter((r) => (props.moment === 'begin' ? r.begin : r.eind)).length);
const volgendeNaam = computed(
    () => props.roster.find((r) => r.id !== props.player?.id && !(props.moment === 'begin' ? r.begin : r.eind))?.first_name ?? null,
);

const kies = (categorie: string, index: number) => {
    form.levels[categorie] = form.levels[categorie] === index ? null : index;
};

const naar = (spelerId: number | null, moment = props.moment) =>
    router.get('/aanbod/' + props.product.id + '/voortgang', { ...(spelerId ? { speler: spelerId } : {}), moment }, { preserveState: false });

const opslaan = () => {
    if (!props.player) {
        return;
    }

    form.post('/aanbod/' + props.product.id + '/voortgang/' + props.player.id, { preserveScroll: false });
};
</script>

<template>
    <Head :title="'Niveaus - ' + product.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <form class="mx-auto w-full max-w-3xl p-4 pb-36" @submit.prevent="opslaan">
            <div class="flex items-start gap-3">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                    <GraduationCap class="size-5" />
                </span>
                <div class="min-w-0">
                    <h1 class="text-xl font-semibold leading-tight tracking-tight">Niveaus &middot; {{ product.name }}</h1>
                    <p class="text-xs text-muted-foreground">
                        {{ product.type }}<template v-if="product.starts_on"> &middot; {{ product.starts_on }} tot {{ product.ends_on ?? '...' }}</template>
                    </p>
                </div>
            </div>

            <!-- Begin of eind: twee tabbladen, elk met hoeveel er al gedaan zijn -->
            <div class="mt-4 grid grid-cols-2 gap-2 rounded-xl bg-secondary p-1">
                <button
                    v-for="m in ['begin', 'eind'] as const"
                    :key="m"
                    type="button"
                    class="min-h-11 rounded-lg text-sm font-medium transition"
                    :class="moment === m ? 'bg-card shadow-sm' : 'text-muted-foreground'"
                    :aria-pressed="moment === m"
                    @click="naar(player?.id ?? null, m)"
                >
                    {{ m === 'begin' ? 'Beginniveau' : 'Eindniveau + verslag' }}
                    <span class="tabular block text-xs text-muted-foreground">{{ roster.filter((r) => (m === 'begin' ? r.begin : r.eind)).length }} van {{ roster.length }}</span>
                </button>
            </div>

            <p v-if="!player" class="mt-6 rounded-xl border border-dashed border-border bg-card/50 p-8 text-center text-sm text-muted-foreground">
                Er doen nog geen spelers mee aan dit aanbod.
            </p>

            <template v-else>
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
                            <p class="truncate text-lg font-semibold leading-tight">{{ player.name }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ player.position }}
                                <template v-if="current"> &middot; vastgelegd op {{ current.date }}</template>
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

                <div class="mt-4 space-y-3">
                    <div v-for="c in categories" :key="c.category" class="rounded-xl border border-border bg-card p-4 shadow-sm">
                        <div class="flex items-baseline justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-medium leading-tight">{{ c.label }}</p>
                                <p class="text-xs text-muted-foreground">{{ c.hint }}</p>
                            </div>
                            <p v-if="moment === 'eind' && other && other.levels[c.category] !== undefined" class="shrink-0 text-xs text-muted-foreground">
                                begin:
                                <span class="font-semibold" :style="{ color: kleurVan(levels[other.levels[c.category]]?.color) }">{{
                                    levels[other.levels[c.category]]?.label
                                }}</span>
                            </p>
                        </div>

                        <div class="mt-3 grid gap-1.5" :style="{ gridTemplateColumns: `repeat(${levels.length}, minmax(0, 1fr))` }">
                            <button
                                v-for="(n, i) in levels"
                                :key="n.key"
                                type="button"
                                class="flex min-h-12 items-center justify-center rounded-lg border-2 px-1 text-center text-xs font-semibold leading-tight transition sm:text-sm"
                                :style="
                                    form.levels[c.category] === i
                                        ? { backgroundColor: kleurVan(n.color), borderColor: kleurVan(n.color), color: '#fff' }
                                        : { borderColor: kleurVan(n.color, 0.35), color: kleurVan(n.color) }
                                "
                                :aria-pressed="form.levels[c.category] === i"
                                @click="kies(c.category, i)"
                            >
                                {{ n.label }}
                            </button>
                        </div>
                    </div>
                </div>
                <InputError class="mt-2" :message="(form.errors as Record<string, string>).levels" />

                <div class="mt-4">
                    <label for="verslag" class="text-sm font-medium">
                        {{ moment === 'eind' ? 'Verslag over de cursus' : 'Notitie bij de start' }}
                        <span class="text-muted-foreground">(optioneel)</span>
                    </label>
                    <textarea
                        id="verslag"
                        v-model="form.note"
                        :rows="moment === 'eind' ? 4 : 2"
                        class="mt-2 w-full rounded-lg border border-input bg-background p-3 text-sm outline-none focus:border-primary"
                        :placeholder="
                            moment === 'eind'
                                ? 'Bijvoorbeeld: Sem is veel zekerder geworden bij hoge ballen en coacht zijn verdediging nu hardop.'
                                : 'Bijvoorbeeld: nog wat afwachtend bij uitkomen.'
                        "
                    ></textarea>
                    <p class="mt-1 text-xs text-muted-foreground">Ouders en {{ player.first_name }} lezen dit bij Voortgang.</p>
                    <InputError class="mt-2" :message="form.errors.note" />
                </div>

                <div class="mt-6">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                        Deelnemers &middot; <span class="tabular">{{ klaarVoorMoment }} van {{ roster.length }}</span> gedaan
                    </p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <button
                            v-for="rij in roster"
                            :key="rij.id"
                            type="button"
                            class="inline-flex min-h-11 items-center gap-2 rounded-xl border px-3 text-sm transition"
                            :class="
                                rij.id === player.id
                                    ? 'border-primary bg-primary/10 font-medium text-primary'
                                    : (moment === 'begin' ? rij.begin : rij.eind)
                                      ? 'border-border bg-card/60 text-muted-foreground'
                                      : 'border-border bg-card hover:border-primary'
                            "
                            @click="naar(rij.id)"
                        >
                            <Check v-if="moment === 'begin' ? rij.begin : rij.eind" class="size-3.5 shrink-0" />
                            {{ rij.first_name }}
                        </button>
                    </div>
                </div>

                <p v-if="canManage" class="mt-4 text-center text-xs text-muted-foreground">
                    <Link :href="'/aanbod/' + product.id + '/deelnemers'" class="inline-flex min-h-11 items-center underline underline-offset-4">
                        Terug naar de deelnemers
                    </Link>
                </p>

                <div class="fixed inset-x-0 bottom-[var(--pp-tabbar)] border-t border-border bg-card/95 backdrop-blur">
                    <div class="mx-auto flex w-full max-w-3xl items-center gap-2 p-3 sm:p-4">
                        <Button type="submit" size="lg" class="h-12 min-w-0 flex-1 px-3" :disabled="form.processing || gekozen === 0">
                            <LoaderCircle v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                            <span class="truncate">
                                <template v-if="gekozen === 0">Kies per onderdeel een niveau</template>
                                <template v-else-if="volgendeNaam">Opslaan &amp; door naar {{ volgendeNaam }}</template>
                                <template v-else>Opslaan</template>
                            </span>
                        </Button>
                    </div>
                </div>
            </template>
        </form>
    </AppLayout>
</template>
