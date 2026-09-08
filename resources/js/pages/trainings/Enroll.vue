<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { Banknote, CalendarDays, Check, Clock, CreditCard, LoaderCircle, MapPin, UserCog, Users } from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * Los inschrijven op één training: voor welk kind, wat kost het, hoe betaal
 * je, en wat er gebeurt als je bevestigt. Wat niet kan staat er niet of is
 * uitgegrijsd met de reden; de echte grens zit op de server.
 */
interface Kind {
    id: number;
    first_name: string;
    status: string | null;
    status_label: string | null;
    eligible: boolean;
    reason: string | null;
    invited: boolean;
}

const props = defineProps<{
    training: {
        id: number;
        label: string;
        date: string;
        time: string;
        location: string | null;
        trainers: string[];
        price: string;
        is_free: boolean;
        capacity: number | null;
        spots_left: number | null;
        is_full: boolean;
        requires_approval: boolean;
        age_label: string;
        audience_label: string;
        open: boolean;
    };
    children: Kind[];
    methods: { key: string; label: string; hint: string }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Trainingen', href: '/trainings' },
    { title: props.training.label, href: '/trainings/' + props.training.id },
    { title: 'Inschrijven', href: '/trainings/' + props.training.id + '/inschrijven' },
];

const kandidaten = computed(() => props.children.filter((k) => k.eligible || k.invited));

const form = useForm({
    player_id: (kandidaten.value.length === 1 ? kandidaten.value[0].id : null) as number | null,
    payment_method: (props.methods.length === 1 ? props.methods[0].key : '') as string,
});

const gekozen = computed(() => props.children.find((k) => k.id === form.player_id) ?? null);

// Wat er straks gebeurt, in één zin. Een bedrag zonder "wanneer" laat een
// ouder gokken of er vanavond iets van zijn rekening gaat.
const watGebeurtEr = computed(() => {
    const naam = gekozen.value?.first_name ?? 'je kind';

    if (props.training.is_full && !gekozen.value?.invited) {
        return `De training is vol. ${naam} komt op de wachtlijst; je betaalt niets en hoort het zodra er plek is.`;
    }

    if (props.training.requires_approval) {
        return `De school bekijkt de aanvraag eerst. ${naam} staat pas ingeschreven na een ja, en je betaalt ook pas dan.`;
    }

    if (props.training.is_free) {
        return `${naam} staat meteen ingeschreven; de training komt bij Komend en in je agenda.`;
    }

    if (form.payment_method === 'cash') {
        return `${naam} staat meteen ingeschreven. Je rekent ${props.training.price} contant af bij de training.`;
    }

    if (form.payment_method === 'online') {
        return `Je rekent nu ${props.training.price} af; daarna staat ${naam} ingeschreven.`;
    }

    return `${naam} staat ingeschreven zodra je hebt gekozen hoe je betaalt.`;
});

const moetBetaalwijzeKiezen = computed(
    () =>
        !props.training.is_free &&
        !props.training.requires_approval &&
        !(props.training.is_full && !gekozen.value?.invited) &&
        props.methods.length > 0,
);

const knopTekst = computed(() => {
    if (props.training.is_full && !gekozen.value?.invited) return 'Op de wachtlijst zetten';
    if (props.training.requires_approval) return 'Aanvraag versturen';
    if (!props.training.is_free && form.payment_method === 'online') return 'Inschrijven en betalen';
    return 'Inschrijven';
});

const bevestig = () => form.post('/trainings/' + props.training.id + '/inschrijven');
</script>

