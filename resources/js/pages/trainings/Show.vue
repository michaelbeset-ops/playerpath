<script setup lang="ts">
import RegistrationDialog from '@/components/RegistrationDialog.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Banknote,
    CalendarDays,
    CalendarPlus,
    CalendarX2,
    Check,
    Clock,
    MapPin,
    MessageSquareText,
    Pencil,
    RotateCcw,
    Trash2,
    UserCog,
    Users,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface SpelerRij {
    id: number;
    name: string;
    position: string;
    registration: string | null;
    registration_note: string | null;
    status: string | null;
    loose: boolean;
    enrollment_id: number | null;
    cash_due: boolean;
    paid: boolean;
    amount: string | null;
}

interface Aanmelding {
    id: number;
    player_id: number;
    name: string;
    position: string;
    status: string;
    status_label: string;
    payment_method: string | null;
    invited: boolean;
    since: string;
}

const props = defineProps<{
    training: {
        id: number;
        group: string;
        group_id: number | null;
        date: string;
        time: string;
        location: string | null;
        note: string | null;
        trainers: { id: number; name: string }[];
        has_passed: boolean;
        cancelled_at: string | null;
        cancellation_reason: string | null;
        open: boolean;
        is_open: boolean;
        price: string;
        is_free: boolean;
        capacity: number | null;
        spots_taken: number | null;
        spots_left: number | null;
        is_full: boolean;
        requires_approval: boolean;
        age_label: string;
        audience_label: string;
    };
    players: SpelerRij[];
    enrollments: Aanmelding[];
    enrollUrl: string | null;
    can: { record: boolean; manage: boolean; delete: boolean };
    /** Inzetkaart: na de training inzet geven. */
    effortFlow?: boolean;
    /** Hoort deze training bij een cursus: daar staan begin- en eindniveau. */
    course?: { id: number; name: string } | null;
}>();

const aanvragen = computed(() => props.enrollments.filter((e) => e.status === 'requested'));
const wachtlijst = computed(() => props.enrollments.filter((e) => e.status === 'waitlisted'));

// Afwijzen vraagt om een bericht: een "nee" zonder waarom levert een telefoontje op.
const afwijzen = ref<number | null>(null);
const afwijsBericht = ref('');

// Wat de server weigerde (bijvoorbeeld: de training is inmiddels vol) staat
// op de pagina, niet alleen in een melding die je kunt missen.
const page = usePage();
const aanmeldFout = computed(() => (page.props.errors as Record<string, string> | undefined)?.enrollment ?? null);

// Eén verzoek tegelijk: twee keer tikken op "Goedkeuren" hoort niets dubbel te doen.
const bezig = ref(false);
const opties = (extra: Record<string, unknown> = {}) => ({
    preserveScroll: true,
    onStart: () => (bezig.value = true),
    onFinish: () => (bezig.value = false),
    ...extra,
});

const keurGoed = (id: number) => router.post(`/trainings/${props.training.id}/aanmeldingen/${id}/goedkeuren`, {}, opties());
const wijsAf = (id: number) =>
    router.post(
        `/trainings/${props.training.id}/aanmeldingen/${id}/afwijzen`,
        { message: afwijsBericht.value },
        opties({ onSuccess: () => ((afwijzen.value = null), (afwijsBericht.value = '')) }),
    );
const haalVanLijst = (e: Aanmelding) => {
    if (confirm(`${e.name} van deze training halen?`)) {
        router.delete(`/trainings/${props.training.id}/aanmeldingen/${e.id}`, opties());
    }
};
const contantOntvangen = (speler: SpelerRij) =>
    router.post(`/trainings/${props.training.id}/aanmeldingen/${speler.enrollment_id}/contant`, {}, opties());

// Een ouder meldt een los ingeschreven kind af: de plek gaat naar de wachtlijst.
const schrijfUit = (speler: SpelerRij) => {
    if (confirm(`${speler.name.split(' ')[0]} afmelden voor deze training? De plek gaat naar de wachtlijst.`)) {
        router.delete(`/trainings/${props.training.id}/inschrijven/${speler.id}`, { preserveScroll: true });
    }
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Trainingen', href: '/trainings' },
    { title: props.training.group, href: '/trainings/' + props.training.id },
];

