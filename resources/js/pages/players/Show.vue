<script setup lang="ts">
import Avatar from '@/components/Avatar.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import GoalList, { type Doel } from '@/components/GoalList.vue';
import InputError from '@/components/InputError.vue';
import PhotoUpload from '@/components/PhotoUpload.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ClipboardList, FileDown, IdCard, Pencil, Plus, Target, Trash2, UserPlus, X } from 'lucide-vue-next';
import { ref } from 'vue';

interface Categorie {
    category: string;
    label: string;
    hint: string;
    rating: number | null;
}

const props = defineProps<{
    player: {
        id: number;
        first_name: string;
        last_name: string;
        name: string;
        photo: string | null;
        date_of_birth: string;
        age: number | null;
        position: string;
        is_active: boolean;
        overall_rating: number | null;
        rated_at: string | null;
    };
    categories: Categorie[];
    groups: { id: number; name: string; age_category: string | null }[];
    guardians: { id: number; name: string; email: string; relationship: string | null }[];
    reports: { id: number; reported_on: string; trainer: string | null; note: string | null }[];
    reportCount: number;
    purchases: {
        id: number;
        name: string;
        type: string;
        type_label: string;
        amount: string;
        credits_total: number | null;
        credits_left: number | null;
        starts_on: string;
        expires_on: string | null;
        expired: boolean;
        status: string;
        note: string | null;
    }[];
    sellableProducts: { id: number; name: string; type_label: string; amount: string; credits: number | null }[];
    linkableGuardians: { id: number; name: string; email: string }[];
    goals: Doel[];
    goalCategories: { value: string; label: string }[];
    can: { manage: boolean; delete: boolean; report: boolean; goals: boolean; dataExport: boolean };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Spelers', href: '/players' },
    { title: props.player.name, href: '/players/' + props.player.id },
];

const nieuweOuder = useForm({ name: '', email: '', relationship: '' });
const bestaandeOuder = useForm({ user_id: '', relationship: '' });

const toonNieuweOuder = ref(false);

// --- Doelen ---
const toonDoelFormulier = ref(false);

const doelForm = useForm({
    category: props.goalCategories[0]?.value ?? '',
    target: 8,
    due_on: '',
    note: '',
});

const stelDoel = () =>
    doelForm.post('/players/' + props.player.id + '/goals', {
        preserveScroll: true,
        onSuccess: () => {
            doelForm.reset();
            toonDoelFormulier.value = false;
        },
    });

// --- Producten ---
const toonProductFormulier = ref(false);

const productForm = useForm({ product_id: '', starts_on: '', note: '' });

const kenToe = () =>
    productForm.post('/players/' + props.player.id + '/purchases', {
        preserveScroll: true,
        onSuccess: () => {
            productForm.reset();
            toonProductFormulier.value = false;
        },
    });

const trekIn = (id: number, naam: string) => {
    if (confirm(`${naam} intrekken? De rekening die eraan hangt blijft staan.`)) {
        router.delete('/players/' + props.player.id + '/purchases/' + id, { preserveScroll: true });
    }
};

const stopDoel = (id: number) => {
    if (confirm('Dit doel stoppen?')) {
        router.delete('/goals/' + id, { preserveScroll: true });
    }
};

const koppelBestaande = () => bestaandeOuder.post('/players/' + props.player.id + '/guardians', { preserveScroll: true });

const nodigUit = () =>
    nieuweOuder.post('/players/' + props.player.id + '/guardians/invite', {
        preserveScroll: true,
        onSuccess: () => {
            nieuweOuder.reset();
            toonNieuweOuder.value = false;
        },
    });

const ontkoppel = (id: number) => router.delete('/players/' + props.player.id + '/guardians/' + id, { preserveScroll: true });

const verwijderen = () => {
    if (confirm('Weet je zeker dat je ' + props.player.name + ' wilt verwijderen? Ook alle rapporten van deze speler verdwijnen.')) {
        router.delete('/players/' + props.player.id);
    }
};
</script>

