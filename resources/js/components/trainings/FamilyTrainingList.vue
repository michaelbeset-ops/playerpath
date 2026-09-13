<script setup lang="ts">
import RegistrationDialog from '@/components/RegistrationDialog.vue';
import DemoBadge from '@/components/onboarding/DemoBadge.vue';
import { Link, router } from '@inertiajs/vue3';
import { Banknote, CalendarPlus, ChevronRight, MapPin, UserCog, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';

/**
 * De trainingen van een gezin: komend, inschrijven, geweest - per kind.
 *
 * Bij meer dan één kind staat er een wisselaar bovenaan; elke stapel is dan
 * de stapel van dát kind. Zo hoeft een ouder met twee kinderen op twee
 * verschillende dagen niet zelf uit te zoeken welke regel bij wie hoort.
 *
 * Afmelden gaat op twee manieren, en dat is geen slordigheid: wie in de groep
 * zit (abonnement) meldt zich af voor die ene keer en blijft in de groep; wie
 * los was ingeschreven wordt uitgeschreven en de plek gaat naar de wachtlijst.
 */
interface KindStatus {
    id: number;
    first_name: string;
    status: string | null;
    status_label: string | null;
    enrollable: boolean;
    registration: string | null;
    attendance: string | null;
    attendance_label: string;
    cash_due: boolean;
    payment_open: boolean;
}

export interface GezinsTraining {
    id: number;
    group: string;
    day: string;
    day_label: string;
    is_today: boolean;
    date: string;
    starts_at: string;
    ends_at: string;
    time: string;
    location: string | null;
    trainers: string[];
    has_passed: boolean;
    cancelled: boolean;
    is_demo?: boolean;
    open: boolean;
    price: string;
    is_free: boolean;
    spots_left: number | null;
    is_full: boolean;
    requires_approval: boolean;
    children: KindStatus[];
}

const props = defineProps<{
    children: { id: number; first_name: string }[];
    upcoming: GezinsTraining[];
    enrollable: GezinsTraining[];
    past: GezinsTraining[];
    canEnroll: boolean;
    /** Welke stapel open staat bij het laden; de rondleiding opent "Inschrijven". */
    startTab?: 'upcoming' | 'enrollable' | 'past';
}>();

const kind = ref<number>(props.children[0]?.id ?? 0);
const tab = ref<'upcoming' | 'enrollable' | 'past'>(props.startTab ?? 'upcoming');

const voor = (t: GezinsTraining) => t.children.find((k) => k.id === kind.value);

const komend = computed(() => props.upcoming.filter((t) => voor(t)?.status));
const inschrijven = computed(() => props.enrollable.filter((t) => voor(t)?.enrollable));
const geweest = computed(() => props.past.filter((t) => voor(t)?.status));

const lijst = computed(() => (tab.value === 'upcoming' ? komend.value : tab.value === 'enrollable' ? inschrijven.value : geweest.value));

// Per dag gegroepeerd: een rooster lees je per dag.
const dagen = computed(() => {
    const uit: { day: string; label: string; isToday: boolean; items: GezinsTraining[] }[] = [];

    for (const t of lijst.value) {
        const laatste = uit[uit.length - 1];
        if (laatste?.day === t.day) laatste.items.push(t);
        else uit.push({ day: t.day, label: t.day_label, isToday: t.is_today, items: [t] });
    }

    return uit;
});

// Afmelden of weer aanmelden voor wie in de groep zit.
const dialoog = ref<{ training: GezinsTraining; mode: 'declined' | 'attending' } | null>(null);
const dialoogOpen = ref(false);

const openDialoog = (t: GezinsTraining, mode: 'declined' | 'attending') => {
    dialoog.value = { training: t, mode };
    dialoogOpen.value = true;
};

const gekozenKind = computed(() => props.children.find((k) => k.id === kind.value) ?? { id: 0, first_name: '' });

// Uitschrijven voor wie los was ingeschreven.
const schrijfUit = (t: GezinsTraining) => {
    if (confirm(`${gekozenKind.value.first_name} afmelden voor ${t.group} op ${t.day_label}? De plek gaat naar de wachtlijst.`)) {
        router.delete(`/trainings/${t.id}/inschrijven/${kind.value}`, { preserveScroll: true });
    }
};

const statusKlasse = (status: string | null) =>
    status === 'requested'
        ? 'bg-warning/10 text-warning'
        : status === 'waitlisted'
          ? 'bg-secondary text-muted-foreground'
          : 'bg-primary/10 text-primary';
</script>

<template>
    <div>
        <!-- De wisselaar: alleen bij meer dan één kind. -->
        <div v-if="children.length > 1" class="mt-5 flex flex-wrap gap-2" role="tablist" aria-label="Kind">
            <button
                v-for="k in children"
                :key="k.id"
                type="button"
                role="tab"
                class="inline-flex min-h-11 items-center rounded-full border px-4 text-sm font-medium transition"
                :class="
                    kind === k.id
                        ? 'border-primary bg-primary text-primary-foreground'
                        : 'border-border bg-card text-muted-foreground hover:border-primary'
                "
                :aria-selected="kind === k.id"
                @click="kind = k.id"
            >
                {{ k.first_name }}
            </button>
        </div>

        <!-- Drie stapels -->
        <div class="mt-5 grid grid-cols-3 rounded-lg border border-border bg-card p-1 shadow-sm">
            <button
                v-for="t in [
                    { key: 'upcoming', label: 'Komend', n: komend.length },
                    { key: 'enrollable', label: 'Inschrijven', n: inschrijven.length },
                    { key: 'past', label: 'Geweest', n: geweest.length },
                ]"
                :key="t.key"
                type="button"
                class="min-h-11 rounded-md px-2 text-sm font-medium transition"
                :class="tab === t.key ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'"
                @click="tab = t.key as 'upcoming' | 'enrollable' | 'past'"
            >
                {{ t.label }} <span class="tabular">({{ t.n }})</span>
            </button>
        </div>

        <div v-if="dagen.length" class="mt-5 space-y-5">
            <section v-for="dag in dagen" :key="dag.day">
                <div class="flex items-center gap-2">
                    <h2 class="text-sm font-semibold first-letter:uppercase" :class="dag.isToday ? 'text-primary' : ''">{{ dag.label }}</h2>
                    <span v-if="dag.isToday" class="rounded-full bg-primary px-2 py-0.5 text-[10px] font-semibold text-primary-foreground"
                        >vandaag</span
                    >
                </div>

                <div class="mt-2 space-y-2">
                    <article
                        v-for="t in dag.items"
                        :key="t.id"
                        class="rounded-xl border bg-card p-3 shadow-sm"
                        :class="tab === 'enrollable' ? 'border-dashed border-primary/50' : 'border-border'"
                    >
                        <div class="flex min-w-0 gap-3">
                            <span class="tabular w-12 shrink-0 text-sm font-semibold" :class="t.cancelled ? 'text-muted-foreground' : 'text-primary'">
                                {{ t.starts_at }}
                                <span class="block text-xs font-normal text-muted-foreground">{{ t.ends_at }}</span>
                            </span>

                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <Link
                                        :href="'/trainings/' + t.id"
                                        class="inline-flex min-h-11 items-center font-medium hover:text-primary"
                                        :class="t.cancelled ? 'line-through' : ''"
                                    >
                                        {{ t.group }}
                                    </Link>
                                    <DemoBadge v-if="t.is_demo" />
                                    <span
                                        v-if="t.cancelled"
                                        class="rounded-full bg-secondary px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground"
                                    >
                                        afgezegd
                                    </span>
                                    <template v-if="voor(t)?.status && voor(t)?.status !== 'group'">
                                        <span
                                            class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                                            :class="statusKlasse(voor(t)!.status)"
                                        >
                                            {{ voor(t)!.status_label }}
                                        </span>
                                    </template>
                                    <span
                                        v-if="voor(t)?.registration === 'declined'"
                                        class="rounded-full bg-warning/15 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-warning"
                                    >
                                        afgemeld
                                    </span>
                                </div>

                                <p class="mt-1 flex items-start gap-1.5 text-xs text-muted-foreground">
                                    <MapPin class="mt-0.5 size-3.5 shrink-0" />
                                    <span>{{ t.location || 'Geen locatie ingevuld' }}</span>
                                </p>
                                <p class="mt-0.5 flex items-start gap-1.5 text-xs text-muted-foreground">
                                    <UserCog class="mt-0.5 size-3.5 shrink-0" />
                                    <span>{{ t.trainers.join(', ') || 'Geen trainer gekoppeld' }}</span>
                                </p>

                                <!-- Inschrijven: prijs en plekken, met de knop. -->
                                <div v-if="tab === 'enrollable'" class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1">
                                    <span class="tabular text-sm font-semibold">{{ t.is_free ? 'Gratis' : t.price }}</span>
                                    <span class="tabular flex items-center gap-1 text-xs text-muted-foreground">
                                        <Users class="size-3.5" />
                                        <template v-if="t.spots_left !== null">
                                            {{
                                                t.is_full ? 'vol · wachtlijst' : t.spots_left + (t.spots_left === 1 ? ' plek vrij' : ' plekken vrij')
                                            }}
                                        </template>
                                        <template v-else>onbeperkt</template>
                                    </span>
                                    <span v-if="t.requires_approval" class="text-xs text-muted-foreground">· na goedkeuring</span>
                                    <Link
                                        v-if="canEnroll"
                                        :href="'/trainings/' + t.id + '/inschrijven'"
                                        class="ml-auto inline-flex min-h-11 items-center gap-1.5 rounded-xl bg-primary px-3 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                                    >
                                        <CalendarPlus class="size-4" />
                                        {{ t.is_full ? 'Wachtlijst' : 'Inschrijven' }}
                                    </Link>
                                </div>

                                <!-- Komend: afmelden, en wat er nog te betalen is. -->
                                <div
                                    v-else-if="tab === 'upcoming' && !t.has_passed && !t.cancelled"
                                    class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1"
                                >
                                    <span v-if="voor(t)?.cash_due" class="inline-flex items-center gap-1 text-xs text-muted-foreground">
                                        <Banknote class="size-3.5" />
                                        {{ t.price }} contant bij de training
                                    </span>
                                    <span v-else-if="voor(t)?.payment_open" class="text-xs text-warning">Betaling staat nog open</span>

                                    <template v-if="voor(t)?.status === 'group'">
                                        <button
                                            v-if="voor(t)?.registration === 'declined'"
                                            type="button"
                                            class="ml-auto inline-flex min-h-11 items-center rounded-lg border border-border px-3 text-sm font-medium transition hover:border-primary"
                                            @click="openDialoog(t, 'attending')"
                                        >
                                            Weer aanmelden
                                        </button>
                                        <button
                                            v-else
                                            type="button"
                                            class="ml-auto inline-flex min-h-11 items-center rounded-lg border border-border px-3 text-sm font-medium text-muted-foreground transition hover:border-warning hover:text-warning"
                                            @click="openDialoog(t, 'declined')"
                                        >
                                            Afmelden
                                        </button>
                                    </template>
                                    <button
                                        v-else-if="canEnroll"
                                        type="button"
                                        class="ml-auto inline-flex min-h-11 items-center rounded-lg border border-border px-3 text-sm font-medium text-muted-foreground transition hover:border-warning hover:text-warning"
                                        @click="schrijfUit(t)"
                                    >
                                        Afmelden
                                    </button>
                                </div>

                                <!-- Geweest: wat de trainer afvinkte. -->
                                <p
                                    v-else-if="tab === 'past'"
                                    class="mt-2 text-xs font-medium"
                                    :class="
                                        voor(t)?.attendance === 'present'
                                            ? 'text-success'
                                            : voor(t)?.attendance === 'absent'
                                              ? 'text-warning'
                                              : 'text-muted-foreground'
                                    "
                                >
                                    {{ gekozenKind.first_name }} {{ voor(t)?.attendance_label }}
                                </p>
                            </div>

                            <Link
                                :href="'/trainings/' + t.id"
                                class="flex min-h-11 shrink-0 items-center text-muted-foreground"
                                :aria-label="'Details van ' + t.group"
                            >
                                <ChevronRight class="size-4" />
                            </Link>
                        </div>
                    </article>
                </div>
            </section>
        </div>

        <div v-else class="mt-5 rounded-2xl border border-dashed border-border bg-card/50 p-8 text-center">
            <p class="font-medium">
                <template v-if="tab === 'upcoming'">Geen komende trainingen voor {{ gekozenKind.first_name }}</template>
                <template v-else-if="tab === 'enrollable'">Niets om op in te schrijven</template>
                <template v-else>Nog geen trainingen geweest</template>
            </p>
            <p class="mt-1 text-sm text-muted-foreground">
                <template v-if="tab === 'upcoming'">Zodra de school er een inplant of je ergens op inschrijft, staat hij hier.</template>
                <template v-else-if="tab === 'enrollable'"
                    >Zet de school een training open voor de leeftijd en positie van {{ gekozenKind.first_name }}, dan zie je hem hier.</template
                >
                <template v-else>Na de eerste training zie je hier of {{ gekozenKind.first_name }} erbij was.</template>
            </p>
        </div>

        <RegistrationDialog
            v-if="dialoog"
            v-model:open="dialoogOpen"
            :training-id="dialoog.training.id"
            :training-label="dialoog.training.group"
            :date="dialoog.training.date + ' · ' + dialoog.training.time"
            :child="gekozenKind"
            :mode="dialoog.mode"
        />
    </div>
</template>
