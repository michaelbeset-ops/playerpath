<script setup lang="ts">
import Avatar from '@/components/Avatar.vue';
import PhotoUpload from '@/components/PhotoUpload.vue';
import type { Kaart } from '@/components/PlayerCardVisual.vue';
import { router, usePage } from '@inertiajs/vue3';
import { Camera, Check, ChevronUp, Minus, Pencil, Plus, Shirt } from 'lucide-vue-next';
import { computed, nextTick, onUnmounted, ref, watch } from 'vue';

/**
 * Foto en rugnummer: de kaart eigen maken.
 *
 * Zolang een van de twee ontbreekt, valt het blok op en staat er hoeveel er
 * nog moet. Staan ze er allebei, dan klapt het in tot één regel met de foto
 * en het nummer, en "Aanpassen". Een formulier dat na het instellen blijft
 * staan is een formulier waar je elke keer omheen scrolt.
 *
 * Het rugnummer slaat zichzelf op: met de plus en min, of door te typen.
 * Een aparte opslaan-knop naast één getal is een stap die niemand verwacht.
 */
const props = defineProps<{
    player: { id: number; name: string; photo: string | null };
    card: Kaart;
}>();

const emit = defineEmits<{ preview: [url: string | null]; uploaded: [eerste: boolean] }>();

const page = usePage();

const heeftFoto = computed(() => !!props.player.photo);
const heeftNummer = computed(() => props.card.shirt_number !== null && props.card.shirt_number !== undefined);
const klaar = computed(() => heeftFoto.value && heeftNummer.value);
const aantalKlaar = computed(() => Number(heeftFoto.value) + Number(heeftNummer.value));

// Ingeklapt als alles er al staat bij het openen. Wordt het tijdens het
// bezoek af, dan blijft het open: dichtklappen terwijl je kijkt is schrikken.
const open = ref(!klaar.value);

const fotoKiezer = ref<InstanceType<typeof PhotoUpload> | null>(null);

// ---- Het rugnummer ----
const nummer = ref<number | null>(props.card.shirt_number ?? null);
const bezig = ref(false);
const opgeslagen = ref(false);
const fout = computed(() => (page.props.errors as Record<string, string> | undefined)?.shirt_number ?? null);

watch(
    () => props.card.shirt_number,
    (nieuw) => {
        if (!bezig.value) {
            nummer.value = nieuw ?? null;
        }
    },
);

let timer: ReturnType<typeof setTimeout> | null = null;
let klaarTimer: ReturnType<typeof setTimeout> | null = null;

const bewaar = () => {
    if (timer) {
        clearTimeout(timer);
    }

    timer = setTimeout(() => {
        const waarde = nummer.value === null || Number.isNaN(nummer.value) ? null : nummer.value;

        if (waarde === (props.card.shirt_number ?? null)) {
            return;
        }

        bezig.value = true;
        router.patch(
            '/players/' + props.player.id + '/rugnummer',
            { shirt_number: waarde },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    opgeslagen.value = true;
                    if (klaarTimer) {
                        clearTimeout(klaarTimer);
                    }
                    klaarTimer = setTimeout(() => (opgeslagen.value = false), 1800);
                },
                onFinish: () => (bezig.value = false),
            },
        );
    }, 600);
};

const stap = (delta: number) => {
    const huidig = nummer.value ?? (delta > 0 ? 0 : 100);
    nummer.value = Math.min(99, Math.max(1, huidig + delta));
    bewaar();
};

const typ = (event: Event) => {
    const tekst = (event.target as HTMLInputElement).value.replace(/\D/g, '').slice(0, 2);
    nummer.value = tekst === '' ? null : Math.min(99, Math.max(1, Number(tekst)));
    bewaar();
};

const wis = () => {
    nummer.value = null;
    bewaar();
};

onUnmounted(() => {
    if (timer) {
        clearTimeout(timer);
    }
    if (klaarTimer) {
        clearTimeout(klaarTimer);
    }
});

/** Voor de knop "Foto toevoegen" op de kaart: openklappen en de kiezer openen. */
const openFoto = async () => {
    open.value = true;
    await nextTick();
    document.getElementById('foto')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    fotoKiezer.value?.open();
};

defineExpose({ open: openFoto });
</script>

