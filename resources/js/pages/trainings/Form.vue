<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import ToggleSwitch from '@/components/ToggleSwitch.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle, TriangleAlert } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    training: {
        id: number;
        group_id: number | null;
        is_private?: boolean;
        label?: string;
        date: string;
        starts_at: string;
        ends_at: string;
        location: string | null;
        location_id: number | null;
        note: string | null;
        trainers: number[];
        open_enrollment: boolean;
        age_categories: string[];
        audience: string;
        capacity: number | null;
        price: string;
        payment_methods: string[];
        requires_approval: boolean;
    } | null;
    groups: { id: number; name: string; age_category: string | null }[];
    availableTrainers: { id: number; name: string }[];
    locations: { id: number; name: string }[];
    defaults?: { open: boolean; payment_methods: string[]; requires_approval: boolean };
    ageCategories: { key: string; label: string }[];
    audiences: Record<string, string>;
    gatewayConnected: boolean;
}>();

const bewerken = computed(() => props.training !== null);

// Een privétraining (geboekt moment) heeft geen groep en krijgt er ook geen.
const prive = computed(() => props.training?.is_private === true);

// Een oude training met alleen een vrije tekst als locatie: die blijft staan
// zolang je hier niets kiest.
const vrijeLocatie = computed(() => (props.training && !props.training.location_id ? props.training.location : null));

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Trainingen', href: '/trainings' },
    bewerken.value ? { title: 'Bewerken', href: '/trainings/' + props.training!.id + '/edit' } : { title: 'Inplannen', href: '/trainings/create' },
]);

const form = useForm({
    group_id: prive.value ? null : (props.training?.group_id ?? props.groups[0]?.id ?? ''),
    date: props.training?.date ?? '',
    starts_at: props.training?.starts_at ?? '18:00',
    ends_at: props.training?.ends_at ?? '19:30',
    location_id: props.training?.location_id ?? null,
    note: props.training?.note ?? '',
    trainers: props.training?.trainers ?? ([] as number[]),
    repeat_until: '',
    // Los inschrijven: wie mag meedoen, hoeveel, wat kost het, hoe betalen.
    // Een nieuwe training begint met wat de school in de wizard koos.
    open_enrollment: props.training?.open_enrollment ?? props.defaults?.open ?? false,
    age_categories: (props.training?.age_categories ?? []) as string[],
    audience: props.training?.audience ?? 'all',
    capacity: props.training?.capacity ?? null,
    price: props.training?.price ?? '0,00',
    payment_methods: [...(props.training?.payment_methods ?? props.defaults?.payment_methods ?? ['online', 'cash'])] as string[],
    requires_approval: props.training?.requires_approval ?? props.defaults?.requires_approval ?? false,
});

const wisselCategorie = (key: string) => {
    const i = form.age_categories.indexOf(key);
    if (i === -1) form.age_categories.push(key);
    else form.age_categories.splice(i, 1);
};

const wisselBetaalwijze = (key: string) => {
    const i = form.payment_methods.indexOf(key);
    if (i === -1) form.payment_methods.push(key);
    else form.payment_methods.splice(i, 1);
};

const gratis = computed(() => !form.price || Number(String(form.price).replace(',', '.')) === 0);

const wisselTrainer = (id: number) => {
    const positie = form.trainers.indexOf(id);

    if (positie === -1) {
        form.trainers.push(id);
    } else {
        form.trainers.splice(positie, 1);
    }
};

const herhalen = ref(false);

const opslaan = () => {
    if (bewerken.value) {
        form.put('/trainings/' + props.training!.id);
    } else {
        form.transform((data) => ({ ...data, repeat_until: herhalen.value ? data.repeat_until : null })).post('/trainings');
    }
};
</script>

