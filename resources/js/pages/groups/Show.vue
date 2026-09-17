<script setup lang="ts">
import Avatar from '@/components/Avatar.vue';
import DemoBadge from '@/components/onboarding/DemoBadge.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { CalendarDays, Check, Pencil, Plus, Search, UserMinus, Users } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

/**
 * Eén groep: wie erin zit, wat er gepland staat, en spelers erin of eruit.
 *
 * Dit is de plek waar je indeelt. Zoeken en meerdere tegelijk aanvinken,
 * want een school die overstapt zet er twintig in één keer in; één voor één
 * via het spelersformulier is dan een middag werk. Vanaf de speler kan het
 * ook (zijn bewerkscherm) - beide kanten schrijven dezelfde koppeling.
 */
interface Speler {
    id: number;
    name: string;
    position: string;
    age: number | null;
    photo: string | null;
    is_active: boolean;
}

interface Kandidaat {
    id: number;
    name: string;
    position: string;
    age: number | null;
    age_category: string | null;
}

const props = defineProps<{
    group: {
        id: number;
        name: string;
        age_category: string | null;
        is_active: boolean;
        is_demo: boolean;
        product: { id: number; name: string } | null;
        trainings_total: number;
    };
    players: Speler[];
    upcoming: { id: number; date: string; time: string; location: string | null }[];
    available: Kandidaat[];
    can: { manage: boolean; editProduct: boolean };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Groepen', href: '/groups' },
    { title: props.group.name, href: '/groups/' + props.group.id },
];

// --- Spelers toevoegen: zoeken en aanvinken ---
const open = ref(false);
const zoek = ref('');
const gekozen = ref<number[]>([]);
const bezig = ref(false);

const gevonden = computed(() => {
    const term = zoek.value.trim().toLowerCase();

    return term === '' ? props.available : props.available.filter((k) => k.name.toLowerCase().includes(term));
});

// Kandidaten uit dezelfde leeftijdscategorie als de groep staan bovenaan:
// dat zijn negen van de tien keer de spelers die je zoekt.
const gesorteerd = computed(() => {
    const cat = props.group.age_category;

    return [...gevonden.value].sort((a, b) => Number(b.age_category === cat) - Number(a.age_category === cat));
});

const wissel = (id: number) => {
    const i = gekozen.value.indexOf(id);
    if (i === -1) gekozen.value.push(id);
    else gekozen.value.splice(i, 1);
};

const toevoegen = () => {
    if (!gekozen.value.length) return;
    bezig.value = true;
    router.post(
        '/groups/' + props.group.id + '/spelers',
        { players: gekozen.value },
        {
            preserveScroll: true,
            onFinish: () => (bezig.value = false),
            onSuccess: () => {
                open.value = false;
                gekozen.value = [];
                zoek.value = '';
            },
        },
    );
};

const eruit = (speler: Speler) => {
    if (confirm(speler.name.split(' ')[0] + ' uit de groep halen? Rapporten en aanwezigheid blijven bewaard.')) {
        router.delete('/groups/' + props.group.id + '/spelers/' + speler.id, { preserveScroll: true });
    }
};

watch(open, (nu) => {
    if (!nu) {
        gekozen.value = [];
        zoek.value = '';
    }
});
</script>