// Afzeggen stuurt meteen bericht aan de groep, dus vragen we om een reden:
// "gaat niet door" zonder waarom levert alleen maar telefoontjes op.
const toonAfzeggen = ref(false);
const reden = ref('');

const afzeggen = () => {
    router.post(
        '/trainings/' + props.training.id + '/afzeggen',
        { reason: reden.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                toonAfzeggen.value = false;
                reden.value = '';
            },
        },
    );
};

const terugzetten = () => {
    if (confirm('De training weer in het rooster zetten? De groep krijgt hier geen bericht van.')) {
        router.delete('/trainings/' + props.training.id + '/afzeggen', { preserveScroll: true });
    }
};

const aanwezig = computed(() => props.players.filter((s) => s.status === 'present').length);
const afgevinkt = computed(() => props.players.filter((s) => s.status !== null).length);

// Nog een keer op hetzelfde klikken haalt de keuze weg, zodat een vergissing
// niet vastzit.
const vink = (spelerId: number, status: string, huidig: string | null) => {
    router.patch(
        '/trainings/' + props.training.id + '/attendance/' + spelerId,
        { status: huidig === status ? null : status },
        { preserveScroll: true, preserveState: true },
    );
};

// Afmelden of weer aanmelden (ouder of speler), in de pop-up.
const dialoog = ref<{ child: { id: number; first_name: string }; mode: 'declined' | 'attending' } | null>(null);
const dialoogOpen = ref(false);

const openDialoog = (speler: SpelerRij, mode: 'declined' | 'attending') => {
    dialoog.value = { child: { id: speler.id, first_name: speler.name.split(' ')[0] }, mode };
    dialoogOpen.value = true;
};

const verwijderen = () => {
    if (confirm('Deze training verwijderen? De afgevinkte aanwezigheid verdwijnt mee.')) {
        router.delete('/trainings/' + props.training.id);
    }
};
</script>