<template>
    <div id="foto" class="rounded-xl border bg-card shadow-sm transition" :class="klaar ? 'border-border' : 'border-primary/50'">
        <!-- Ingeklapt: één regel met wat er op de kaart staat -->
        <button
            v-if="!open"
            type="button"
            class="flex min-h-16 w-full items-center gap-3 px-4 py-3 text-left transition hover:bg-secondary/40"
            @click="open = true"
        >
            <!-- Het nummer als badge op de foto, zoals op een shirt: dan past
                 de regel op een telefoon zonder dat de titel afbreekt. -->
            <span class="relative shrink-0">
                <Avatar :name="player.name" :photo="player.photo" size="size-12" />
                <span
                    class="tabular absolute -bottom-1 -right-1.5 flex h-5 min-w-5 items-center justify-center rounded-full border-2 border-card bg-foreground px-1 text-[10px] font-extrabold text-background"
                    >{{ card.shirt_number }}</span
                >
            </span>
            <span class="min-w-0 flex-1">
                <span class="block whitespace-nowrap text-sm font-semibold leading-tight">Foto en nummer</span>
                <span class="mt-0.5 block text-xs text-muted-foreground">Staan op de kaart</span>
            </span>
            <span class="inline-flex min-h-11 shrink-0 items-center gap-1.5 rounded-lg border border-border px-3 text-sm font-medium">
                <Pencil class="size-3.5" />
                Aanpassen
            </span>
        </button>

        <!-- Open: de twee dingen die de kaart van jou maken -->
        <div v-else class="p-5">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="font-medium">{{ klaar ? 'Foto en rugnummer' : 'Maak de kaart af' }}</p>
                    <p class="mt-0.5 text-sm text-muted-foreground">
                        {{ klaar ? 'Dit staat op de kaart, ook op een gedeelde kaart.' : 'Een foto en een rugnummer maken de kaart echt van ' + card.first_name + '.' }}
                    </p>
                </div>
                <span v-if="!klaar" class="tabular shrink-0 rounded-full bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary">{{ aantalKlaar }} van 2</span>
                <button
                    v-else
                    type="button"
                    class="-m-1 flex size-11 shrink-0 items-center justify-center rounded-lg text-muted-foreground transition hover:bg-secondary hover:text-foreground"
                    aria-label="Inklappen"
                    @click="open = false"
                >
                    <ChevronUp class="size-5" />
                </button>
            </div>

            <!-- De foto. De stand staat in het kopje, niet in een kolom ernaast:
                 op een telefoon heeft de kiezer de volle breedte nodig. -->
            <div class="mt-4">
                <p class="flex items-center gap-2 text-sm font-medium">
                    <Camera class="size-4 text-muted-foreground" />
                    Foto
                    <span v-if="heeftFoto" class="inline-flex items-center gap-1 text-xs font-semibold text-primary"><Check class="size-3.5" /> staat erop</span>
                </p>
                <PhotoUpload
                    ref="fotoKiezer"
                    class="mt-2"
                    :name="player.name"
                    :photo="player.photo"
                    size="size-14"
                    :action="'/players/' + player.id + '/photo'"
                    :kaart="{ first_name: card.first_name, last_name: card.last_name, overall: card.overall, position: card.position }"
                    @preview="emit('preview', $event)"
                    @uploaded="emit('uploaded', $event)"
                />
            </div>

            <!-- Het rugnummer -->
            <div class="mt-5 border-t border-border pt-5">
                <div>
                    <label for="rugnummer" class="flex items-center gap-2 text-sm font-medium">
                        <Shirt class="size-4 text-muted-foreground" />
                        Rugnummer
                        <span v-if="heeftNummer" class="inline-flex items-center gap-1 text-xs font-semibold text-primary"><Check class="size-3.5" /> staat erop</span>
                    </label>

                    <div class="mt-2 flex flex-wrap items-center gap-3">
                        <div class="inline-flex items-center rounded-xl border border-input bg-background">
                            <button
                                type="button"
                                class="flex size-11 items-center justify-center rounded-l-xl text-muted-foreground transition hover:bg-secondary hover:text-foreground disabled:opacity-40"
                                aria-label="Eén lager"
                                :disabled="nummer === 1"
                                @click="stap(-1)"
                            >
                                <Minus class="size-4" />
                            </button>
                            <input
                                id="rugnummer"
                                :value="nummer ?? ''"
                                type="text"
                                inputmode="numeric"
                                maxlength="2"
                                placeholder="–"
                                class="tabular h-11 w-14 border-x border-input bg-transparent text-center text-xl font-extrabold outline-none focus:bg-secondary/40"
                                @input="typ"
                            />
                            <button
                                type="button"
                                class="flex size-11 items-center justify-center rounded-r-xl text-muted-foreground transition hover:bg-secondary hover:text-foreground disabled:opacity-40"
                                aria-label="Eén hoger"
                                :disabled="nummer === 99"
                                @click="stap(1)"
                            >
                                <Plus class="size-4" />
                            </button>
                        </div>

                        <span class="text-xs" :class="fout ? 'text-destructive' : 'text-muted-foreground'" role="status">
                            <template v-if="fout">{{ fout }}</template>
                            <template v-else-if="bezig">Opslaan…</template>
                            <span v-else-if="opgeslagen" class="inline-flex items-center gap-1 font-medium text-primary"><Check class="size-3.5" /> Op de kaart</span>
                            <template v-else-if="!heeftNummer">1 tot 99, zoals op een shirt</template>
                        </span>

                        <button
                            v-if="heeftNummer && !bezig"
                            type="button"
                            class="inline-flex min-h-11 items-center text-xs text-muted-foreground underline underline-offset-4 hover:text-foreground"
                            @click="wis"
                        >
                            Weghalen
                        </button>
                    </div>
                </div>
            </div>

            <button
                v-if="klaar"
                type="button"
                class="mt-5 inline-flex h-11 w-full items-center justify-center rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground transition hover:opacity-90 sm:w-auto"
                @click="open = false"
            >
                Klaar
            </button>
        </div>
    </div>
</template>
