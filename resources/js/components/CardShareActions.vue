<script setup lang="ts">
import type { Kaart } from '@/components/PlayerCardVisual.vue';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { deelKaartAlsAfbeelding, slaKaartOp } from '@/lib/cardImage';
import { ChevronDown, Copy, Ghost, ImageDown, Share2, X } from 'lucide-vue-next';
import { ref } from 'vue';

/**
 * De kaart delen: één knop, met de manieren eronder.
 *
 * Vier knoppen naast elkaar lieten je kiezen voordat je wist wat het
 * verschil was. Nu is het "Delen", en daaronder: via het deelmenu van je
 * telefoon (WhatsApp, Instagram), als afbeelding opslaan, naar Snapchat, of
 * de link kopiëren.
 *
 * Snapchat pakt een afbeelding uit het deelmenu op veel telefoons niet goed
 * op. Daarom een eigen weg: de afbeelding opslaan, zeggen dat hij in je
 * foto's staat, en een knop die Snapchat opent; daar kies je hem uit je
 * galerij.
 */
const props = defineProps<{
    card: Kaart;
    link: string | null;
    sticker?: string | null;
}>();

const bezig = ref(false);
const melding = ref<string | null>(null);
const snapchatKlaar = ref(false);

const start = () => {
    bezig.value = true;
    melding.value = null;
    snapchatKlaar.value = false;
};

const deel = async () => {
    start();
    const uitkomst = await deelKaartAlsAfbeelding(props.card, props.link, props.sticker ?? null);
    bezig.value = false;
    melding.value = {
        gedeeld: null,
        geannuleerd: null,
        gedownload: 'De afbeelding is opgeslagen. Deel hem vanuit je foto’s of downloads.',
        mislukt: 'Het maken van de afbeelding is niet gelukt. Probeer het nog eens.',
    }[uitkomst];
};

const bewaar = async () => {
    start();
    const uitkomst = await slaKaartOp(props.card, props.link, props.sticker ?? null);
    bezig.value = false;
    melding.value = uitkomst === 'gedownload' ? 'Kaart opgeslagen in je foto’s of downloads.' : 'Het opslaan is niet gelukt. Probeer het nog eens.';
};

const naarSnapchat = async () => {
    start();
    const uitkomst = await slaKaartOp(props.card, props.link, props.sticker ?? null);
    bezig.value = false;

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
    snapchatKlaar.value = false;
    melding.value = 'De link is gekopieerd.';
};
</script>

<template>
    <div>
        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <button
                    type="button"
                    class="inline-flex h-11 items-center gap-2 rounded-xl bg-primary px-5 text-sm font-semibold text-primary-foreground transition hover:opacity-90 disabled:opacity-60"
                    :disabled="bezig"
                >
                    <Share2 class="size-4" :class="{ 'animate-pulse': bezig }" />
                    {{ bezig ? 'Even geduld…' : 'Delen' }}
                    <ChevronDown class="size-4 opacity-80" />
                </button>
            </DropdownMenuTrigger>

            <DropdownMenuContent align="start" class="w-64">
                <DropdownMenuItem class="min-h-11 cursor-pointer gap-3" @click="deel">
                    <Share2 class="size-4" />
                    <span>
                        <span class="block font-medium">Delen via…</span>
                        <span class="block text-xs text-muted-foreground">WhatsApp, Instagram en meer</span>
                    </span>
                </DropdownMenuItem>
                <DropdownMenuItem class="min-h-11 cursor-pointer gap-3" @click="naarSnapchat">
                    <Ghost class="size-4" />
                    <span class="font-medium">Naar Snapchat</span>
                </DropdownMenuItem>
                <DropdownMenuItem class="min-h-11 cursor-pointer gap-3" @click="bewaar">
                    <ImageDown class="size-4" />
                    <span class="font-medium">Opslaan als afbeelding</span>
                </DropdownMenuItem>
                <template v-if="link">
                    <DropdownMenuSeparator />
                    <DropdownMenuItem class="min-h-11 cursor-pointer gap-3" @click="kopieer">
                        <Copy class="size-4" />
                        <span class="font-medium">Link kopiëren</span>
                    </DropdownMenuItem>
                </template>
            </DropdownMenuContent>
        </DropdownMenu>

        <div v-if="melding" class="mt-3 flex items-start gap-3 rounded-xl border border-primary/30 bg-primary/5 p-3 text-sm" role="status">
            <div class="min-w-0 flex-1">
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
            <button
                type="button"
                class="-m-1 flex size-9 shrink-0 items-center justify-center rounded-lg text-muted-foreground hover:text-foreground"
                aria-label="Sluiten"
                @click="melding = null"
            >
                <X class="size-4" />
            </button>
        </div>
    </div>
</template>