<template>
    <Head :title="'Inschrijven - ' + training.label" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-xl p-4 pb-28">
            <h1 class="text-2xl font-semibold tracking-tight">Inschrijven</h1>
            <p class="mt-1 text-sm text-muted-foreground">{{ training.label }}</p>

            <!-- De training zelf: wanneer, waar, met wie, voor wie. -->
            <div class="mt-5 divide-y divide-border rounded-xl border border-border bg-card shadow-sm">
                <div class="flex items-start gap-3 p-4">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        <CalendarDays class="size-5" />
                    </span>
                    <div class="min-w-0">
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
                    <p class="min-w-0 break-words font-medium">{{ training.location }}</p>
                </div>
                <div v-if="training.trainers.length" class="flex items-start gap-3 p-4">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        <UserCog class="size-5" />
                    </span>
                    <p class="min-w-0 break-words font-medium">{{ training.trainers.join(', ') }}</p>
                </div>
                <div class="flex items-start gap-3 p-4">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        <Users class="size-5" />
                    </span>
                    <div class="min-w-0">
                        <p class="font-medium">{{ training.audience_label }} · {{ training.age_label }}</p>
                        <p class="tabular text-sm text-muted-foreground">
                            <template v-if="training.spots_left !== null">
                                {{ training.is_full ? 'Vol' : training.spots_left + (training.spots_left === 1 ? ' plek vrij' : ' plekken vrij') }}
                                van {{ training.capacity }}
                            </template>
                            <template v-else>Onbeperkt aantal plekken</template>
                        </p>
                    </div>
                </div>
            </div>

            <form class="mt-5 space-y-4" @submit.prevent="bevestig">
                <!-- 1. Voor wie -->
                <section class="rounded-xl border border-border bg-card p-5 shadow-sm">
                    <p class="font-medium">Voor wie?</p>
                    <div class="mt-3 grid gap-2">
                        <button
                            v-for="kind in children"
                            :key="kind.id"
                            type="button"
                            class="flex min-h-11 items-center gap-3 rounded-xl border p-3 text-left transition"
                            :class="[
                                form.player_id === kind.id ? 'border-primary bg-primary/5' : 'border-border',
                                kind.eligible || kind.invited ? 'hover:border-primary' : 'cursor-not-allowed opacity-60',
                            ]"
                            :disabled="!(kind.eligible || kind.invited)"
                            :aria-pressed="form.player_id === kind.id"
                            @click="form.player_id = kind.id"
                        >
                            <span
                                class="flex size-5 shrink-0 items-center justify-center rounded-full border"
                                :class="form.player_id === kind.id ? 'border-primary bg-primary text-primary-foreground' : 'border-border'"
                            >
                                <Check v-if="form.player_id === kind.id" class="size-3" />
                            </span>
                            <span class="min-w-0">
                                <span class="block text-sm font-medium">{{ kind.first_name }}</span>
                                <span v-if="kind.status" class="block text-xs text-muted-foreground">{{ kind.status_label }}</span>
                                <span v-else-if="kind.reason" class="block text-xs text-muted-foreground">Kan niet: {{ kind.reason }}</span>
                                <span v-else-if="kind.invited" class="block text-xs text-primary">Er is plek vrijgekomen</span>
                            </span>
                        </button>
                    </div>
                    <InputError class="mt-2" :message="form.errors.player_id" />
                </section>

                <!-- 2. Wat kost het en hoe betaal je -->
                <section class="rounded-xl border border-border bg-card p-5 shadow-sm">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <p class="font-medium">Kosten</p>
                        <p class="tabular text-lg font-bold">{{ training.is_free ? 'Gratis' : training.price }}</p>
                    </div>

                    <div v-if="moetBetaalwijzeKiezen" class="mt-3 grid gap-2">
                        <button
                            v-for="wijze in methods"
                            :key="wijze.key"
                            type="button"
                            class="flex min-h-11 items-center gap-3 rounded-xl border p-3 text-left transition hover:border-primary"
                            :class="form.payment_method === wijze.key ? 'border-primary bg-primary/5' : 'border-border'"
                            :aria-pressed="form.payment_method === wijze.key"
                            @click="form.payment_method = wijze.key"
                        >
                            <component :is="wijze.key === 'cash' ? Banknote : CreditCard" class="size-5 shrink-0 text-primary" />
                            <span class="min-w-0">
                                <span class="block text-sm font-medium">{{ wijze.label }}</span>
                                <span class="block text-xs text-muted-foreground">{{ wijze.hint }}</span>
                            </span>
                        </button>
                        <InputError :message="form.errors.payment_method" />
                    </div>

                    <p v-else-if="!training.is_free && !methods.length && !training.requires_approval" class="mt-2 text-sm text-warning">
                        Deze training heeft nog geen betaalwijze die nu kan. Neem contact op met de school.
                    </p>
                </section>

                <!-- 3. Wat er gebeurt -->
                <p class="rounded-xl border border-primary/30 bg-primary/5 p-4 text-sm">{{ watGebeurtEr }}</p>
            </form>

            <div class="fixed inset-x-0 bottom-[var(--pp-tabbar)] z-30 border-t border-border bg-card/95 backdrop-blur">
                <div class="mx-auto flex w-full max-w-xl items-center justify-end gap-3 p-4">
                    <button
                        type="button"
                        class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-primary px-5 text-sm font-semibold text-primary-foreground transition hover:opacity-90 disabled:opacity-60 sm:w-auto"
                        :disabled="form.processing || !form.player_id || (moetBetaalwijzeKiezen && !form.payment_method) || !training.open"
                        @click="bevestig"
                    >
                        <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                        <Check v-else class="size-4" />
                        {{ knopTekst }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
