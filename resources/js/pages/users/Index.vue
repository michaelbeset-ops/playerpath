<script setup lang="ts">
import FlashMessage from '@/components/FlashMessage.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ClipboardList, Contact, KeyRound, Plus, Search, Trash2, UserCog, Users } from 'lucide-vue-next';
import { reactive, ref, watch } from 'vue';

interface SpelerRij {
    id: number;
    name: string;
    position: string;
    age: number | null;
    is_active: boolean;
    overall_rating: number | null;
    groups: string[];
    has_login: boolean;
    email: string | null;
}

const props = defineProps<{
    type: 'players' | 'trainers' | 'guardians';
    counts: { players: number; trainers: number; guardians: number };
    can: { managePlayers: boolean; manageAccounts: boolean };
    players?: SpelerRij[];
    filters?: { search: string; position: string; group: number | null; status: string };
    positions?: Record<string, string>;
    groups?: { id: number; name: string }[];
    trainers?: { id: number; name: string; email: string; is_owner: boolean; trainings_count: number; reports_count: number }[];
    guardians?: { id: number; name: string; email: string; children: { id: number; name: string; relationship: string | null }[] }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Gebruikers', href: '/users' }];

const tabbladen = [
    { key: 'players', label: 'Spelers', icon: Users },
    { key: 'trainers', label: 'Trainers', icon: UserCog },
    { key: 'guardians', label: 'Ouders', icon: Contact },
] as const;

const naarTab = (key: string) => router.get('/users', { type: key }, { preserveState: false });

// --- Spelers ---
const filters = reactive({ ...(props.filters ?? { search: '', position: '', group: null, status: 'active' }) });

let wachten: ReturnType<typeof setTimeout> | undefined;

watch(
    filters,
    () => {
        clearTimeout(wachten);
        wachten = setTimeout(() => {
            router.get('/users', { type: 'players', ...filters, group: filters.group ?? '' }, { preserveState: true, replace: true });
        }, 300);
    },
    { deep: true },
);

// --- Trainer uitnodigen ---
const toonTrainerFormulier = ref(false);

const trainerForm = useForm({ name: '', email: '' });

const nodigTrainerUit = () =>
    trainerForm.post('/users/trainers', {
        preserveScroll: true,
        onSuccess: () => {
            trainerForm.reset();
            toonTrainerFormulier.value = false;
        },
    });

const verwijderTrainer = (id: number, naam: string) => {
    if (confirm(`Het account van ${naam} verwijderen? Zijn rapporten blijven bewaard.`)) {
        router.delete('/users/trainers/' + id, { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Gebruikers" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-4xl p-4">
            <FlashMessage />

            <h1 class="text-2xl font-semibold tracking-tight">Gebruikers</h1>
            <p class="mt-1 text-sm text-muted-foreground">Iedereen die bij je school hoort.</p>

            <!-- Tabbladen: op mobiel volle breedte, geen dropdown -->
            <div class="mt-5 grid grid-cols-3 gap-1 rounded-xl border border-border bg-card p-1 shadow-sm">
                <button
                    v-for="tab in tabbladen"
                    :key="tab.key"
                    type="button"
                    class="flex items-center justify-center gap-1.5 rounded-lg px-2 py-2.5 text-sm font-medium transition sm:gap-2"
                    :class="type === tab.key ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'"
                    @click="naarTab(tab.key)"
                >
                    <!-- Op smalle schermen wint het label: drie labels met icoon passen niet. -->
                    <component :is="tab.icon" class="hidden size-4 shrink-0 sm:block" />
                    <span class="truncate">{{ tab.label }}</span>
                    <span class="tabular text-xs opacity-70">{{ counts[tab.key] }}</span>
                </button>
            </div>

            <!-- ================= SPELERS ================= -->
            <template v-if="type === 'players'">
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-muted-foreground">
                        <span class="tabular">{{ players?.length ?? 0 }}</span>
                        {{ (players?.length ?? 0) === 1 ? 'speler' : 'spelers' }} gevonden
                    </p>

                    <Link
                        v-if="can.managePlayers"
                        href="/players/create"
                        class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                    >
                        <Plus class="size-4" />
                        Speler toevoegen
                    </Link>
                </div>

                <div class="mt-3 grid gap-3 grid-cols-2 lg:grid-cols-4">
                    <div class="relative sm:col-span-2 lg:col-span-1">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <input
                            v-model="filters.search"
                            type="search"
                            placeholder="Zoek op naam..."
                            class="w-full rounded-lg border border-input bg-card py-2 pl-9 pr-3 text-sm outline-none focus:border-primary"
                        />
                    </div>

                    <select v-model="filters.position" class="rounded-lg border border-input bg-card px-3 py-2 text-sm outline-none focus:border-primary">
                        <option value="">Alle posities</option>
                        <option v-for="(label, waarde) in positions" :key="waarde" :value="waarde">{{ label }}</option>
                    </select>

                    <select v-model="filters.group" class="rounded-lg border border-input bg-card px-3 py-2 text-sm outline-none focus:border-primary">
                        <option :value="null">Alle groepen</option>
                        <option v-for="groep in groups" :key="groep.id" :value="groep.id">{{ groep.name }}</option>
                    </select>

                    <select v-model="filters.status" class="rounded-lg border border-input bg-card px-3 py-2 text-sm outline-none focus:border-primary">
                        <option value="active">Actieve spelers</option>
                        <option value="inactive">Niet-actieve spelers</option>
                        <option value="all">Alle spelers</option>
                    </select>
                </div>

                <div v-if="players?.length" class="mt-4 overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                    <Link
                        v-for="(speler, index) in players"
                        :key="speler.id"
                        :href="'/players/' + speler.id"
                        class="flex items-center gap-4 p-4 transition hover:bg-secondary/60"
                        :class="index > 0 ? 'border-t border-border' : ''"
                    >
                        <div
                            class="tabular flex size-11 shrink-0 items-center justify-center rounded-lg text-base font-bold"
                            :class="speler.overall_rating ? 'bg-primary/10 text-primary' : 'bg-secondary text-muted-foreground'"
                        >
                            {{ speler.overall_rating ?? '—' }}
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-2 truncate font-medium">
                                {{ speler.name }}
                                <span v-if="!speler.is_active" class="rounded bg-secondary px-1.5 py-0.5 text-[10px] text-muted-foreground">
                                    niet actief
                                </span>
                                <!-- Heeft dit kind zelf een inlog, of loopt alles via de ouder? -->
                                <KeyRound v-if="speler.has_login" class="size-3.5 shrink-0 text-muted-foreground" title="Heeft een eigen inlog" />
                            </p>
                            <p class="truncate text-xs text-muted-foreground">
                                {{ speler.position }}<span v-if="speler.age"> &middot; {{ speler.age }} jaar</span>
                                <span v-if="speler.groups.length"> &middot; {{ speler.groups.join(', ') }}</span>
                                <span v-else> &middot; nog geen groep</span>
                            </p>
                        </div>
                    </Link>
                </div>

                <div v-else class="mt-4 rounded-2xl border border-dashed border-border bg-card/50 p-10 text-center">
                    <p class="font-medium">Geen spelers gevonden</p>
                    <p class="mt-1 text-sm text-muted-foreground">Pas je filters aan of voeg een speler toe.</p>
                </div>
            </template>

            <!-- ================= TRAINERS ================= -->
            <template v-else-if="type === 'trainers'">
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-muted-foreground">Wie geeft er training bij jouw school.</p>

                    <Button v-if="can.manageAccounts && !toonTrainerFormulier" @click="toonTrainerFormulier = true">
                        <Plus class="mr-2 size-4" />
                        Trainer uitnodigen
                    </Button>
                </div>

                <form
                    v-if="toonTrainerFormulier"
                    class="mt-3 space-y-4 rounded-xl border border-border bg-card p-5 shadow-sm"
                    @submit.prevent="nodigTrainerUit"
                >
                    <p class="text-sm font-medium">Nieuwe trainer uitnodigen</p>
                    <p class="text-xs text-muted-foreground">
                        De trainer krijgt een e-mail om zelf een wachtwoord te kiezen. Jij hoeft er geen te bedenken.
                    </p>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="trainer_name">Naam</Label>
                            <Input id="trainer_name" v-model="trainerForm.name" required />
                            <InputError :message="trainerForm.errors.name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="trainer_email">E-mailadres</Label>
                            <Input id="trainer_email" v-model="trainerForm.email" type="email" required placeholder="naam@voorbeeld.nl" />
                            <InputError :message="trainerForm.errors.email" />
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <Button type="submit" :disabled="trainerForm.processing">Uitnodigen</Button>
                        <button type="button" class="text-sm text-muted-foreground underline underline-offset-4" @click="toonTrainerFormulier = false">
                            Annuleren
                        </button>
                    </div>
                </form>

                <div v-if="trainers?.length" class="mt-4 overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                    <div
                        v-for="(trainer, index) in trainers"
                        :key="trainer.id"
                        class="flex items-center gap-4 p-4"
                        :class="index > 0 ? 'border-t border-border' : ''"
                    >
                        <span class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <UserCog class="size-5" />
                        </span>

                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-2 truncate font-medium">
                                {{ trainer.name }}
                                <span v-if="trainer.is_owner" class="rounded bg-secondary px-1.5 py-0.5 text-[10px] text-muted-foreground">
                                    eigenaar
                                </span>
                            </p>
                            <p class="truncate text-xs text-muted-foreground">{{ trainer.email }}</p>
                            <p class="tabular mt-1 flex flex-wrap gap-x-3 text-xs text-muted-foreground">
                                <span>{{ trainer.trainings_count }} {{ trainer.trainings_count === 1 ? 'training' : 'trainingen' }}</span>
                                <span class="inline-flex items-center gap-1">
                                    <ClipboardList class="size-3" />
                                    {{ trainer.reports_count }} {{ trainer.reports_count === 1 ? 'rapport' : 'rapporten' }}
                                </span>
                            </p>
                        </div>

                        <button
                            v-if="can.manageAccounts && !trainer.is_owner"
                            type="button"
                            class="shrink-0 rounded-lg p-2 text-muted-foreground transition hover:bg-secondary hover:text-destructive"
                            :aria-label="'Account van ' + trainer.name + ' verwijderen'"
                            @click="verwijderTrainer(trainer.id, trainer.name)"
                        >
                            <Trash2 class="size-4" />
                        </button>
                    </div>
                </div>

                <div v-else class="mt-4 rounded-2xl border border-dashed border-border bg-card/50 p-10 text-center">
                    <p class="font-medium">Nog geen trainers</p>
                    <p class="mt-1 text-sm text-muted-foreground">Nodig er een uit om trainingen en rapporten te verdelen.</p>
                </div>
            </template>

            <!-- ================= OUDERS ================= -->
            <template v-else>
                <p class="mt-4 text-sm text-muted-foreground">
                    Ouders koppel je aan een kind op de spelerspagina. Hier zie je wie er aan wie hangt.
                </p>

                <div v-if="guardians?.length" class="mt-4 overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                    <div
                        v-for="(ouder, index) in guardians"
                        :key="ouder.id"
                        class="p-4"
                        :class="index > 0 ? 'border-t border-border' : ''"
                    >
                        <div class="flex items-center gap-4">
                            <span class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-secondary text-muted-foreground">
                                <Contact class="size-5" />
                            </span>

                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium">{{ ouder.name }}</p>
                                <p class="truncate text-xs text-muted-foreground">{{ ouder.email }}</p>
                            </div>
                        </div>

                        <div v-if="ouder.children.length" class="mt-3 flex flex-wrap gap-2 pl-[3.75rem]">
                            <Link
                                v-for="kind in ouder.children"
                                :key="kind.id"
                                :href="'/players/' + kind.id"
                                class="rounded-lg border border-border px-2.5 py-1 text-xs transition hover:border-primary"
                            >
                                {{ kind.name }}
                                <span v-if="kind.relationship" class="text-muted-foreground">({{ kind.relationship }})</span>
                            </Link>
                        </div>

                        <p v-else class="mt-2 pl-[3.75rem] text-xs text-warning">Nog niet aan een kind gekoppeld.</p>
                    </div>
                </div>

                <div v-else class="mt-4 rounded-2xl border border-dashed border-border bg-card/50 p-10 text-center">
                    <p class="font-medium">Nog geen ouders</p>
                    <p class="mt-1 text-sm text-muted-foreground">Nodig een ouder uit vanaf de pagina van een speler.</p>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
