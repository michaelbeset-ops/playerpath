<script setup lang="ts">
import type { Kaart } from '@/components/PlayerCardVisual.vue';
import { deelKaartAlsAfbeelding, slaKaartOp } from '@/lib/cardImage';
import { Check, Copy, Ghost, ImageDown, Share2 } from 'lucide-vue-next';
import { ref } from 'vue';

/**
 * De kaart delen: via het deelmenu, als opgeslagen afbeelding, of naar
 * Snapchat.
 *
 * Snapchat pakt een afbeelding uit het deelmenu op veel telefoons niet goed
 * op. Daarom een eigen weg: de afbeelding opslaan, zeggen dat hij in je
 * foto's staat, en een knop die Snapchat opent; daar kies je hem uit je
 * galerij. Omslachtiger dan één tik, maar het werkt overal.
 */
const props = defineProps<{
    card: Kaart;
    link: string | null;
    sticker?: string | null;
}>();

const bezig = ref<string | null>(null);
const melding = ref<string | null>(null);
const gekopieerd = ref(false);
const snapchatKlaar = ref(false);

const deel = async () => {
    bezig.value = 'deel';
    melding.value = null;
    snapchatKlaar.value = false;

    const uitkomst = await deelKaartAlsAfbeelding(props.card, props.link, props.sticker ?? null);

    bezig.value = null;
    melding.value = {
        gedeeld: null,
        geannuleerd: null,
        gedownload: 'De afbeelding is opgeslagen. Deel hem vanuit je foto’s of downloads.',
        mislukt: 'Het maken van de afbeelding is niet gelukt. Probeer het nog eens.',
    }[uitkomst];
};

const bewaar = async () => {
    bezig.value = 'bewaar';
    melding.value = null;
    snapchatKlaar.value = false;

    const uitkomst = await slaKaartOp(props.card, props.link, props.sticker ?? null);

    bezig.value = null;
    melding.value = uitkomst === 'gedownload' ? 'Kaart opgeslagen in je foto’s of downloads.' : 'Het opslaan is niet gelukt. Probeer het nog eens.';
};

const naarSnapchat = async () => {
    bezig.value = 'snapchat';
    melding.value = null;

    const uitkomst = await slaKaartOp(props.card, props.link, props.sticker ?? null);

    bezig.value = null;

    if (uitkomst !== 'gedownload') {
        melding.value = 'Het opslaan is niet gelukt. Probeer het nog eens.';
        return;
    }

    melding.value = 'Kaart opgeslagen in je foto’s! Open Snapchat en kies de foto uit je galerij.';
    snapchatKlaar.value = true;
};

const kopieer = async () => {
    if (!props.link) {
        return;
    }

    await navigator.clipboard.writeText(props.link);
    gekopieerd.value = true;
    setTimeout(() => (gekopieerd.value = false), 2000);
};
</script>

<template>
    <div>
        <div class="flex flex-wrap items-center gap-2">
            <button
                type="button"
                class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground transition hover:opacity-90 disabled:opacity-60"
                :disabled="bezig !== null"
                @click="deel"
            >
                <Share2 class="size-4" :class="{ 'animate-pulse': bezig === 'deel' }" />
                Delen
            </button>

            <button
                type="button"
                class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-border px-4 text-sm font-medium transition hover:border-primary disabled:opacity-60"
                :disabled="bezig !== null"
                @click="bewaar"
            >
                <ImageDown class="size-4" :class="{ 'animate-pulse': bezig === 'bewaar' }" />
                Opslaan als afbeelding
            </button>

            <button
                type="button"
                class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-border px-4 text-sm font-medium transition hover:border-primary disabled:opacity-60"
                :disabled="bezig !== null"
                @click="naarSnapchat"
            >
                <Ghost class="size-4" :class="{ 'animate-pulse': bezig === 'snapchat' }" />
                Delen naar Snapchat
            </button>

            <button
                v-if="link"
                type="button"
                class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-border px-4 text-sm font-medium transition hover:border-primary"
                @click="kopieer"
            >
                <Check v-if="gekopieerd" class="size-4 text-primary" />
                <Copy v-else class="size-4" />
                {{ gekopieerd ? 'Link gekopieerd' : 'Link kopiëren' }}
            </button>
        </div>

        <div v-if="melding" class="mt-3 rounded-xl border border-primary/30 bg-primary/5 p-3 text-sm" role="status">
            <p>{{ melding }}</p>
            <a
                v-if="snapchatKlaar"
                href="snapchat://"
                class="mt-2 inline-flex min-h-11 items-center gap-2 rounded-xl bg-[#FFFC00] px-4 text-sm font-semibold text-black"
            >
                <Ghost class="size-4" />
                Open Snapchat
            </a>
        </div>
    </div>
</template>