<template>
    <Head :title="player.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-4xl p-4">
            <FlashMessage />

            <!-- Kop met acties -->
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex min-w-0 items-center gap-4">
                    <Avatar :name="player.name" :photo="player.photo" size="size-14" />

                    <div class="min-w-0">
                        <h1 class="flex items-center gap-2 text-2xl font-semibold tracking-tight">
                            {{ player.name }}
                            <span v-if="!player.is_active" class="rounded bg-secondary px-2 py-0.5 text-xs font-medium text-muted-foreground">
                                niet actief
                            </span>
                        </h1>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ player.position }} &middot; {{ player.date_of_birth }}<span v-if="player.age"> ({{ player.age }} jaar)</span>
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Link
                        :href="'/players/' + player.id + '/card'"
                        class="inline-flex items-center gap-2 rounded-xl border border-border bg-card px-4 py-2 text-sm font-medium shadow-sm transition hover:border-primary"
                    >
                        <IdCard class="size-4" />
                        Spelerskaart
                    </Link>

                    <Link
                        v-if="can.report"
                        :href="'/players/' + player.id + '/reports/create'"
                        class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                    >
                        <ClipboardList class="size-4" />
                        Rapport invullen
                    </Link>

                    <Link
                        v-if="can.manage"
                        :href="'/players/' + player.id + '/edit'"
                        class="inline-flex items-center gap-2 rounded-xl border border-border bg-card px-4 py-2 text-sm font-medium shadow-sm transition hover:border-primary"
                    >
                        <Pencil class="size-4" />
                        Bewerken
                    </Link>
                </div>
            </div>

            <div class="mt-6 grid gap-4 lg:grid-cols-2">
                <!-- Cijfers -->
                <div class="rounded-xl border border-border bg-card p-5 shadow-sm">
                    <div class="flex items-baseline justify-between">
                        <p class="font-medium">Huidige cijfers</p>
                        <p
                            class="tabular text-3xl font-bold leading-none"
                            :class="player.overall_rating ? 'text-primary' : 'text-muted-foreground/60'"
                        >
                            {{ player.overall_rating ?? '—' }}
                        </p>
                    </div>

                    <div v-if="player.overall_rating" class="mt-4 space-y-3">
                        <div v-for="categorie in categories" :key="categorie.category">
                            <div class="flex items-baseline justify-between text-sm">
                                <span>{{ categorie.label }}</span>
                                <span class="tabular font-semibold">{{ categorie.rating ?? '—' }}</span>
                            </div>
                            <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-secondary">
                                <div class="h-full rounded-full bg-primary" :style="{ width: (categorie.rating ?? 0) + '%' }"></div>
                            </div>
                        </div>
                        <p class="pt-1 text-xs text-muted-foreground">
                            <span class="tabular">{{ reportCount }}</span> {{ reportCount === 1 ? 'rapport' : 'rapporten' }}
                            <span v-if="player.rated_at">&middot; bijgewerkt {{ player.rated_at }}</span>
                        </p>
                    </div>

                    <p v-else class="mt-3 text-sm text-muted-foreground">Nog geen rapporten, dus nog geen cijfers.</p>
                </div>

                <!-- Groepen -->
                <div class="rounded-xl border border-border bg-card p-5 shadow-sm">
                    <p class="font-medium">Groepen</p>

                    <div v-if="groups.length" class="mt-3 flex flex-wrap gap-2">
                        <span
                            v-for="groep in groups"
                            :key="groep.id"
                            class="rounded-lg border border-primary/30 bg-primary/5 px-3 py-1.5 text-sm text-primary"
                        >
                            {{ groep.name }}
                            <span v-if="groep.age_category" class="text-xs opacity-70">{{ groep.age_category }}</span>
                        </span>
                    </div>

                    <p v-else class="mt-3 text-sm text-muted-foreground">Deze speler zit nog in geen enkele groep.</p>

                    <Link
                        v-if="can.manage"
                        :href="'/players/' + player.id + '/edit'"
                        class="mt-4 inline-block text-sm font-medium text-primary underline underline-offset-4"
                    >
                        Groepen aanpassen
                    </Link>
                </div>
            </div>

            <!-- Doelen: waar werkt deze speler naartoe -->
            <div class="mt-4 rounded-xl border border-border bg-card p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <p class="font-medium">Ontwikkelingsdoelen</p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Een streefcijfer per categorie, met een einddatum. Ouders zien dit op de kaart.
                        </p>
                    </div>
                    <Button v-if="can.goals && !toonDoelFormulier" variant="secondary" @click="toonDoelFormulier = true">
                        <Target class="mr-2 size-4" />
                        Doel stellen
                    </Button>
                </div>

                <form v-if="toonDoelFormulier" class="mt-4 space-y-4 rounded-lg border border-border p-4" @submit.prevent="stelDoel">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="goal_category">Categorie</Label>
                            <select
                                id="goal_category"
                                v-model="doelForm.category"
                                class="h-10 rounded-lg border border-input bg-background px-3 text-sm outline-none focus:border-primary"
                            >
                                <option v-for="c in goalCategories" :key="c.value" :value="c.value">{{ c.label }}</option>
                            </select>
                            <InputError :message="doelForm.errors.category" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="goal_due">Halen vóór</Label>
                            <Input id="goal_due" v-model="doelForm.due_on" type="date" required />
                            <InputError :message="doelForm.errors.due_on" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label>Streefcijfer <span class="text-muted-foreground">(op de kaart wordt dit maal tien)</span></Label>
                        <div class="grid grid-cols-10 gap-1">
                            <button
                                v-for="n in 10"
                                :key="n"
                                type="button"
                                class="tabular h-10 rounded-lg border text-sm font-semibold transition"
                                :class="
                                    doelForm.target === n
                                        ? 'border-transparent bg-primary text-primary-foreground'
                                        : 'border-border text-muted-foreground hover:border-primary'
                                "
                                @click="doelForm.target = n"
                            >
                                {{ n }}
                            </button>
                        </div>
                        <InputError :message="doelForm.errors.target" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="goal_note">Toelichting <span class="text-muted-foreground">(optioneel)</span></Label>
                        <Input id="goal_note" v-model="doelForm.note" placeholder="Waar gaan we op letten?" />
                    </div>

                    <div class="flex items-center gap-3">
                        <Button type="submit" :disabled="doelForm.processing || !doelForm.due_on">Doel stellen</Button>
                        <button type="button" class="text-sm text-muted-foreground underline underline-offset-4" @click="toonDoelFormulier = false">
                            Annuleren
                        </button>
                    </div>
                </form>

                <GoalList v-if="goals.length" class="mt-4" :goals="goals" :can-stop="can.goals" @stop="stopDoel" />
                <p v-else-if="!toonDoelFormulier" class="mt-3 text-sm text-muted-foreground">
                    Nog geen doel. Een concreet doel maakt een rapport pas echt spannend.
                </p>
            </div>

            <!-- Ouders -->
            <div class="mt-4 rounded-xl border border-border bg-card p-5 shadow-sm">
                <p class="font-medium">Ouders</p>
                <p class="mt-1 text-xs text-muted-foreground">Gekoppelde ouders zien de spelerskaart en de voortgang van hun kind.</p>

                <div v-if="guardians.length" class="mt-4 space-y-2">
                    <div v-for="ouder in guardians" :key="ouder.id" class="flex items-center gap-3 rounded-lg border border-border p-3">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">
                                {{ ouder.name }}
                                <span v-if="ouder.relationship" class="text-xs font-normal text-muted-foreground">({{ ouder.relationship }})</span>
                            </p>
                            <p class="truncate text-xs text-muted-foreground">{{ ouder.email }}</p>
                        </div>

                        <button
                            v-if="can.manage"
                            type="button"
                            class="rounded-lg p-2 text-muted-foreground transition hover:bg-secondary hover:text-destructive"
                            :aria-label="'Koppeling met ' + ouder.name + ' verwijderen'"
                            @click="ontkoppel(ouder.id)"
                        >
                            <X class="size-4" />
                        </button>
                    </div>
                </div>

                <p v-else class="mt-3 text-sm text-muted-foreground">Er is nog geen ouder gekoppeld.</p>

                <div v-if="can.manage" class="mt-5 border-t border-border pt-5">
                    <!-- Bestaande ouder koppelen -->
                    <form v-if="linkableGuardians.length" class="flex flex-wrap items-end gap-3" @submit.prevent="koppelBestaande">
                        <div class="grid min-w-48 flex-1 gap-2">
                            <Label for="user_id">Bestaande ouder koppelen</Label>
                            <select
                                id="user_id"
                                v-model="bestaandeOuder.user_id"
                                class="h-10 rounded-lg border border-input bg-background px-3 text-sm outline-none focus:border-primary"
                            >
                                <option value="">Kies een ouder...</option>
                                <option v-for="ouder in linkableGuardians" :key="ouder.id" :value="ouder.id">
                                    {{ ouder.name }} ({{ ouder.email }})
                                </option>
                            </select>
                            <InputError :message="bestaandeOuder.errors.user_id" />
                        </div>

                        <div class="grid w-40 gap-2">
                            <Label for="relationship">Relatie</Label>
                            <Input id="relationship" v-model="bestaandeOuder.relationship" placeholder="moeder" />
                        </div>

                        <Button type="submit" variant="secondary" :disabled="bestaandeOuder.processing || !bestaandeOuder.user_id"> Koppelen </Button>
                    </form>

                    <button
                        v-if="!toonNieuweOuder"
                        type="button"
                        class="mt-4 inline-flex items-center gap-2 text-sm font-medium text-primary underline underline-offset-4"
                        @click="toonNieuweOuder = true"
                    >
                        <UserPlus class="size-4" />
                        Nieuwe ouder uitnodigen
                    </button>

                    <!-- Nieuwe ouder uitnodigen -->
                    <form v-else class="mt-4 space-y-4 rounded-lg border border-border p-4" @submit.prevent="nodigUit">
                        <p class="text-sm font-medium">Nieuwe ouder uitnodigen</p>
                        <p class="text-xs text-muted-foreground">
                            De ouder krijgt een e-mail om zelf een wachtwoord te kiezen. Jij hoeft er dus geen te bedenken.
                        </p>

                        <div class="grid gap-4 sm:grid-cols-3">
                            <div class="grid gap-2">
                                <Label for="ouder_name">Naam</Label>
                                <Input id="ouder_name" v-model="nieuweOuder.name" required />
                                <InputError :message="nieuweOuder.errors.name" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="ouder_email">E-mailadres</Label>
                                <Input id="ouder_email" v-model="nieuweOuder.email" type="email" required placeholder="naam@voorbeeld.nl" />
                                <InputError :message="nieuweOuder.errors.email" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="ouder_relationship">Relatie</Label>
                                <Input id="ouder_relationship" v-model="nieuweOuder.relationship" placeholder="moeder" />
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <Button type="submit" :disabled="nieuweOuder.processing">Uitnodigen</Button>
                            <button type="button" class="text-sm text-muted-foreground underline underline-offset-4" @click="toonNieuweOuder = false">
                                Annuleren
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Laatste rapporten -->
            <div class="mt-4 rounded-xl border border-border bg-card p-5 shadow-sm">
                <p class="font-medium">Laatste rapporten</p>

                <div v-if="reports.length" class="mt-3 space-y-2">
                    <div v-for="rapport in reports" :key="rapport.id" class="rounded-lg border border-border p-3">
                        <p class="text-xs text-muted-foreground">
                            {{ rapport.reported_on }}<span v-if="rapport.trainer"> door {{ rapport.trainer }}</span>
                        </p>
                        <p v-if="rapport.note" class="mt-1 text-sm">{{ rapport.note }}</p>
                    </div>
                </div>

                <p v-else class="mt-3 text-sm text-muted-foreground">Er is nog geen rapport ingevuld voor deze speler.</p>
            </div>

            <!-- Wat deze speler afneemt. Abonnementen staan er bewust niet
                 bij: die hebben hun eigen scherm met termijnen en incasso. -->
            <div v-if="purchases.length || sellableProducts.length" class="mt-4 rounded-xl border border-border bg-card p-5 shadow-sm">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <p class="font-medium">Producten</p>
                    <Button v-if="sellableProducts.length && !toonProductFormulier" size="sm" @click="toonProductFormulier = true">
                        <Plus class="mr-2 size-4" />
                        Toekennen
                    </Button>
                </div>

                <form v-if="toonProductFormulier" class="mt-4 space-y-4 rounded-lg border border-border p-4" @submit.prevent="kenToe">
                    <div class="grid gap-2">
                        <Label for="product_id">Product</Label>
                        <select
                            id="product_id"
                            v-model="productForm.product_id"
                            required
                            class="h-10 rounded-lg border border-input bg-background px-3 text-base outline-none focus:border-primary sm:text-sm"
                        >
                            <option value="">Kies een product...</option>
                            <option v-for="product in sellableProducts" :key="product.id" :value="product.id">
                                {{ product.name }} &middot; {{ product.amount
                                }}<template v-if="product.credits"> &middot; {{ product.credits }} beurten</template>
                            </option>
                        </select>
                        <InputError :message="productForm.errors.product_id" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="starts_on">Ingangsdatum <span class="text-muted-foreground">(optioneel)</span></Label>
                            <Input id="starts_on" v-model="productForm.starts_on" type="date" />
                            <InputError :message="productForm.errors.starts_on" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="purchase_note">Notitie <span class="text-muted-foreground">(optioneel)</span></Label>
                            <Input id="purchase_note" v-model="productForm.note" placeholder="Contant betaald" />
                            <InputError :message="productForm.errors.note" />
                        </div>
                    </div>

                    <p class="text-xs text-muted-foreground">
                        Er ontstaat meteen een openstaande rekening. Die zet je zelf op betaald zodra het geld binnen is.
                    </p>

                    <div class="flex items-center gap-3">
                        <Button type="submit" :disabled="productForm.processing">Toekennen</Button>
                        <button
                            type="button"
                            class="text-sm text-muted-foreground underline underline-offset-4"
                            @click="toonProductFormulier = false"
                        >
                            Annuleren
                        </button>
                    </div>
                </form>

                <div v-if="purchases.length" class="mt-4 space-y-2">
                    <div
                        v-for="aankoop in purchases"
                        :key="aankoop.id"
                        class="flex flex-wrap items-center gap-3 rounded-lg border border-border p-3"
                        :class="aankoop.status !== 'active' || aankoop.expired ? 'opacity-60' : ''"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="flex flex-wrap items-center gap-2 text-sm font-medium">
                                {{ aankoop.name }}
                                <span class="rounded bg-secondary px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground">
                                    {{ aankoop.type_label }}
                                </span>
                                <span v-if="aankoop.status === 'cancelled'" class="text-[10px] text-muted-foreground">ingetrokken</span>
                                <span v-else-if="aankoop.expired" class="text-[10px] text-warning">verlopen</span>
                            </p>
                            <p class="tabular text-xs text-muted-foreground">
                                {{ aankoop.amount }} &middot; vanaf {{ aankoop.starts_on }}
                                <template v-if="aankoop.expires_on"> &middot; tot {{ aankoop.expires_on }}</template>
                                <template v-if="aankoop.note"> &middot; {{ aankoop.note }}</template>
                            </p>
                        </div>

                        <!-- Het saldo van een rittenkaart: het enige cijfer dat
                             een ouder aan de telefoon van je wil horen. -->
                        <p v-if="aankoop.credits_total" class="tabular shrink-0 text-sm">
                            <span class="font-bold" :class="(aankoop.credits_left ?? 0) > 0 ? 'text-primary' : 'text-muted-foreground'">
                                {{ aankoop.credits_left }}
                            </span>
                            <span class="text-xs text-muted-foreground">/ {{ aankoop.credits_total }} over</span>
                        </p>

                        <button
                            v-if="can.manage && aankoop.status === 'active'"
                            type="button"
                            class="shrink-0 rounded-lg p-2 text-muted-foreground transition hover:bg-secondary hover:text-destructive"
                            :aria-label="aankoop.name + ' intrekken'"
                            @click="trekIn(aankoop.id, aankoop.name)"
                        >
                            <Trash2 class="size-4" />
                        </button>
                    </div>
                </div>

                <p v-else-if="!toonProductFormulier" class="mt-3 text-sm text-muted-foreground">Nog niets afgenomen.</p>
            </div>

            <!-- De foto komt ook op de spelerskaart terecht; daarom staat de
                 uitleg erbij dat dit niet zomaar een lijstplaatje is. -->
            <div v-if="can.manage" class="mt-4 rounded-xl border border-border bg-card p-5 shadow-sm">
                <p class="font-medium">Pasfoto</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    Deze foto staat op de spelerskaart van {{ player.first_name }}, ook op een gedeelde kaart.
                </p>

                <div class="mt-4">
                    <PhotoUpload :name="player.name" :photo="player.photo" :action="'/players/' + player.id + '/photo'" />
                </div>
            </div>

            <!-- Verwijderen staat apart en onderaan: het is onomkeerbaar -->
            <!-- AVG: een ouder mag opvragen wat de school over zijn kind bewaart -->
            <div v-if="can.dataExport" class="mt-4 rounded-xl border border-border bg-card p-5 shadow-sm">
                <p class="font-medium">Inzageverzoek</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    Alles wat de school over {{ player.name }} bewaart in één Excel-bestand: profiel, ouders, rapporten, cijfers, aanwezigheid, doelen
                    en betalingen. Bedoeld om aan een ouder te geven die daarom vraagt.
                </p>
                <a
                    :href="'/players/' + player.id + '/gegevens'"
                    class="mt-4 inline-flex h-9 items-center rounded-lg border border-border px-3 text-sm font-medium hover:border-primary"
                >
                    <FileDown class="mr-2 size-4" />
                    Gegevens downloaden
                </a>
            </div>

            <div v-if="can.delete" class="mt-4 rounded-xl border border-destructive/25 bg-destructive/5 p-5">
                <p class="font-medium text-destructive">Speler verwijderen</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    Hiermee verdwijnen ook alle rapporten van deze speler. Wil je alleen dat hij niet meer meetelt, zet hem dan op niet-actief via
                    Bewerken.
                </p>
                <Button variant="destructive" class="mt-4" @click="verwijderen">
                    <Trash2 class="mr-2 size-4" />
                    Definitief verwijderen
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
