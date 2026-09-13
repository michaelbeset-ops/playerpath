<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import PhotoUpload from '@/components/PhotoUpload.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle, TriangleAlert } from 'lucide-vue-next';
import { computed } from 'vue';

interface Speler {
    id: number;
    first_name: string;
    last_name: string;
    name: string;
    photo: string | null;
    date_of_birth: string;
    position: string;
    shirt_number: number | null;
    is_active: boolean;
    groups: number[];
}

const props = defineProps<{
    player: Speler | null;
    positions: Record<string, string>;
    availableGroups: { id: number; name: string; age_category: string | null }[];
}>();

const bewerken = computed(() => props.player !== null);

const breadcrumbs = computed<BreadcrumbItem[]>(() =>
    bewerken.value
        ? [
              { title: 'Spelers', href: '/players' },
              { title: props.player!.first_name + ' ' + props.player!.last_name, href: '/players/' + props.player!.id },
              { title: 'Bewerken', href: '/players/' + props.player!.id + '/edit' },
          ]
        : [
              { title: 'Spelers', href: '/players' },
              { title: 'Nieuwe speler', href: '/players/create' },
          ],
);

const form = useForm({
    first_name: props.player?.first_name ?? '',
    last_name: props.player?.last_name ?? '',
    date_of_birth: props.player?.date_of_birth ?? '',
    position: props.player?.position ?? 'keeper',
    shirt_number: props.player?.shirt_number ?? ('' as number | ''),
    is_active: props.player?.is_active ?? true,
    groups: props.player?.groups ?? ([] as number[]),
});

const wisselGroep = (id: number) => {
    const positie = form.groups.indexOf(id);

    if (positie === -1) {
        form.groups.push(id);
    } else {
        form.groups.splice(positie, 1);
    }
};

const opslaan = () => {
    if (bewerken.value) {
        form.put('/players/' + props.player!.id);
    } else {
        form.post('/players');
    }
};
</script>

