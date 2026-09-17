<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { CalendarRange, Flag, LoaderCircle, Medal } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

/**
 * Het seizoen: begin, eind en hoeveel weken. Aan het eind wordt elke kaart
 * bewaard als eindkaart en beginnen de punten opnieuw; de cijfers blijven.
 */
const props = defineProps<{
    season: {
        name: string | null;
        starts_on: string | null;
        ends_on: string | null;
        weeks: number | null;
        closed_at: string | null;
        is_set: boolean;
        is_active: boolean;
        has_ended: boolean;
        current_week: number | null;
        total_weeks: number | null;
        days_left: number | null;
    };
    levels: { key: string; label: string; xp: number }[];
    players: number;
    archived: number;
    suggestion: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Seizoen', href: '/seizoen' }];

// Datums in lokale tijd: toISOString() rekent in UTC en geeft rond middernacht
// (en na een zomertijdwissel) de dag ervoor.
const alsDatum = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

/** 2026-09-17 → 17-09-2026, zoals overal in de app. */
const alsNederlands = (iso: string | null) => (iso ? iso.split('-').reverse().join('-') : '');

const vandaag = alsDatum(new Date());

const form = useForm({
    name: props.season.name ?? props.suggestion,
    starts_on: props.season.starts_on ?? vandaag,
    weeks: props.season.weeks ?? 12,
    ends_on: props.season.ends_on ?? '',
});

/** Einddatum uit start + weken; wie de einddatum zelf aanpast wint. */
const eindUitWeken = (start: string, weken: number) => {
    const d = new Date(start + 'T00:00:00');

    if (Number.isNaN(d.getTime())) {
        return '';
    }

    d.setDate(d.getDate() + weken * 7 - 1);

    return alsDatum(d);
};

const zelfAangepast = ref(false);

watch(
    () => [form.starts_on, form.weeks] as const,
    ([start, weken]) => {
        if (!zelfAangepast.value) {
            form.ends_on = eindUitWeken(start, Number(weken) || 0);
        }
    },
    { immediate: !props.season.ends_on },
);

const kiesWeken = (n: number) => {
    zelfAangepast.value = false;
    form.weeks = n;
};

const opslaan = () => form.post('/seizoen', { preserveScroll: true });

const bezigSluiten = ref(false);

// Afsluiten gaat via router.post, dus de fout staat in de gedeelde errors.
const page = usePage();
const sluitFout = computed(() => (page.props.errors as Record<string, string> | undefined)?.season);

const sluitNu = () => {
    if (
        !confirm(
            'Het seizoen nu afsluiten? Van elke speler wordt de kaart van dit moment bewaard als eindkaart en de punten beginnen opnieuw. De cijfers blijven staan. Ouders en spelers krijgen bericht.',
        )
    ) {
        return;
    }

    bezigSluiten.value = true;
    router.post('/seizoen/afsluiten', {}, { preserveScroll: true, onFinish: () => (bezigSluiten.value = false) });
};

const voortgang = computed(() =>
    props.season.current_week && props.season.total_weeks ? Math.round((props.season.current_week / props.season.total_weeks) * 100) : 0,
);
</script>

<template>
    <Head title="Seizoen" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl p-4">

            <h1 class="text-2xl font-semibold tracking-tight">Seizoen</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                De punten en het level van een kaart horen bij een seizoen. Aan het eind wordt elke kaart bewaard als eindkaart en beginnen de punten
                opnieuw. De cijfers blijven staan: die zeggen hoe goed iemand is, en dat verdwijnt niet op een datum.
            </p>

            <!-- Waar we staan -->
            <section v-if="season.is_set" class="mt-6 rounded-xl border border-border bg-card p-5 shadow-sm">
                <div class="flex items-start gap-3">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        <CalendarRange class="size-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="font-medium">{{ season.name }}</p>
                        <p class="text-sm text-muted-foreground">
                            <template v-if="season.is_active">
                                Week {{ season.current_week }} van {{ season.total_weeks }} · nog
                                {{ season.days_left === 1 ? '1 dag' : season.days_left + ' dagen' }}
                            </template>
                            <template v-else-if="season.has_ended">
                                De einddatum is voorbij. Vannacht wordt het seizoen afgesloten, of doe het nu.
                            </template>
                            <template v-else>Begint op {{ alsNederlands(season.starts_on) }}.</template>
                        </p>
                        <div v-if="season.is_active" class="mt-3 h-1.5 overflow-hidden rounded-full bg-secondary">
                            <div class="h-full rounded-full bg-primary transition-all" :style="{ width: voortgang + '%' }"></div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-border pt-4">
                    <button
                        type="button"
                        class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-border px-4 text-sm font-medium transition hover:border-warning disabled:opacity-60"
                        :disabled="bezigSluiten"
                        @click="sluitNu"
                    >
                        <LoaderCircle v-if="bezigSluiten" class="size-4 animate-spin" />
                        <Flag v-else class="size-4" />
                        Seizoen nu afsluiten
                    </button>
                    <p class="text-xs text-muted-foreground">{{ players }} actieve spelers krijgen een eindkaart. Eerder bewaard: {{ archived }}.</p>
                </div>
                <InputError class="mt-2" :message="sluitFout" />
            </section>

            <div v-else-if="season.closed_at" class="mt-6 rounded-xl border border-border bg-card p-4 text-sm shadow-sm">
                Het vorige seizoen is afgesloten op {{ season.closed_at }}. Stel hieronder het volgende in; tot die tijd tellen de punten gewoon door
                vanaf de dag na het afsluiten.
            </div>

            <!-- Instellen -->
            <form class="mt-6 space-y-5 rounded-xl border border-border bg-card p-5 shadow-sm" @submit.prevent="opslaan">
                <p class="font-medium">{{ season.is_set ? 'Seizoen aanpassen' : 'Nieuw seizoen instellen' }}</p>

                <div class="grid gap-2">
                    <Label for="name">Naam</Label>
                    <Input id="name" v-model="form.name" required placeholder="Najaar 2026" />
                    <p class="text-xs text-muted-foreground">Staat onderaan elke kaart en op de eindkaart.</p>
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="starts_on">Startdatum</Label>
                        <Input id="starts_on" v-model="form.starts_on" type="date" required />
                        <p class="text-xs text-muted-foreground">Vanaf deze dag tellen de punten voor dit seizoen.</p>
                        <InputError :message="form.errors.starts_on" />
                    </div>

                    <div class="grid gap-2">
                        <Label>Duur</Label>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="n in [8, 10, 12, 16]"
                                :key="n"
                                type="button"
                                class="min-h-11 rounded-lg border px-3 text-sm transition"
                                :class="
                                    Number(form.weeks) === n
                                        ? 'border-primary bg-primary/10 font-medium text-primary'
                                        : 'border-border hover:border-primary'
                                "
                                @click="kiesWeken(n)"
                            >
                                {{ n }} weken
                            </button>
                            <Input
                                v-model="form.weeks"
                                type="number"
                                min="1"
                                max="52"
                                class="w-24"
                                aria-label="Aantal weken"
                                @input="zelfAangepast = false"
                            />
                        </div>
                        <InputError :message="form.errors.weeks" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="ends_on">Einddatum</Label>
                    <Input id="ends_on" v-model="form.ends_on" type="date" required @input="zelfAangepast = true" />
                    <p class="text-xs text-muted-foreground">
                        Volgt uit de startdatum en het aantal weken; je mag hem ook zelf zetten. De dag erna worden de kaarten bewaard.
                    </p>
                    <InputError :message="form.errors.ends_on" />
                </div>

                <div class="flex items-center gap-3">
                    <Button type="submit" :disabled="form.processing">
                        <LoaderCircle v-if="form.processing" class="mr-2 size-4 animate-spin" />
                        {{ season.is_set ? 'Opslaan' : 'Seizoen starten' }}
                    </Button>
                </div>
            </form>

            <!-- Wat het betekent -->
            <section class="mt-6 rounded-xl border border-border bg-card p-5 shadow-sm">
                <p class="flex items-center gap-2 font-medium">
                    <Medal class="size-4 text-primary" />
                    Zo werken de kaarten in een seizoen
                </p>
                <ul class="mt-3 space-y-2 text-sm text-muted-foreground">
                    <li>
                        Elke training levert punten op, en afhankelijk van jullie spelerskaart ook de inzet of het rapport. De punten bepalen de
                        kaart:
                    </li>
                    <li class="flex flex-wrap gap-2">
                        <span v-for="l in levels" :key="l.key" class="rounded-lg border border-border px-2.5 py-1 text-xs">
                            <span class="font-medium text-foreground">{{ l.label }}</span>
                            <template v-if="l.xp > 0"> vanaf {{ l.xp }} punten</template>
                            <template v-else> bij de start</template>
                        </span>
                    </li>
                    <li>
                        Aan het eind van het seizoen krijgt elke speler zijn eindkaart bij Mijn kaarten, en beginnen de punten opnieuw. Ouders en
                        spelers krijgen daar bericht van.
                    </li>
                    <li>
                        Bij de prestatiekaart gaan de cijfers per categorie en de rating gewoon door: die groeien over seizoenen heen. Welke kaart
                        jullie gebruiken kies je bij Spelerskaart.
                    </li>
                </ul>
            </section>
        </div>
    </AppLayout>
</template>
