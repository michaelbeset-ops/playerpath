<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    training: {
        id: number;
        group_id: number;
        date: string;
        starts_at: string;
        ends_at: string;
        location: string | null;
        note: string | null;
        trainers: number[];
    } | null;
    groups: { id: number; name: string; age_category: string | null }[];
    availableTrainers: { id: number; name: string }[];
}>();

const bewerken = computed(() => props.training !== null);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Trainingen', href: '/trainings' },
    bewerken.value
        ? { title: 'Bewerken', href: '/trainings/' + props.training!.id + '/edit' }
        : { title: 'Inplannen', href: '/trainings/create' },
]);

const form = useForm({
    group_id: props.training?.group_id ?? (props.groups[0]?.id ?? ''),
    date: props.training?.date ?? '',
    starts_at: props.training?.starts_at ?? '18:00',
    ends_at: props.training?.ends_at ?? '19:30',
    location: props.training?.location ?? '',
    note: props.training?.note ?? '',
    trainers: props.training?.trainers ?? ([] as number[]),
    repeat_until: '',
});

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

            <form v-if="groups.length" class="mt-6 space-y-5 rounded-xl border border-border bg-card p-5 shadow-sm" @submit.prevent="opslaan">
                <div class="grid gap-2">
                    <Label for="group_id">Groep</Label>
                    <select
                        id="group_id"
                        v-model="form.group_id"
                        class="h-10 rounded-lg border border-input bg-background px-3 text-sm outline-none focus:border-primary"
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

                <!-- Trainers: tikken in plaats van een multiselect, dat werkt op
                     mobiel veel prettiger -->
                <div class="grid gap-2">
                    <Label>Trainer(s)</Label>

                    <div v-if="availableTrainers.length" class="flex flex-wrap gap-2">
                        <button
                            v-for="trainer in availableTrainers"
                            :key="trainer.id"
                            type="button"
                            class="rounded-lg border px-3 py-2 text-sm transition"
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
                    <Label for="location">Locatie <span class="text-muted-foreground">(optioneel)</span></Label>
                    <Input id="location" v-model="form.location" placeholder="Sportpark De Vliert, veld 3" />
                    <InputError :message="form.errors.location" />
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

                    <Link href="/trainings" class="text-sm text-muted-foreground underline underline-offset-4 hover:text-foreground">
                        Annuleren
                    </Link>
                </div>
            </form>

            <div v-else class="mt-6 rounded-2xl border border-dashed border-border bg-card/50 p-10 text-center">
                <p class="font-medium">Eerst een groep nodig</p>
                <p class="mt-1 text-sm text-muted-foreground">Een training hoort altijd bij een groep.</p>
                <Link href="/groups/create" class="mt-3 inline-block text-sm font-medium text-primary underline underline-offset-4">
                    Groep aanmaken
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
