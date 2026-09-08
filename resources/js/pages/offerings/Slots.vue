<script setup lang="ts">
import FlashMessage from '@/components/FlashMessage.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { MapPin, Plus, UserCog, X } from 'lucide-vue-next';
import { computed } from 'vue';

interface Moment {
    id: number;
    day: string;
    day_label: string;
    time: string;
    trainer: string | null;
    location: string | null;
    player: string | null;
    player_id: number | null;
    reserved: boolean;
    has_passed: boolean;
}

const props = defineProps<{
    product: { id: number; name: string; type: string; location: string | null };
    slots: Moment[];
    trainers: { id: number; name: string }[];
    locations: { id: number; name: string }[];
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Aanbod', href: '/aanbod' },
    { title: props.product.name, href: '/aanbod/' + props.product.id + '/momenten' },
]);

const form = useForm({
    date: '',
    starts_at: '16:00',
    ends_at: '17:00',
    user_id: props.trainers[0]?.id ?? null,
    location_id: null as number | null,
    repeat_weeks: 1,
});

const voegToe = () =>
    form.post('/aanbod/' + props.product.id + '/momenten', {
        preserveScroll: true,
        onSuccess: () => form.reset('date'),
    });

const haalWeg = (moment: Moment) => {
    const vraag = moment.player
        ? `De boeking van ${moment.player} terugdraaien? Het moment komt weer vrij; de rekening blijft staan.`
        : 'Dit moment weghalen?';

    if (confirm(vraag)) {
        router.delete(`/aanbod/${props.product.id}/momenten/${moment.id}`, { preserveScroll: true });
    }
};

// Per dag gegroepeerd; een trainer beschrijft zijn agenda per dag, niet als één
// lange rij losse uren.
const dagen = computed(() => {
    const uit: { day: string; label: string; items: Moment[] }[] = [];

    for (const moment of props.slots) {
        const laatste = uit[uit.length - 1];

        if (laatste?.day === moment.day) {
            laatste.items.push(moment);
        } else {
            uit.push({ day: moment.day, label: moment.day_label, items: [moment] });
        }
    }

    return uit;
});

const vrij = computed(() => props.slots.filter((s) => !s.player_id && !s.reserved && !s.has_passed).length);
const geboekt = computed(() => props.slots.filter((s) => s.player_id).length);
</script>