<template>
    <Head :title="'Training - ' + training.group" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl p-4">

            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <h1 class="text-2xl font-semibold tracking-tight">{{ training.group }}</h1>
                </div>

                <Link
                    v-if="can.manage"
                    :href="'/trainings/' + training.id + '/edit'"
                    class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-border bg-card px-4 text-sm font-medium shadow-sm transition hover:border-primary"
                >
                    <Pencil class="size-4" />
                    Bewerken
                </Link>
            </div>

            <!-- De details: wanneer, waar, met wie. Voor een ouder of speler is
                 dit de pagina; aan- of afmelden hoeft niet, je bent er gewoon. -->
            <div class="mt-5 divide-y divide-border rounded-xl border border-border bg-card shadow-sm">
                <div class="flex items-start gap-3 p-4">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        <CalendarDays class="size-5" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs text-muted-foreground">Wanneer</p>
                        <p class="font-medium first-letter:uppercase">{{ training.date }}</p>
                        <p class="tabular flex items-center gap-1.5 text-sm text-muted-foreground">
                            <Clock class="size-3.5" />
                            {{ training.time }}
                        </p>
                    </div>
                </div>

                <div v-if="training.location" class="flex items-start gap-3 p-4">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        <MapPin class="size-5" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs text-muted-foreground">Waar</p>
                        <p class="break-words font-medium">{{ training.location }}</p>
                    </div>
                </div>

                <div v-if="training.trainers.length" class="flex items-start gap-3 p-4">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        <UserCog class="size-5" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs text-muted-foreground">{{ training.trainers.length === 1 ? 'Trainer' : 'Trainers' }}</p>
                        <p class="break-words font-medium">{{ training.trainers.map((t) => t.name).join(', ') }}</p>
                    </div>
                </div>

                <div v-if="!can.record && players.length" class="flex items-start gap-3 p-4">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        <Users class="size-5" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs text-muted-foreground">Voor</p>
                        <p class="break-words font-medium">{{ players.map((s) => s.name.split(' ')[0]).join(' en ') }}</p>
                        <!-- Vooraf: afmelden of weer aanmelden, per kind. -->
                        <div v-if="!training.has_passed && !training.cancelled_at" class="mt-2 flex flex-col gap-2">
                            <div v-for="speler in players" :key="speler.id" class="flex items-center justify-between gap-3">
                                <span class="text-sm">
                                    {{ speler.name.split(' ')[0] }}
                                    <span v-if="speler.loose" class="text-primary">· ingeschreven</span>
                                    <span v-else-if="speler.registration === 'declined'" class="text-warning">· afgemeld</span>
                                    <span v-else-if="speler.registration === 'attending'" class="text-primary">· aangemeld</span>
                                    <span v-if="speler.loose && speler.cash_due" class="block text-xs text-muted-foreground">
                                        {{ speler.amount }} contant te voldoen bij de training
                                    </span>
                                    <span v-else-if="speler.loose && speler.paid" class="block text-xs text-primary">betaald</span>
                                </span>
                                <button
                                    v-if="speler.loose"
                                    type="button"
                                    class="inline-flex min-h-11 shrink-0 items-center rounded-lg border border-border px-3 text-sm font-medium text-muted-foreground transition hover:border-warning hover:text-warning"
                                    @click="schrijfUit(speler)"
                                >
                                    Afmelden
                                </button>
                                <button
                                    v-else-if="speler.registration === 'declined'"
                                    type="button"
                                    class="inline-flex min-h-11 shrink-0 items-center rounded-lg border border-border px-3 text-sm font-medium transition hover:border-primary"
                                    @click="openDialoog(speler, 'attending')"
                                >
                                    Weer aanmelden
                                </button>
                                <button
                                    v-else
                                    type="button"
                                    class="inline-flex min-h-11 shrink-0 items-center rounded-lg border border-border px-3 text-sm font-medium text-muted-foreground transition hover:border-warning hover:text-warning"
                                    @click="openDialoog(speler, 'declined')"
                                >
                                    Afmelden
                                </button>
                            </div>
                        </div>

                        <p v-for="e in enrollments" :key="e.id" class="mt-2 text-sm">
                            {{ e.name.split(' ')[0] }}
                            <span :class="e.status === 'requested' ? 'text-warning' : 'text-muted-foreground'"
                                >· {{ e.status_label.toLowerCase() }}</span
                            >
                            <span v-if="e.invited" class="text-primary"> · er is plek, schrijf nu in</span>
                        </p>

                        <!-- Na afloop: wat de trainer heeft afgevinkt, per kind. -->
                        <p v-if="training.has_passed" class="mt-0.5 text-sm text-muted-foreground">
                            <template v-for="(speler, index) in players" :key="speler.id">
                                <template v-if="index > 0"> &middot; </template>
                                {{ speler.name.split(' ')[0] }}
                                <span :class="speler.status === 'present' ? 'text-primary' : ''">
                                    {{ speler.status === 'present' ? 'was erbij' : speler.status === 'absent' ? 'was er niet' : 'niet afgevinkt' }}
                                </span>
                            </template>
                        </p>
                    </div>
                </div>

                <!-- Los inschrijven: voor wie, wat kost het, is er plek. -->
                <div v-if="training.open" class="flex items-start gap-3 p-4">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-gold/15 text-gold">
                        <CalendarPlus class="size-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs text-muted-foreground">Los inschrijven</p>
                        <p class="font-medium">{{ training.audience_label }} · {{ training.age_label }}</p>
                        <p class="tabular text-sm text-muted-foreground">
                            {{ training.is_free ? 'Gratis' : training.price + ' per training' }}
                            <template v-if="training.capacity !== null">
                                · {{ training.spots_taken }} van {{ training.capacity }} plekken bezet<template v-if="training.is_full">
                                    (vol)</template
                                >
                            </template>
                            <template v-else> · onbeperkt aantal plekken</template>
                            <template v-if="training.requires_approval"> · na goedkeuring</template>
                        </p>
                        <Link
                            v-if="enrollUrl && training.is_open"
                            :href="enrollUrl"
                            class="mt-2 inline-flex min-h-11 items-center gap-2 rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                        >
                            <CalendarPlus class="size-4" />
                            {{ training.is_full ? 'Op de wachtlijst' : 'Inschrijven' }}
                        </Link>
                    </div>
                </div>

                <div v-if="training.note" class="flex items-start gap-3 p-4">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-gold/15 text-gold">
                        <MessageSquareText class="size-5" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs text-muted-foreground">Van de trainer</p>
                        <p class="whitespace-pre-line text-sm">{{ training.note }}</p>
                    </div>
                </div>
            </div>

            <!-- Afgezegd: blijft in het rooster staan, maar duidelijk gemarkeerd -->
            <div v-if="training.cancelled_at" class="mt-4 rounded-xl border border-warning/30 bg-warning/10 p-4">
                <p class="flex items-center gap-2 font-medium text-warning">
                    <CalendarX2 class="size-4" />
                    Deze training gaat niet door
                </p>
                <p class="mt-1 text-sm">{{ training.cancellation_reason }}</p>
                <p class="tabular mt-1 text-xs text-muted-foreground">Afgezegd op {{ training.cancelled_at }}, de groep heeft bericht gehad.</p>

                <button
                    v-if="can.manage"
                    type="button"
                    class="mt-3 inline-flex min-h-11 items-center gap-2 text-xs text-muted-foreground underline underline-offset-4 hover:text-foreground"
                    @click="terugzetten"
                >
                    <RotateCcw class="size-3.5" />
                    Toch weer laten doorgaan
                </button>
            </div>

            <!-- Afzeggen -->
            <div v-else-if="can.manage && !training.has_passed" class="mt-4 rounded-xl border border-border bg-card p-5 shadow-sm">
                <template v-if="!toonAfzeggen">
                    <p class="font-medium">Gaat de training niet door?</p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Alle ouders en spelers van {{ training.group }} krijgen meteen bericht, in de app en per e-mail.
                    </p>
                    <Button variant="secondary" class="mt-3" @click="toonAfzeggen = true">
                        <CalendarX2 class="mr-2 size-4" />
                        Training afzeggen
                    </Button>
                </template>

                <form v-else @submit.prevent="afzeggen">
                    <p class="font-medium">Training afzeggen</p>
                    <label for="reden" class="mt-3 block text-sm">Waarom gaat het niet door?</label>
                    <input
                        id="reden"
                        v-model="reden"
                        maxlength="200"
                        required
                        placeholder="Het veld staat onder water."
                        class="mt-1 h-10 w-full rounded-lg border border-input bg-background px-3 text-sm outline-none focus:border-primary"
                    />
                    <p class="mt-2 text-xs text-muted-foreground">Dit komt letterlijk in het bericht te staan.</p>

                    <div class="mt-3 flex items-center gap-3">
                        <Button type="submit" variant="destructive" :disabled="!reden">Afzeggen en iedereen berichten</Button>
                        <button
                            type="button"
                            class="inline-flex min-h-11 items-center text-sm text-muted-foreground underline underline-offset-4"
                            @click="toonAfzeggen = false"
                        >
                            Annuleren
                        </button>
                    </div>
                </form>
            </div>

            <!-- Losse aanmeldingen (school): aanvragen en de wachtlijst. -->
            <div v-if="can.record && training.open" class="mt-6 rounded-xl border border-border bg-card p-5 shadow-sm">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <p class="font-medium">Aanmeldingen</p>
                    <p class="tabular text-sm text-muted-foreground">
                        <template v-if="training.capacity !== null">{{ training.spots_taken }} van {{ training.capacity }} plekken bezet</template>
                        <template v-else>{{ training.spots_taken }} ingeschreven · onbeperkt</template>
                    </p>
                </div>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ training.audience_label }} · {{ training.age_label }} · {{ training.is_free ? 'gratis' : training.price }}
                    <template v-if="training.requires_approval"> · goedkeuring nodig</template>
                </p>

                <p v-if="aanmeldFout" role="alert" class="mt-3 rounded-lg border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive">
                    {{ aanmeldFout }}
                </p>

                <!-- Aanvragen -->
                <div v-if="aanvragen.length" class="mt-4">
                    <p class="text-sm font-semibold text-warning">
                        {{ aanvragen.length === 1 ? 'Eén aanvraag wacht' : aanvragen.length + ' aanvragen wachten' }}
                    </p>
                    <ul class="mt-2 space-y-2">
                        <li v-for="e in aanvragen" :key="e.id" class="rounded-lg border border-warning/40 bg-warning/5 p-3">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium">{{ e.name }}</p>
                                    <p class="text-xs text-muted-foreground">{{ e.position }} · aangevraagd op {{ e.since }}</p>
                                </div>
                                <div class="flex shrink-0 gap-2">
                                    <button
                                        type="button"
                                        class="inline-flex min-h-11 items-center gap-1.5 rounded-lg bg-primary px-3 text-sm font-semibold text-primary-foreground transition hover:opacity-90 disabled:opacity-50"
                                        :disabled="bezig"
                                        @click="keurGoed(e.id)"
                                    >
                                        <Check class="size-4" />
                                        Goedkeuren
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex min-h-11 items-center rounded-lg border border-border px-3 text-sm font-medium text-muted-foreground transition hover:border-destructive hover:text-destructive disabled:opacity-50"
                                        :disabled="bezig"
                                        @click="afwijzen = afwijzen === e.id ? null : e.id"
                                    >
                                        Afwijzen
                                    </button>
                                </div>
                            </div>
                            <form v-if="afwijzen === e.id" class="mt-3 flex flex-col gap-2 sm:flex-row" @submit.prevent="wijsAf(e.id)">
                                <input
                                    v-model="afwijsBericht"
                                    type="text"
                                    maxlength="300"
                                    placeholder="Bericht aan de ouders, bijvoorbeeld: deze training is voor de selectie"
                                    class="min-h-11 min-w-0 flex-1 rounded-lg border border-input bg-background px-3 text-sm outline-none focus:border-primary"
                                />
                                <button
                                    type="submit"
                                    class="inline-flex min-h-11 items-center justify-center rounded-lg bg-destructive px-3 text-sm font-semibold text-destructive-foreground disabled:opacity-50"
                                    :disabled="bezig"
                                >
                                    Afwijzen en berichten
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>

                <!-- Wachtlijst -->
                <div v-if="wachtlijst.length" class="mt-4">
                    <p class="text-sm font-semibold">Wachtlijst ({{ wachtlijst.length }})</p>
                    <ul class="mt-2 divide-y divide-border">
                        <li v-for="(e, i) in wachtlijst" :key="e.id" class="flex items-center gap-3 py-2">
                            <span class="tabular w-5 shrink-0 text-xs text-muted-foreground">{{ i + 1 }}.</span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium">{{ e.name }}</p>
                                <p class="text-xs text-muted-foreground">
                                    {{ e.position }} · sinds {{ e.since
                                    }}<span v-if="e.invited" class="text-primary"> · heeft bericht dat er plek is</span>
                                </p>
                            </div>
                            <button
                                type="button"
                                class="flex size-11 shrink-0 items-center justify-center rounded-lg text-muted-foreground transition hover:text-destructive disabled:opacity-50"
                                :disabled="bezig"
                                :aria-label="e.name + ' van de wachtlijst halen'"
                                @click="haalVanLijst(e)"
                            >
                                <X class="size-4" />
                            </button>
                        </li>
                    </ul>
                </div>

                <p v-if="!aanvragen.length && !wachtlijst.length" class="mt-3 text-sm text-muted-foreground">
                    Geen aanvragen of wachtlijst. Wie los is ingeschreven staat hieronder bij de aanwezigheid.
                </p>
            </div>

            <!-- Inzetkaart: na de training per kind aanwezig, inzet en houding -->
            <div
                v-if="effortFlow && players.length"
                class="mt-6 flex flex-col gap-3 rounded-xl border border-primary/30 bg-primary/5 p-4 sm:flex-row sm:items-center"
            >
                <p class="min-w-0 flex-1 text-sm">
                    <span class="font-medium">Na de training:</span>
                    <span class="text-muted-foreground"> per kind aanwezig, inzet en houding. Een paar tikken per kind.</span>
                </p>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <Link
                        :href="'/trainings/' + training.id + '/inzet'"
                        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                    >
                        Inzet geven
                    </Link>
                    <Link
                        v-if="course"
                        :href="'/aanbod/' + course.id + '/voortgang'"
                        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-border bg-card px-4 text-sm font-medium transition hover:border-primary"
                    >
                        Niveaus cursus
                    </Link>
                </div>
            </div>

            <!-- Aanwezigheid afvinken (trainer) -->
            <!-- Het anker is er zodat "Aanwezigheid" in het overzicht hier landt
                 en niet bovenaan een pagina waar je nog voor moet scrollen. -->
            <div id="aanwezigheid" class="scroll-mt-4"></div>
            <div v-if="can.record" class="mt-6 rounded-xl border border-border bg-card p-5 shadow-sm" data-tour="attendance">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <p class="font-medium">Aanwezigheid</p>
                    <p class="tabular text-sm text-muted-foreground">
                        {{ aanwezig }} aanwezig &middot; {{ afgevinkt }} van {{ players.length }} afgevinkt
                    </p>
                </div>

                <div v-if="players.length" class="mt-4 space-y-2">
                    <div v-for="speler in players" :key="speler.id" class="flex items-center gap-3 rounded-lg border border-border p-3">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">{{ speler.name }}</p>
                            <p class="truncate text-xs text-muted-foreground">
                                {{ speler.position }}
                                <template v-if="speler.registration">
                                    &middot;
                                    <span :class="speler.registration === 'attending' ? 'text-primary' : ''">
                                        {{ speler.registration === 'attending' ? 'aangemeld' : 'afgemeld' }}
                                    </span>
                                </template>
                            </p>
                            <p v-if="speler.registration_note" class="mt-0.5 break-words text-xs italic text-muted-foreground">
                                {{ speler.registration_note }}
                            </p>
                            <p v-if="speler.loose" class="mt-1 flex flex-wrap items-center gap-2 text-xs">
                                <span class="rounded-full bg-gold/15 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-gold"
                                    >los ingeschreven</span
                                >
                                <span v-if="speler.paid" class="text-primary">{{ speler.amount }} betaald</span>
                                <button
                                    v-else-if="speler.cash_due"
                                    type="button"
                                    class="inline-flex min-h-11 items-center gap-1 rounded-lg border border-warning/50 px-2 font-medium text-warning transition hover:bg-warning/10 disabled:opacity-50"
                                    :disabled="bezig"
                                    @click="contantOntvangen(speler)"
                                >
                                    <Banknote class="size-3.5" />
                                    {{ speler.amount }} contant · ontvangen?
                                </button>
                                <button
                                    v-else
                                    type="button"
                                    class="inline-flex min-h-11 items-center gap-1 rounded-lg border border-border px-2 font-medium text-muted-foreground transition hover:border-primary disabled:opacity-50"
                                    :disabled="bezig"
                                    @click="haalVanLijst({ id: speler.enrollment_id!, name: speler.name } as Aanmelding)"
                                >
                                    Afmelden
                                </button>
                            </p>
                        </div>

                        <div class="flex shrink-0 gap-1">
                            <button
                                type="button"
                                class="flex size-11 items-center justify-center rounded-lg border transition"
                                :class="
                                    speler.status === 'present'
                                        ? 'border-transparent bg-primary text-primary-foreground'
                                        : 'border-border text-muted-foreground hover:border-primary hover:text-primary'
                                "
                                :aria-label="speler.name + ' aanwezig'"
                                :aria-pressed="speler.status === 'present'"
                                @click="vink(speler.id, 'present', speler.status)"
                            >
                                <Check class="size-5" />
                            </button>

                            <button
                                type="button"
                                class="flex size-11 items-center justify-center rounded-lg border transition"
                                :class="
                                    speler.status === 'absent'
                                        ? 'border-transparent bg-destructive text-destructive-foreground'
                                        : 'border-border text-muted-foreground hover:border-destructive hover:text-destructive'
                                "
                                :aria-label="speler.name + ' afwezig'"
                                :aria-pressed="speler.status === 'absent'"
                                @click="vink(speler.id, 'absent', speler.status)"
                            >
                                <X class="size-5" />
                            </button>
                        </div>
                    </div>
                </div>

                <p v-else class="mt-3 text-sm text-muted-foreground">
                    Er staan hier nog geen actieve spelers.
                    <Link
                        v-if="training.group_id"
                        :href="'/groups/' + training.group_id"
                        class="inline-flex min-h-11 items-center font-medium text-primary underline underline-offset-4"
                    >
                        Bekijk de groep
                    </Link>
                </p>
            </div>

            <RegistrationDialog
                v-if="dialoog"
                v-model:open="dialoogOpen"
                :training-id="training.id"
                :training-label="training.group"
                :date="training.date + ' · ' + training.time"
                :child="dialoog.child"
                :mode="dialoog.mode"
            />

            <div v-if="can.delete" class="mt-4 rounded-xl border border-destructive/25 bg-destructive/5 p-5">
                <p class="font-medium text-destructive">Training verwijderen</p>
                <p class="mt-1 text-sm text-muted-foreground">De afgevinkte aanwezigheid van deze training verdwijnt mee.</p>
                <Button variant="destructive" class="mt-4" @click="verwijderen">
                    <Trash2 class="mr-2 size-4" />
                    Training verwijderen
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
