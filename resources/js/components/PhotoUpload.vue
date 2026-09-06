<script setup lang="ts">
import Avatar from '@/components/Avatar.vue';
import InputError from '@/components/InputError.vue';
import { router, useForm } from '@inertiajs/vue3';
import { Camera, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

/**
 * Een profielfoto kiezen of weghalen.
 *
 * Er is geen aparte opslaan-knop: je kiest een bestand en het staat er. Een
 * foto uploaden is één handeling, en er tussenuit stappen om nog een keer op
 * "opslaan" te drukken is precies het soort stap dat mensen halverwege laat
 * afhaken.
 */
const props = defineProps<{
    name: string;
    photo: string | null;
    /** Waar de foto heen gaat, bijvoorbeeld /players/12/photo. */
    action: string;
    size?: string;
    /** Alleen het rondje met een cameraknopje, voor in een lijst. */
    compact?: boolean;
}>();

const invoer = ref<HTMLInputElement | null>(null);
const form = useForm<{ photo: File | null }>({ photo: null });

const kies = (event: Event) => {
    const bestand = (event.target as HTMLInputElement).files?.[0];

    if (!bestand) {
        return;
    }

    form.photo = bestand;
    form.post(props.action, {
        preserveScroll: true,
        // Het veld leegmaken, anders kun je dezelfde foto niet nog eens kiezen
        // nadat je hem hebt weggehaald.
        onFinish: () => {
            form.reset();

            if (invoer.value) {
                invoer.value.value = '';
            }
        },
    });
};

const verwijder = () => {
    if (confirm(`De foto van ${props.name} verwijderen?`)) {
        router.delete(props.action, { preserveScroll: true });
    }
};
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
    </button>

    <div v-else class="flex items-center gap-4">
        <Avatar :name="name" :photo="photo" :size="size ?? 'size-16'" />

        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    class="inline-flex h-9 items-center gap-2 rounded-lg border border-border bg-card px-3 text-sm font-medium transition hover:border-primary disabled:opacity-60"
                    :disabled="form.processing"
                    @click="invoer?.click()"
                >
                    <Camera class="size-4" />
                    {{ photo ? 'Andere foto' : 'Foto kiezen' }}
                </button>

                <button
                    v-if="photo"
                    type="button"
                    class="inline-flex h-9 items-center gap-2 rounded-lg px-2 text-sm text-muted-foreground transition hover:text-destructive"
                    @click="verwijder"
                >
                    <Trash2 class="size-4" />
                    Weghalen
                </button>
            </div>

            <p class="mt-1 text-xs text-muted-foreground">png, jpg of webp, hooguit 5 MB. Wordt vierkant bijgesneden.</p>
            <InputError class="mt-1" :message="form.errors.photo" />
        </div>

        <input ref="invoer" type="file" accept="image/png,image/jpeg,image/webp" class="hidden" @change="kies" />
    </div>
</template>