<template>
    <Head :title="'Momenten · ' + product.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-3xl p-4">
            <FlashMessage />

            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">{{ product.type }}</p>
                    <h1 class="text-2xl font-semibold tracking-tight">{{ product.name }}</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        <span class="tabular">{{ vrij }}</span> vrij · <span class="tabular">{{ geboekt }}</span> geboekt
                    </p>
                </div>

                <Link
                    :href="'/aanbod/' + product.id + '/edit'"
                    class="inline-flex h-11 items-center rounded-xl border border-border bg-card px-4 text-sm font-medium shadow-sm transition hover:border-primary"
                >
                    Bewerken
                </Link>
            </div>

            <!-- Momenten neerzetten: één dag, of een reeks weken -->
            <form class="mt-5 space-y-4 rounded-xl border border-border bg-card p-4 shadow-sm sm:p-5" @submit.prevent="voegToe">
                <p class="font-medium">Wanneer kun je?</p>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="date">Datum</Label>
                        <input
                            id="date"
                            v-model="form.date"
                            type="date"
                            required
                            class="h-11 w-full rounded-lg border border-input bg-background px-3 text-base outline-none focus:border-primary sm:h-10 sm:text-sm"
                        />
                        <InputError :message="form.errors.date" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="user_id">Trainer</Label>
                        <select
                            id="user_id"
                            v-model="form.user_id"
                            class="h-11 w-full rounded-lg border border-input bg-background px-3 text-base outline-none focus:border-primary sm:h-10 sm:text-sm"
                        >
                            <option :value="null">Nog niet bekend</option>
                            <option v-for="trainer in trainers" :key="trainer.id" :value="trainer.id">{{ trainer.name }}</option>
                        </select>
                        <InputError :message="form.errors.user_id" />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="grid gap-2">
                        <Label for="starts_at">Van</Label>
                        <input
                            id="starts_at"
                            v-model="form.starts_at"
                            type="time"
                            class="h-11 w-full rounded-lg border border-input bg-background px-3 text-base outline-none focus:border-primary sm:h-10 sm:text-sm"
                        />
                        <InputError :message="form.errors.starts_at" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="ends_at">Tot</Label>
                        <input
                            id="ends_at"
                            v-model="form.ends_at"
                            type="time"
                            class="h-11 w-full rounded-lg border border-input bg-background px-3 text-base outline-none focus:border-primary sm:h-10 sm:text-sm"
                        />
                        <InputError :message="form.errors.ends_at" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="repeat_weeks">Herhalen</Label>
                        <div class="flex items-center gap-2">
                            <Input
                                id="repeat_weeks"
                                v-model.number="form.repeat_weeks"
                                type="number"
                                min="1"
                                max="26"
                                class="h-11 max-w-20 sm:h-10"
                            />
                            <span class="text-sm text-muted-foreground">weken</span>
                        </div>
                        <InputError :message="form.errors.repeat_weeks" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="location_id">Locatie <span class="text-muted-foreground">(optioneel)</span></Label>
                    <select
                        id="location_id"
                        v-model="form.location_id"
                        class="h-11 w-full rounded-lg border border-input bg-background px-3 text-base outline-none focus:border-primary sm:h-10 sm:text-sm"
                    >
                        <option :value="null">Zoals bij het aanbod</option>
                        <option v-for="locatie in locations" :key="locatie.id" :value="locatie.id">{{ locatie.name }}</option>
                    </select>
                    <InputError :message="form.errors.location_id" />
                </div>

                <Button type="submit" class="h-11 w-full sm:w-auto" :disabled="form.processing">
                    <Plus class="mr-2 size-4" />
                    Moment toevoegen
                </Button>
            </form>

            <!-- Wat er staat -->
            <div v-if="dagen.length" class="mt-6 space-y-4">
                <section v-for="dag in dagen" :key="dag.day">
                    <h2 class="text-sm font-semibold first-letter:uppercase">{{ dag.label }}</h2>

                    <div class="mt-2 overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                        <div
                            v-for="(moment, index) in dag.items"
                            :key="moment.id"
                            class="flex items-center gap-3 p-3 sm:p-4"
                            :class="[index > 0 ? 'border-t border-border' : '', moment.has_passed ? 'opacity-60' : '']"
                        >
                            <span class="tabular w-24 shrink-0 text-sm font-semibold">{{ moment.time }}</span>

                            <div class="min-w-0 flex-1">
                                <p v-if="moment.player" class="font-medium">{{ moment.player }}</p>
                                <p v-else-if="moment.reserved" class="font-medium text-warning">Gereserveerd</p>
                                <p v-else class="font-medium text-muted-foreground">Vrij</p>

                                <p v-if="moment.trainer" class="mt-0.5 flex items-center gap-1.5 text-xs text-muted-foreground">
                                    <UserCog class="size-3.5 shrink-0" />
                                    {{ moment.trainer }}
                                </p>
                                <p v-if="moment.location" class="mt-0.5 flex items-center gap-1.5 text-xs text-muted-foreground">
                                    <MapPin class="size-3.5 shrink-0" />
                                    {{ moment.location }}
                                </p>
                            </div>

                            <button
                                type="button"
                                class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-border text-muted-foreground transition hover:border-destructive hover:text-destructive"
                                :aria-label="moment.player ? 'Boeking terugdraaien' : 'Moment weghalen'"
                                @click="haalWeg(moment)"
                            >
                                <X class="size-4" />
                            </button>
                        </div>
                    </div>
                </section>
            </div>

            <div v-else class="mt-6 rounded-2xl border border-dashed border-border bg-card/50 p-8 text-center">
                <p class="font-medium">Nog geen momenten</p>
                <p class="mt-1 text-sm text-muted-foreground">Zet hierboven neer wanneer je kunt; ouders kiezen daaruit in de shop.</p>
            </div>
        </div>
    </AppLayout>
</template>