<template>
    <Head :title="bewerken ? 'Speler bewerken' : 'Speler toevoegen'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <form class="mx-auto w-full max-w-2xl p-4" @submit.prevent="opslaan">
            <h1 class="text-2xl font-semibold tracking-tight">
                {{ bewerken ? 'Speler bewerken' : 'Speler toevoegen' }}
            </h1>

            <!-- Zonder groep staat een speler nergens op een lijst. Niet
                 blokkeren (een speler zonder groep mag bestaan), wel bovenaan
                 zeggen wat de volgorde is en waar je heen moet. -->
            <div v-if="!availableGroups.length" class="mt-4 flex gap-3 rounded-xl border border-warning/40 bg-warning/10 p-4 text-sm">
                <TriangleAlert class="mt-0.5 size-5 shrink-0 text-warning" />
                <div class="min-w-0">
                    <p class="font-medium">Je hebt nog geen groep</p>
                    <p class="mt-1 text-muted-foreground">
                        Een speler hoort in een groep, want daar plan je trainingen voor. Maak eerst een groep aan; daarna zet je deze speler
                        erin.
                    </p>
                    <Link href="/groups/create" class="mt-2 inline-flex min-h-11 items-center font-medium text-primary underline underline-offset-4">
                        Eerst een groep aanmaken
                    </Link>
                </div>
            </div>

            <div class="mt-6 space-y-5 rounded-xl border border-border bg-card p-5 shadow-sm">
                <!-- De foto hoort bij het bewerken van een speler, niet bij het
                     bekijken. Hij gaat wel meteen weg als je hem kiest: uploaden
                     is één handeling en wacht niet op "Wijzigingen opslaan".
                     Bij een nieuwe speler kan het nog niet - er is nog niets om
                     de foto aan te hangen. -->
                <div v-if="bewerken" class="grid gap-2">
                    <Label>Pasfoto</Label>
                    <p class="text-xs text-muted-foreground">
                        Deze foto staat op de spelerskaart van {{ player!.first_name }}, ook op een gedeelde kaart.
                    </p>
                    <PhotoUpload class="mt-1" :name="player!.name" :photo="player!.photo" :action="'/players/' + player!.id + '/photo'" />
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="first_name">Voornaam</Label>
                        <Input id="first_name" v-model="form.first_name" required autofocus autocomplete="off" />
                        <InputError :message="form.errors.first_name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="last_name">Achternaam</Label>
                        <Input id="last_name" v-model="form.last_name" required autocomplete="off" />
                        <InputError :message="form.errors.last_name" />
                    </div>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="date_of_birth">Geboortedatum</Label>
                        <Input id="date_of_birth" v-model="form.date_of_birth" type="date" required />
                        <p class="text-xs text-muted-foreground">De leeftijd wordt hieruit berekend, dus die klopt vanzelf.</p>
                        <InputError :message="form.errors.date_of_birth" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="position">Positie</Label>
                        <select
                            id="position"
                            v-model="form.position"
                            class="min-h-11 rounded-lg border border-input bg-background px-3 text-sm outline-none focus:border-primary"
                        >
                            <option v-for="(label, waarde) in positions" :key="waarde" :value="waarde">{{ label }}</option>
                        </select>
                        <p class="text-xs text-muted-foreground">Bepaalt op welke categorieën de speler beoordeeld wordt.</p>
                        <InputError :message="form.errors.position" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="shirt_number">Rugnummer <span class="text-muted-foreground">(optioneel)</span></Label>
                    <Input id="shirt_number" v-model="form.shirt_number" type="number" inputmode="numeric" min="1" max="99" class="w-24" placeholder="10" />
                    <p class="text-xs text-muted-foreground">Staat groot op de foto van de kaart. De speler of zijn ouders kunnen het zelf ook zetten.</p>
                    <InputError :message="form.errors.shirt_number" />
                </div>

                <div class="grid gap-2">
                    <Label>Groepen</Label>
                    <p class="text-xs text-muted-foreground">
                        Indelen doe je zelf; een speler kan in meerdere groepen zitten en hoeft niet bij zijn leeftijd te passen.
                    </p>

                    <div v-if="availableGroups.length" class="mt-1 flex flex-wrap gap-2">
                        <button
                            v-for="groep in availableGroups"
                            :key="groep.id"
                            type="button"
                            class="min-h-11 rounded-lg border px-3 py-2 text-sm transition"
                            :class="
                                form.groups.includes(groep.id)
                                    ? 'border-primary bg-primary/10 font-medium text-primary'
                                    : 'border-border bg-background text-muted-foreground hover:border-primary'
                            "
                            :aria-pressed="form.groups.includes(groep.id)"
                            @click="wisselGroep(groep.id)"
                        >
                            {{ groep.name }}
                            <span v-if="groep.age_category" class="text-xs opacity-70">{{ groep.age_category }}</span>
                        </button>
                    </div>

                    <p v-else class="mt-1 text-sm text-muted-foreground">
                        Deze school heeft nog geen groepen.
                        <Link href="/groups/create" class="font-medium text-primary underline underline-offset-4">Maak er een aan</Link>.
                    </p>

                    <InputError :message="form.errors.groups" />
                </div>

                <label class="flex items-center gap-3 rounded-lg border border-border p-3">
                    <input v-model="form.is_active" type="checkbox" class="size-4 accent-[hsl(var(--primary))]" />
                    <span class="text-sm">
                        <span class="font-medium">Actieve speler</span>
                        <span class="block text-xs text-muted-foreground">Niet-actieve spelers blijven bewaard, maar tellen niet mee.</span>
                    </span>
                </label>
            </div>

            <div class="mt-4 flex items-center gap-3">
                <Button type="submit" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                    {{ bewerken ? 'Wijzigingen opslaan' : 'Speler toevoegen' }}
                </Button>

                <Link
                    :href="bewerken ? '/players/' + player!.id : '/players'"
                    class="inline-flex min-h-11 items-center text-sm text-muted-foreground underline underline-offset-4 hover:text-foreground"
                >
                    Annuleren
                </Link>
            </div>
        </form>
    </AppLayout>
</template>
