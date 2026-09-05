<script setup lang="ts">
import FlashMessage from '@/components/FlashMessage.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { CalendarX2, Check, MapPin, Pencil, RotateCcw, Trash2, UserCog, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface SpelerRij {
    id: number;
    name: string;
    position: string;
    registration: string | null;
    status: string | null;
}

const props = defineProps<{
    training: {
        id: number;
        group: string;
        group_id: number;
        date: string;
        time: string;
        location: string | null;
        note: string | null;
        trainers: { id: number; name: string }[];
        has_passed: boolean;
        cancelled_at: string | null;
        cancellation_reason: string | null;
    };
    players: SpelerRij[];
    can: { record: boolean; manage: boolean; delete: boolean };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Trainingen', href: '/trainings' },
    { title: props.training.group, href: '/trainings/' + props.training.id },
];

// Afzeggen stuurt meteen bericht aan de groep, dus vragen we om een reden:
// "gaat niet door" zonder waarom levert alleen maar telefoontjes op.
const toonAfzeggen = ref(false);
const reden = ref('');

const afzeggen = () => {
    router.post('/trainings/' + props.training.id + '/afzeggen', { reason: reden.value }, {
        preserveScroll: true,
        onSuccess: () => {
            toonAfzeggen.value = false;
            reden.value = '';
        },
    });
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

const meld = (spelerId: number, registration: string) => {
    router.post(
        '/trainings/' + props.training.id + '/registration/' + spelerId,
        { registration },
        { preserveScroll: true },
    );
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
            <FlashMessage />

            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <h1 class="text-2xl font-semibold tracking-tight">{{ training.group }}</h1>
                    <p class="mt-1 text-sm text-muted-foreground first-letter:uppercase">{{ training.date }} &middot; {{ training.time }}</p>
                    <p v-if="training.location" class="mt-1 flex items-center gap-1.5 text-sm text-muted-foreground">
                        <MapPin class="size-4" />
                        {{ training.location }}
                    </p>
                    <p v-if="training.trainers.length" class="mt-1 flex items-center gap-1.5 text-sm text-muted-foreground">
                        <UserCog class="size-4" />
                        {{ training.trainers.map((t) => t.name).join(', ') }}
                    </p>
                </div>

                <Link
                    v-if="can.manage"
                    :href="'/trainings/' + training.id + '/edit'"
                    class="inline-flex items-center gap-2 rounded-xl border border-border bg-card px-4 py-2 text-sm font-medium shadow-sm transition hover:border-primary"
                >
                    <Pencil class="size-4" />
                    Bewerken
                </Link>
            </div>

            <p v-if="training.note" class="mt-4 rounded-lg bg-secondary px-3 py-2 text-sm">{{ training.note }}</p>

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
                    class="mt-3 inline-flex items-center gap-2 text-xs text-muted-foreground underline underline-offset-4 hover:text-foreground"
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
                        <button type="button" class="text-sm text-muted-foreground underline underline-offset-4" @click="toonAfzeggen = false">
                            Annuleren
                        </button>
                    </div>
                </form>
            </div>

            <!-- Aanwezigheid afvinken (trainer) -->
            <div v-if="can.record" class="mt-6 rounded-xl border border-border bg-card p-5 shadow-sm">
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
                        </div>

                        <div class="flex shrink-0 gap-1">
                            <button
                                type="button"
                                class="flex h-10 w-10 items-center justify-center rounded-lg border transition"
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
                                class="flex h-10 w-10 items-center justify-center rounded-lg border transition"
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
                    Deze groep heeft nog geen actieve spelers.
                    <Link :href="'/groups/' + training.group_id + '/edit'" class="font-medium text-primary underline underline-offset-4">
                        Bekijk de groep
                    </Link>
                </p>
            </div>

            <!-- Aan- en afmelden (speler of ouder) -->
            <div v-else class="mt-6 rounded-xl border border-border bg-card p-5 shadow-sm">
                <p class="font-medium">Kun je erbij zijn?</p>
                <p class="mt-1 text-xs text-muted-foreground">Je trainer ziet dit, zodat hij weet op wie hij kan rekenen.</p>

                <div v-if="players.length" class="mt-4 space-y-3">
                    <div v-for="speler in players" :key="speler.id" class="rounded-lg border border-border p-3">
                        <p class="text-sm font-medium">{{ speler.name }}</p>

                        <div v-if="!training.has_passed" class="mt-3 flex gap-2">
                            <button
                                type="button"
                                class="flex flex-1 items-center justify-center gap-2 rounded-lg border px-3 py-2.5 text-sm font-medium transition"
                                :class="
                                    speler.registration === 'attending'
                                        ? 'border-transparent bg-primary text-primary-foreground'
                                        : 'border-border text-muted-foreground hover:border-primary hover:text-primary'
                                "
                                @click="meld(speler.id, 'attending')"
                            >
                                <Check class="size-4" />
                                Ik kom
                            </button>

                            <button
                                type="button"
                                class="flex flex-1 items-center justify-center gap-2 rounded-lg border px-3 py-2.5 text-sm font-medium transition"
                                :class="
                                    speler.registration === 'declined'
                                        ? 'border-transparent bg-secondary text-secondary-foreground'
                                        : 'border-border text-muted-foreground hover:border-foreground hover:text-foreground'
                                "
                                @click="meld(speler.id, 'declined')"
                            >
                                <X class="size-4" />
                                Ik kom niet
                            </button>
                        </div>

                        <p v-else class="mt-2 text-sm text-muted-foreground">
                            <template v-if="speler.status">
                                Je was {{ speler.status === 'present' ? 'aanwezig' : 'afwezig' }}.
                            </template>
                            <template v-else>Deze training is geweest.</template>
                        </p>
                    </div>
                </div>

                <p v-else class="mt-3 text-sm text-muted-foreground">Je hebt geen spelers in deze groep.</p>
            </div>

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