<template>
    <Head :title="group.name + ' - Groepen'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-3xl p-4">

            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <h1 class="flex flex-wrap items-center gap-2 text-2xl font-semibold tracking-tight">
                        <DemoBadge v-if="group.is_demo" />
                        {{ group.name }}
                        <span v-if="!group.is_active" class="rounded bg-secondary px-1.5 py-0.5 text-[10px] text-muted-foreground">niet actief</span>
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        <span v-if="group.age_category">{{ group.age_category }} &middot; </span>
                        <span class="tabular">{{ players.length }}</span> {{ players.length === 1 ? 'speler' : 'spelers' }}
                        &middot; <span class="tabular">{{ group.trainings_total }}</span> {{ group.trainings_total === 1 ? 'training' : 'trainingen' }}
                        <template v-if="group.product">
                            &middot; hoort bij
                            <Link
                                v-if="can.editProduct"
                                :href="'/aanbod/' + group.product.id + '/edit'"
                                class="text-primary underline underline-offset-4"
                                >{{ group.product.name }}</Link
                            >
                            <span v-else>{{ group.product.name }}</span>
                        </template>
                    </p>
                </div>

                <Link
                    v-if="can.manage"
                    :href="'/groups/' + group.id + '/edit'"
                    class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-border bg-card px-3 text-sm font-medium transition hover:border-primary"
                >
                    <Pencil class="size-4" />
                    Naam of leeftijd
                </Link>
            </div>

            <!-- Spelers -->
            <section class="mt-6 rounded-xl border border-border bg-card p-4 shadow-sm sm:p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="font-medium">Spelers in deze groep</p>
                    <Button v-if="can.manage" class="h-11" @click="open = true">
                        <Plus class="mr-1.5 size-4" />
                        Spelers toevoegen
                    </Button>
                </div>

                <ul v-if="players.length" class="mt-4 divide-y divide-border">
                    <li v-for="speler in players" :key="speler.id" class="flex items-center gap-3 py-2.5">
                        <Avatar :name="speler.name" :photo="speler.photo" size="size-10" />
                        <Link :href="'/players/' + speler.id" class="min-w-0 flex-1">
                            <span class="block truncate font-medium">{{ speler.name }}</span>
                            <span class="block text-xs text-muted-foreground">
                                {{ speler.position }}<template v-if="speler.age !== null"> &middot; {{ speler.age }} jaar</template>
                                <span v-if="!speler.is_active"> &middot; niet actief</span>
                            </span>
                        </Link>
                        <button
                            v-if="can.manage"
                            type="button"
                            class="flex size-11 shrink-0 items-center justify-center rounded-lg text-muted-foreground transition hover:bg-secondary hover:text-warning"
                            :aria-label="speler.name + ' uit de groep halen'"
                            :title="'Uit de groep halen'"
                            @click="eruit(speler)"
                        >
                            <UserMinus class="size-4" />
                        </button>
                    </li>
                </ul>

                <div v-else class="mt-4 rounded-xl border border-dashed border-border p-6 text-center">
                    <Users class="mx-auto size-6 text-muted-foreground" />
                    <p class="mt-2 text-sm font-medium">Nog niemand in deze groep</p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Zet er spelers in; ze staan dan op de aanwezigheidslijst van elke training van deze groep.
                    </p>
                </div>
            </section>

            <!-- Trainingen -->
            <section class="mt-4 rounded-xl border border-border bg-card p-4 shadow-sm sm:p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="font-medium">Komende trainingen</p>
                    <Link
                        v-if="can.manage"
                        href="/trainings/create"
                        class="inline-flex min-h-11 items-center gap-1.5 text-sm font-medium text-primary"
                    >
                        <CalendarDays class="size-4" />
                        Training inplannen
                    </Link>
                </div>

                <ul v-if="upcoming.length" class="mt-3 divide-y divide-border">
                    <li v-for="t in upcoming" :key="t.id">
                        <Link :href="'/trainings/' + t.id" class="flex min-h-11 items-center gap-3 py-2">
                            <span class="tabular w-24 shrink-0 text-sm text-muted-foreground">{{ t.date }}</span>
                            <span class="tabular shrink-0 text-sm font-medium">{{ t.time }}</span>
                            <span class="min-w-0 flex-1 truncate text-sm text-muted-foreground">{{ t.location }}</span>
                        </Link>
                    </li>
                </ul>
                <p v-else class="mt-3 text-sm text-muted-foreground">
                    Nog geen trainingen gepland voor deze groep. Plan je er een, dan staan deze spelers erop.
                </p>
            </section>

            <!-- Wat een groep is, voor wie het nog niet weet -->
            <p class="mt-4 text-xs leading-relaxed text-muted-foreground">
                Een groep is waar je op plant en afvinkt: elke training hoort bij één groep, en wie in de groep zit staat vanzelf op de
                aanwezigheidslijst. Een speler mag in meerdere groepen zitten, bijvoorbeeld keeperstraining én veldtraining.
            </p>
        </div>

        <!-- Spelers kiezen -->
        <Dialog v-model:open="open">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Spelers toevoegen aan {{ group.name }}</DialogTitle>
                    <DialogDescription>Zoek op naam en vink aan. Je kunt er meerdere tegelijk kiezen.</DialogDescription>
                </DialogHeader>

                <div class="relative">
                    <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <input
                        v-model="zoek"
                        type="search"
                        placeholder="Zoek een speler"
                        class="h-11 w-full rounded-lg border border-input bg-background pl-9 pr-3 text-base outline-none focus:border-primary"
                        aria-label="Zoek een speler"
                    />
                </div>

                <div v-if="available.length === 0" class="rounded-lg border border-dashed border-border p-4 text-center text-sm text-muted-foreground">
                    Alle actieve spelers zitten al in deze groep.
                    <Link href="/players/create" class="mt-1 flex min-h-11 items-center justify-center text-primary underline underline-offset-4">Nieuwe speler toevoegen</Link>
                </div>

                <ul v-else class="max-h-72 space-y-1 overflow-y-auto">
                    <li v-for="k in gesorteerd" :key="k.id">
                        <button
                            type="button"
                            class="flex min-h-11 w-full items-center gap-3 rounded-lg px-2 text-left transition hover:bg-secondary"
                            :aria-pressed="gekozen.includes(k.id)"
                            @click="wissel(k.id)"
                        >
                            <span
                                class="flex size-5 shrink-0 items-center justify-center rounded-md border"
                                :class="gekozen.includes(k.id) ? 'border-primary bg-primary text-primary-foreground' : 'border-border'"
                            >
                                <Check v-if="gekozen.includes(k.id)" class="size-3.5" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium">{{ k.name }}</span>
                                <span class="block text-xs text-muted-foreground">
                                    {{ k.position }}<template v-if="k.age !== null"> &middot; {{ k.age }} jaar</template>
                                    <template v-if="k.age_category"> &middot; {{ k.age_category }}</template>
                                </span>
                            </span>
                        </button>
                    </li>
                    <li v-if="gesorteerd.length === 0" class="p-3 text-center text-sm text-muted-foreground">Niemand gevonden met die naam.</li>
                </ul>

                <DialogFooter class="gap-2">
                    <Button variant="secondary" class="h-11" @click="open = false">Annuleren</Button>
                    <Button class="h-11" :disabled="bezig || gekozen.length === 0" @click="toevoegen">
                        {{ gekozen.length === 0 ? 'Toevoegen' : gekozen.length === 1 ? '1 speler toevoegen' : gekozen.length + ' spelers toevoegen' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
