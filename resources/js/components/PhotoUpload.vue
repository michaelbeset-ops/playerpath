<script setup lang="ts">
import Avatar from '@/components/Avatar.vue';
import InputError from '@/components/InputError.vue';
import PhotoCrop from '@/components/PhotoCrop.vue';
import { router, useForm } from '@inertiajs/vue3';
import { Camera, ImageIcon, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

/**
 * Een profielfoto maken, kiezen of weghalen.
 *
 * Twee ingangen op een telefoon: de camera (een foto maken, hier en nu) en de
 * galerij. Wat je kiest snij je eerst vierkant uit (PhotoCrop), en daarna
 * gaat hij meteen weg — er is geen aparte opslaan-knop. Een foto uploaden is
 * één handeling, en er tussenuit stappen om nog een keer op "opslaan" te
 * drukken is precies het soort stap dat mensen halverwege laat afhaken.
 */
const props = defineProps<{
    name: string;
    photo: string | null;
    /** Waar de foto heen gaat, bijvoorbeeld /players/12/photo. */
    action: string;
    size?: string;
    /** Alleen het rondje met een cameraknopje, voor in een lijst. */
    compact?: boolean;
    /** Wat er op het kaartje in het uitsnijvenster staat. */
    kaart?: { first_name: string; last_name: string; overall: number | null; position: string } | null;
}>();

/** De uitsnede tijdens het schuiven, zodat de kaart op de pagina meebeweegt. */
const emit = defineEmits<{ preview: [url: string | null] }>();

const invoer = ref<HTMLInputElement | null>(null);
const camera = ref<HTMLInputElement | null>(null);
const form = useForm<{ photo: File | null }>({ photo: null });

/** Het gekozen bestand, zolang het nog uitgesneden wordt. */
const teKnippen = ref<File | null>(null);

// Een cameraknop heeft alleen zin op een apparaat met een camera die je
// vasthoudt; op een laptop opent hij dezelfde kiezer nog een keer.
const heeftCamera = typeof navigator !== 'undefined' && navigator.maxTouchPoints > 0;

const kies = (event: Event) => {
    const bestand = (event.target as HTMLInputElement).files?.[0];

    // Het veld leegmaken, anders kun je dezelfde foto niet nog eens kiezen
    // nadat je hem hebt weggehaald of het uitsnijden hebt geannuleerd.
    (event.target as HTMLInputElement).value = '';

    if (bestand) {
        teKnippen.value = bestand;
    }
};

const verstuur = (blob: Blob) => {
    teKnippen.value = null;
    form.photo = new File([blob], 'foto.jpg', { type: 'image/jpeg' });
    form.post(props.action, {
        preserveScroll: true,
        // De echte foto staat nu in de props; het voorbeeld mag weg.
        onFinish: () => {
            form.reset();
            emit('preview', null);
        },
    });
};

const verwijder = () => {
    if (confirm(`De foto van ${props.name} verwijderen?`)) {
        router.delete(props.action, { preserveScroll: true });
    }
};

/** Van buitenaf de kiezer openen, bijvoorbeeld vanaf de knop op de kaart. */
defineExpose({ open: () => invoer.value?.click() });
</script>

<template>
    <!-- Compact: het rondje is zelf de knop. In een lijst met twintig ouders
         is een uitleg per regel ruis. -->
    <button
        v-if="compact"
        type="button"
        class="group relative shrink-0 rounded-full"
        :aria-label="'Foto van ' + name + ' wijzigen'"
        :disabled="form.processing"
        @click="invoer?.click()"
    >
        <Avatar :name="name" :photo="photo" :size="size ?? 'size-11'" />
        <span
            class="absolute inset-0 flex items-center justify-center rounded-full bg-foreground/60 text-background opacity-0 transition group-hover:opacity-100 group-focus-visible:opacity-100"
        >
            <Camera class="size-4" />
        </span>
        <input ref="invoer" type="file" accept="image/png,image/jpeg,image/webp" class="hidden" @change="kies" />
        <PhotoCrop v-if="teKnippen" :file="teKnippen" :name="name" :kaart="kaart" @done="verstuur" @cancel="teKnippen = null" @preview="emit('preview', $event)" />
    </button>

    <div v-else class="flex items-center gap-4">
        <Avatar :name="name" :photo="photo" :size="size ?? 'size-16'" />

        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <button
                    v-if="heeftCamera"
                    type="button"
                    class="inline-flex min-h-11 items-center gap-2 rounded-lg bg-primary px-3 text-sm font-semibold text-primary-foreground transition hover:opacity-90 disabled:opacity-60"
                    :disabled="form.processing"
                    @click="camera?.click()"
                >
                    <Camera class="size-4" />
                    Foto maken
                </button>

                <button
                    type="button"
                    class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-border bg-card px-3 text-sm font-medium transition hover:border-primary disabled:opacity-60"
                    :disabled="form.processing"
                    @click="invoer?.click()"
                >
                    <ImageIcon v-if="heeftCamera" class="size-4" />
                    <Camera v-else class="size-4" />
                    {{ heeftCamera ? 'Uit galerij' : photo ? 'Andere foto' : 'Foto kiezen' }}
                </button>

                <button
                    v-if="photo"
                    type="button"
                    class="inline-flex min-h-11 items-center gap-2 rounded-lg px-2 text-sm text-muted-foreground transition hover:text-destructive"
                    @click="verwijder"
                >
                    <Trash2 class="size-4" />
                    Weghalen
                </button>
            </div>

            <p class="mt-1 text-xs text-muted-foreground">
                {{ form.processing ? 'Bezig met opslaan…' : 'Je snijdt hem daarna vierkant uit. png, jpg of webp, hooguit 5 MB.' }}
            </p>
            <InputError class="mt-1" :message="form.errors.photo" />
        </div>

        <input ref="invoer" type="file" accept="image/png,image/jpeg,image/webp" class="hidden" @change="kies" />
        <input ref="camera" type="file" accept="image/*" capture="user" class="hidden" @change="kies" />
        <PhotoCrop v-if="teKnippen" :file="teKnippen" :name="name" :kaart="kaart" @done="verstuur" @cancel="teKnippen = null" @preview="emit('preview', $event)" />
    </div>
</template>