<template>
    <Head :title="bewerken ? 'Training bewerken' : 'Training inplannen'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-xl p-4">
            <h1 class="text-2xl font-semibold tracking-tight">
                {{ bewerken ? 'Training bewerken' : 'Training inplannen' }}
            </h1>

            <!-- Zonder locatie kan een training wel, maar dan weet een ouder
                 niet waar hij moet zijn. Bovenaan, niet als voetnoot bij het
                 veld: dit is de stap die je vóór het inplannen hoort te doen. -->
            <div v-if="groups.length && !locations.length" class="mt-4 flex gap-3 rounded-xl border border-warning/40 bg-warning/10 p-4 text-sm">
                <TriangleAlert class="mt-0.5 size-5 shrink-0 text-warning" />
                <div class="min-w-0">
                    <p class="font-medium">Je hebt nog geen locatie</p>
                    <p class="mt-1 text-muted-foreground">Ouders zien bij de training waar ze moeten zijn. Zet eerst je locatie neer, dan kies je hem hier.</p>
                    <Link href="/locaties" class="mt-2 inline-flex min-h-11 items-center font-medium text-primary underline underline-offset-4">
                        Eerst een locatie toevoegen
                    </Link>
                </div>
            </div>

            <form v-if="groups.length || prive" class="mt-6 space-y-5 rounded-xl border border-border bg-card p-5 shadow-sm" @submit.prevent="opslaan">
                <div v-if="prive" class="rounded-lg border border-border bg-background p-3 text-sm">
                    <p class="font-medium">{{ training?.label }}</p>
                    <p class="mt-0.5 text-xs text-muted-foreground">Een privétraining hoort bij één kind en niet bij een groep.</p>
                </div>

                <div v-else class="grid gap-2">
                    <Label for="group_id">Groep</Label>
                    <select
                        id="group_id"
                        v-model="form.group_id"
                        class="min-h-11 rounded-lg border border-input bg-background px-3 text-sm outline-none focus:border-primary"
                    >
                        <option v-for="groep in groups" :key="groep.id" :value="groep.id">
                            {{ groep.name }}<span v-if="groep.age_category"> ({{ groep.age_category }})</span>
                        </option>
                    </select>
                    <p class="text-xs text-muted-foreground">De actieve spelers van deze groep worden verwacht.</p>
                    <InputError :message="form.errors.group_id" />
                </div>

                <div class="grid gap-5 sm:grid-cols-3">
                    <div class="grid gap-2 sm:col-span-1">
                        <Label for="date">Datum</Label>
                        <Input id="date" v-model="form.date" type="date" required />
                        <InputError :message="form.errors.date" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="starts_at">Van</Label>
                        <Input id="starts_at" v-model="form.starts_at" type="time" required />
                        <InputError :message="form.errors.starts_at" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="ends_at">Tot</Label>
                        <Input id="ends_at" v-model="form.ends_at" type="time" required />
                        <InputError :message="form.errors.ends_at" />
                    </div>
                </div>

                <!-- Los inschrijven: naast de groep mogen er kinderen van buiten
                     aanschuiven. De regels staan op de training zelf; het scherm
                     van de ouder laat alleen zien wat hier is toegestaan, de server
                     controleert het echt. -->
                <div class="rounded-xl border border-border bg-background p-4">
                    <ToggleSwitch
                        v-model="form.open_enrollment"
                        label="Los inschrijven mogelijk"
                        description="Ouders kunnen hun kind voor deze ene training aanmelden, naast de groep."
                    />

                    <div v-if="form.open_enrollment" class="mt-4 space-y-5 border-t border-border pt-4">
                        <div class="grid gap-2">
                            <Label>Leeftijd</Label>
                            <p class="text-xs text-muted-foreground">Niets aangevinkt is iedereen.</p>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="cat in ageCategories"
                                    :key="cat.key"
                                    type="button"
                                    class="min-h-11 rounded-lg border px-3 text-sm transition"
                                    :class="
                                        form.age_categories.includes(cat.key)
                                            ? 'border-primary bg-primary/10 font-medium text-primary'
                                            : 'border-border bg-card text-muted-foreground hover:border-primary'
                                    "
                                    :aria-pressed="form.age_categories.includes(cat.key)"
                                    @click="wisselCategorie(cat.key)"
                                >
                                    {{ cat.label }}
                                </button>
                            </div>
                            <InputError :message="form.errors.age_categories" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="audience">Positie</Label>
                            <select
                                id="audience"
                                v-model="form.audience"
                                class="min-h-11 rounded-lg border border-input bg-card px-3 text-sm outline-none focus:border-primary"
                            >
                                <option v-for="(label, waarde) in audiences" :key="waarde" :value="waarde">{{ label }}</option>
                            </select>
                            <InputError :message="form.errors.audience" />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="capacity">Maximaal aantal <span class="text-muted-foreground">(leeg = onbeperkt)</span></Label>
                                <Input id="capacity" v-model.number="form.capacity" type="number" min="1" max="500" placeholder="Onbeperkt" />
                                <p class="text-xs text-muted-foreground">De groep telt mee. Vol is vol; daarna komt er een wachtlijst.</p>
                                <InputError :message="form.errors.capacity" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="price">Prijs per training</Label>
                                <div class="relative">
                                    <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-muted-foreground">€</span>
                                    <Input id="price" v-model="form.price" type="text" inputmode="decimal" class="pl-7" placeholder="0,00" />
                                </div>
                                <p class="text-xs text-muted-foreground">
                                    {{ gratis ? 'Gratis: er ontstaat geen rekening.' : 'Per kind, per training.' }}
                                </p>
                                <InputError :message="form.errors.price" />
                            </div>
                        </div>

                        <div v-if="!gratis" class="grid gap-2">
                            <Label>Betalen</Label>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <button
                                    type="button"
                                    class="flex min-h-11 items-center gap-3 rounded-lg border p-3 text-left text-sm transition"
                                    :class="form.payment_methods.includes('online') ? 'border-primary bg-primary/10' : 'border-border bg-card'"
                                    :aria-pressed="form.payment_methods.includes('online')"
                                    @click="wisselBetaalwijze('online')"
                                >
                                    <span class="min-w-0">
                                        <span class="block font-medium">Direct online betalen</span>
                                        <span class="block text-xs text-muted-foreground">
                                            {{ gatewayConnected ? 'Via de betaalprovider.' : 'Pas mogelijk zodra de betaalprovider is aangesloten.' }}
                                        </span>
                                    </span>
                                </button>
                                <button
                                    type="button"
                                    class="flex min-h-11 items-center gap-3 rounded-lg border p-3 text-left text-sm transition"
                                    :class="form.payment_methods.includes('cash') ? 'border-primary bg-primary/10' : 'border-border bg-card'"
                                    :aria-pressed="form.payment_methods.includes('cash')"
                                    @click="wisselBetaalwijze('cash')"
                                >
                                    <span class="min-w-0">
                                        <span class="block font-medium">Contant ter plaatse</span>
                                        <span class="block text-xs text-muted-foreground"
                                            >De trainer vinkt bij de training af dat het binnen is.</span
                                        >
                                    </span>
                                </button>
                            </div>
                            <InputError :message="form.errors.payment_methods" />
                        </div>

                        <ToggleSwitch
                            v-model="form.requires_approval"
                            label="Goedkeuring nodig"
                            description="Een aanmelding is dan eerst een aanvraag; je keurt goed of wijst af, en betalen komt daarna. Handig bij kampen of selectietrainingen."
                        />
                    </div>
                </div>

                <!-- Trainers: tikken in plaats van een multiselect, dat werkt op
                     mobiel veel prettiger -->
                <div class="grid gap-2">
                    <Label>Trainer(s)</Label>

                    <div v-if="availableTrainers.length" class="flex flex-wrap gap-2">
                        <button
                            v-for="trainer in availableTrainers"
                            :key="trainer.id"
                            type="button"
                            class="min-h-11 rounded-lg border px-3 py-2 text-sm transition"
                            :class="
                                form.trainers.includes(trainer.id)
                                    ? 'border-primary bg-primary/10 font-medium text-primary'
                                    : 'border-border bg-background text-muted-foreground hover:border-primary'
                            "
                            :aria-pressed="form.trainers.includes(trainer.id)"
                            @click="wisselTrainer(trainer.id)"
                        >
                            {{ trainer.name }}
                        </button>
                    </div>

                    <p v-else class="text-sm text-muted-foreground">
                        Er zijn nog geen trainers.
                        <Link href="/staff" class="font-medium text-primary underline underline-offset-4">Nodig er een uit</Link>.
                    </p>

                    <p class="text-xs text-muted-foreground">Er mogen er meerdere bij staan, bijvoorbeeld een vaste trainer en een invaller.</p>
                    <InputError :message="form.errors.trainers" />
                </div>

                <div class="grid gap-2">
                    <Label for="location_id">Locatie <span class="text-muted-foreground">(optioneel)</span></Label>
                    <select
                        id="location_id"
                        v-model="form.location_id"
                        class="h-11 w-full rounded-lg border border-input bg-background px-3 text-base outline-none focus:border-primary sm:h-10 sm:text-sm"
                    >
                        <option :value="null">Nog niet bekend</option>
                        <option v-for="locatie in locations" :key="locatie.id" :value="locatie.id">{{ locatie.name }}</option>
                    </select>
                    <p v-if="vrijeLocatie && form.location_id === null" class="text-xs text-muted-foreground">
                        Nu: {{ vrijeLocatie }}. Dat blijft staan zolang je hier niets kiest.
                    </p>
                    <p v-if="!locations.length" class="text-xs text-muted-foreground">
                        Je hebt nog geen locaties.
                        <Link href="/locaties" class="font-medium text-primary underline underline-offset-4">Zet er een neer</Link>, dan staat hij hier.
                    </p>
                    <InputError :message="form.errors.location_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="note">Toelichting <span class="text-muted-foreground">(optioneel)</span></Label>
                    <textarea
                        id="note"
                        v-model="form.note"
                        rows="2"
                        class="w-full rounded-lg border border-input bg-background p-3 text-sm outline-none focus:border-primary"
                        placeholder="Neem je keepershandschoenen mee."
                    ></textarea>
                    <InputError :message="form.errors.note" />
                </div>

                <!-- Wekelijks herhalen: een seizoen plan je niet training voor training -->
                <div v-if="!bewerken" class="rounded-lg border border-border p-3">
                    <label class="flex items-center gap-3">
                        <input v-model="herhalen" type="checkbox" class="size-4 accent-[hsl(var(--primary))]" />
                        <span class="text-sm font-medium">Wekelijks herhalen</span>
                    </label>

                    <div v-if="herhalen" class="mt-3 grid gap-2">
                        <Label for="repeat_until">Tot en met</Label>
                        <Input id="repeat_until" v-model="form.repeat_until" type="date" />
                        <p class="text-xs text-muted-foreground">
                            Er komt elke week een losse training bij. Die staan daarna op zichzelf, dus je past ze los van elkaar aan.
                        </p>
                        <InputError :message="form.errors.repeat_until" />
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-1">
                    <Button type="submit" :disabled="form.processing">
                        <LoaderCircle v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                        {{ bewerken ? 'Wijzigingen opslaan' : 'Inplannen' }}
                    </Button>

                    <Link
                        href="/trainings"
                        class="inline-flex min-h-11 items-center text-sm text-muted-foreground underline underline-offset-4 hover:text-foreground"
                    >
                        Annuleren
                    </Link>
                </div>
            </form>

            <div v-else class="mt-6 rounded-2xl border border-dashed border-border bg-card/50 p-8 text-center">
                <TriangleAlert class="mx-auto size-6 text-warning" />
                <p class="mt-2 font-medium">Eerst een groep nodig</p>
                <p class="mx-auto mt-1 max-w-sm text-sm text-muted-foreground">
                    Een training hoort altijd bij een groep: wie erin zit, staat op de aanwezigheidslijst. De volgorde is locatie → groep →
                    training.
                </p>
                <div class="mt-4 flex flex-col items-center gap-2">
                    <Link
                        href="/groups/create"
                        class="inline-flex min-h-11 items-center rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground hover:opacity-90"
                    >
                        Groep aanmaken
                    </Link>
                    <Link v-if="!locations.length" href="/locaties" class="inline-flex min-h-11 items-center text-sm font-medium text-primary underline underline-offset-4">
                        Nog geen locatie? Begin daar
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
