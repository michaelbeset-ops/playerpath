<script setup lang="ts">
import { useGrading } from '@/lib/grade';
import { type SharedData } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

/**
 * Eén vriendelijke regel bij het eerste bezoek, voor een ouder of speler.
 *
 * Zij krijgen geen wizard en geen rondleiding: als een ouder zijn eigen
 * dashboard niet meteen snapt, is een tour het verkeerde antwoord op het
 * verkeerde probleem. Wat wél helpt is één zin die zegt wat hij hier vindt.
 *
 * Na sluiten komt hij niet terug - dat wordt per gebruiker onthouden, niet in
 * de browser: op je telefoon opnieuw hetzelfde regeltje krijgen leest als een
 * app die niet oplet.
 */
const props = defineProps<{ role: 'ouder' | 'speler'; name?: string | null }>();

const page = usePage<SharedData>();

const zichtbaar = ref(page.props.onboarding?.intro === true);

const tekst = computed(() =>
    props.role === 'speler'
        ? inzetKaart.value
          ? 'Hier zie je je eigen spelerskaart. Na elke training krijg je punten voor er zijn en je best doen, en je kaart groeit mee.'
          : 'Hier zie je je eigen spelerskaart en hoe je vooruitgaat. Hij verandert na elk rapport van je trainer.'
        : 'Hier zie je wanneer de trainingen zijn, hoe het met je kind gaat en wat er nog openstaat. Meer hoef je niet in te stellen.',
);

const sluit = () => {
    zichtbaar.value = false;
    router.post('/onboarding/welkom/gezien', {}, { preserveScroll: true, preserveState: true });
};

// De inzetkaart groeit door inzet, niet door rapporten.
const { inzet: inzetKaart } = useGrading();
</script>

<template>
    <div v-if="zichtbaar" class="flex items-start gap-3 rounded-xl border border-primary/30 bg-primary/5 p-3">
        <p class="min-w-0 flex-1 text-sm">
            <span class="font-medium">Welkom{{ name ? ', ' + name : '' }}. </span>
            <span class="text-muted-foreground">{{ tekst }}</span>
        </p>

        <button
            type="button"
            class="-my-2 -mr-1 flex size-11 shrink-0 items-center justify-center rounded-lg text-muted-foreground transition hover:bg-card"
            aria-label="Sluiten"
            @click="sluit"
        >
            <X class="size-4" />
        </button>
    </div>
</template>
