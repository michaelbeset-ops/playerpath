<script setup lang="ts">
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { LoaderCircle } from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

/**
 * Een foto vierkant uitsnijden vóór het uploaden.
 *
 * De server maakt een foto toch vierkant (ProfilePhoto), maar dan uit het
 * midden - en het gezicht van een kind zit zelden precies in het midden van
 * een telefoonfoto. Hier schuif je de foto zelf goed en zoom je in, en pas
 * dan gaat hij weg. Het resultaat is 512 bij 512, dezelfde maat als op de
 * server, zodat er onderweg niets meer verandert.
 *
 * Bewust simpel: slepen met één vinger of de muis, zoomen met een schuif.
 * Geen draaien, geen vrije verhoudingen: de kaart is vierkant en dat is het.
 */
const props = defineProps<{
    file: File;
    name: string;
    /** Wat er op het kaartje naast de uitsnede staat; zonder alleen de foto. */
    kaart?: { first_name: string; last_name: string; overall: number | null; position: string } | null;
}>();

/**
 * `preview` geeft bij elke verschuiving de uitsnede als kleine afbeelding
 * door, zodat de echte kaart op de pagina meebeweegt terwijl je schuift.
 */
const emit = defineEmits<{ done: [blob: Blob]; cancel: []; preview: [url: string | null] }>();

/** De uitsnede als kleine afbeelding, voor het kaartje in dit venster én de kaart erachter. */
const voorbeeld = ref<string | null>(null);
const klein = document.createElement('canvas');
klein.width = 192;
klein.height = 192;
let voorbeeldGepland = false;

/** Maat van het vak op het scherm, in css-pixels. Past op 375 breed. */
const VAK = 280;
/** Uitvoermaat, gelijk aan ProfilePhoto::ZIJDE. */
const UIT = 512;

const doek = ref<HTMLCanvasElement | null>(null);
const open = ref(true);
const laden = ref(true);
const zoom = ref(1);

let afbeelding: ImageBitmap | HTMLImageElement | null = null;
let breedte = 0;
let hoogte = 0;
/** Schaal waarbij de foto het vak precies bedekt; daarbovenop komt de zoom. */
let pas = 1;
let x = 0;
let y = 0;

const schaal = () => pas * zoom.value;

/** De foto mag nooit een rand van het vak vrijlaten. */
const begrens = () => {
    const w = breedte * schaal();
    const h = hoogte * schaal();
    x = Math.min(0, Math.max(VAK - w, x));
    y = Math.min(0, Math.max(VAK - h, y));
};

const teken = () => {
    const ctx = doek.value?.getContext('2d');

    if (!ctx || !afbeelding) {
        return;
    }

    const f = doek.value!.width / VAK;
    ctx.clearRect(0, 0, doek.value!.width, doek.value!.height);
    ctx.drawImage(afbeelding, x * f, y * f, breedte * schaal() * f, hoogte * schaal() * f);

    // Het kaartje hooguit één keer per beeld bijwerken; een dataURL per
    // muisbeweging maakt het slepen stroperig.
    if (!voorbeeldGepland) {
        voorbeeldGepland = true;
        requestAnimationFrame(() => {
            voorbeeldGepland = false;

            if (!afbeelding) {
                return;
            }

            const k = klein.width / VAK;
            const kctx = klein.getContext('2d')!;
            kctx.clearRect(0, 0, klein.width, klein.height);
            kctx.drawImage(afbeelding, x * k, y * k, breedte * schaal() * k, hoogte * schaal() * k);
            voorbeeld.value = klein.toDataURL('image/jpeg', 0.8);
            emit('preview', voorbeeld.value);
        });
    }
};

// Zoomen gebeurt om het midden van het vak, niet om de linkerbovenhoek.
watch(zoom, (nieuw, oud) => {
    const factor = nieuw / oud;
    const mid = VAK / 2;
    x = mid - (mid - x) * factor;
    y = mid - (mid - y) * factor;
    begrens();
    teken();
});

const laad = async () => {
    try {
        // Neemt de draaiing uit de foto mee: een telefoonfoto staat anders
        // nogal eens op zijn kant.
        afbeelding = await createImageBitmap(props.file, { imageOrientation: 'from-image' });
        breedte = afbeelding.width;
        hoogte = afbeelding.height;
    } catch {
        afbeelding = await new Promise<HTMLImageElement>((ok, fout) => {
            const img = new Image();
            img.onload = () => ok(img);
            img.onerror = fout;
            img.src = URL.createObjectURL(props.file);
        });
        breedte = afbeelding.naturalWidth;
        hoogte = afbeelding.naturalHeight;
    }

    pas = Math.max(VAK / breedte, VAK / hoogte);
    x = (VAK - breedte * pas) / 2;
    y = (VAK - hoogte * pas) / 2;
    laden.value = false;
    teken();
};

// --- Slepen ---
let sleep: { px: number; py: number; x: number; y: number } | null = null;

const start = (e: PointerEvent) => {
    (e.currentTarget as HTMLElement).setPointerCapture(e.pointerId);
    sleep = { px: e.clientX, py: e.clientY, x, y };
};

const beweeg = (e: PointerEvent) => {
    if (!sleep) {
        return;
    }

    x = sleep.x + (e.clientX - sleep.px);
    y = sleep.y + (e.clientY - sleep.py);
    begrens();
    teken();
};

const stop = () => (sleep = null);

const bezig = ref(false);

const bevestig = () => {
    if (!afbeelding || bezig.value) {
        return;
    }

    bezig.value = true;

    const uit = document.createElement('canvas');
    uit.width = UIT;
    uit.height = UIT;
    const f = UIT / VAK;
    uit.getContext('2d')!.drawImage(afbeelding, x * f, y * f, breedte * schaal() * f, hoogte * schaal() * f);

    uit.toBlob(
        (blob) => {
            bezig.value = false;

            if (blob) {
                open.value = false;
                emit('done', blob);
            }
        },
        'image/jpeg',
        0.9,
    );
};

const annuleer = () => {
    open.value = false;
    emit('preview', null);
    emit('cancel');
};

watch(open, (nu) => {
    if (!nu && !bezig.value) {
        emit('preview', null);
        emit('cancel');
    }
});

onMounted(laad);

onBeforeUnmount(() => {
    if (afbeelding instanceof ImageBitmap) {
        afbeelding.close();
    }
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-sm">
            <DialogHeader>
                <DialogTitle>Foto uitsnijden</DialogTitle>
                <DialogDescription>Schuif de foto zodat het gezicht van {{ name.split(' ')[0] }} in het vak staat.</DialogDescription>
            </DialogHeader>

            <div class="mx-auto">
                <div
                    class="relative overflow-hidden rounded-2xl border border-border bg-secondary"
                    :style="{ width: VAK + 'px', height: VAK + 'px', touchAction: 'none', cursor: 'grab' }"
                    @pointerdown="start"
                    @pointermove="beweeg"
                    @pointerup="stop"
                    @pointercancel="stop"
                >
                    <canvas ref="doek" :width="VAK * 2" :height="VAK * 2" class="block size-full"></canvas>
                    <!--
                        Het fotovak op de kaart is 3 bij 2 en toont de bovenkant
                        (.pp-foto-vak, object-position top). Van dit vierkant staat
                        dus alleen het bovenste deel op de kaart. Het onderste derde
                        is donker, zodat je niet denkt dat het gezicht erop staat
                        terwijl de kaart het er straks afsnijdt. Het medaillon en
                        de lijsten gebruiken wel het hele vierkant.
                    -->
                    <div class="pointer-events-none absolute inset-x-0 bottom-0 flex h-1/3 items-end justify-center bg-black/55 pb-2">
                        <span class="rounded-full bg-black/60 px-2 py-0.5 text-[10px] font-medium text-white">Valt buiten de kaart</span>
                    </div>
                    <div class="pointer-events-none absolute inset-x-0 top-2/3 border-t-2 border-dashed border-white/80"></div>
                    <div class="pointer-events-none absolute inset-0 rounded-2xl ring-1 ring-inset ring-foreground/10"></div>
                    <div v-if="laden" class="absolute inset-0 flex items-center justify-center bg-secondary">
                        <LoaderCircle class="size-6 animate-spin text-muted-foreground" />
                    </div>
                </div>

                <!-- Zo komt hij op de kaart: een klein kaartje dat meebeweegt
                     met het schuiven, met hetzelfde fotovak van 3 bij 2 als de
                     echte kaart. Een vierkant kaartje liet meer zien dan de
                     kaart straks doet, en dan leek het goed terwijl het niet zo was. -->
                <div class="mt-3 flex items-center gap-3">
                    <div class="w-24 shrink-0 overflow-hidden rounded-xl border-2 border-[#8c96a3] bg-[#0a0f1c] text-[#f1f5f9] shadow-md">
                        <div class="relative aspect-[3/2] bg-[#131c30]">
                            <img v-if="voorbeeld" :src="voorbeeld" alt="" class="size-full object-cover object-top" />
                            <div class="absolute inset-x-0 bottom-0 h-1/2 bg-gradient-to-t from-[#0a0f1c] to-transparent"></div>
                            <p v-if="kaart" class="tabular absolute bottom-1 left-1.5 text-lg font-extrabold leading-none">{{ kaart.overall ?? '-' }}</p>
                        </div>
                        <div class="px-1.5 pb-1.5 pt-0.5">
                            <p class="truncate text-[9px] leading-tight text-[#94a3b8]">{{ kaart?.first_name ?? name.split(' ')[0] }}</p>
                            <p class="truncate text-[11px] font-bold leading-tight">{{ kaart?.last_name ?? name.split(' ').slice(1).join(' ') }}</p>
                            <div class="mt-1 space-y-0.5">
                                <div class="h-0.5 w-full rounded bg-[#25282d]"><div class="h-full w-2/3 rounded bg-[#22e06b]"></div></div>
                                <div class="h-0.5 w-full rounded bg-[#25282d]"><div class="h-full w-1/2 rounded bg-[#22e06b]"></div></div>
                            </div>
                        </div>
                    </div>
                    <p class="text-xs leading-relaxed text-muted-foreground">
                        <span class="font-medium text-foreground">Zo komt hij op de kaart.</span> Sleep de foto en zoom in tot het gezicht bovenin het vak
                        staat; het cijfer komt linksonder over de foto heen.
                    </p>
                </div>

                <label class="mt-3 flex items-center gap-3 text-xs text-muted-foreground">
                    <span class="shrink-0">Zoom</span>
                    <input v-model.number="zoom" type="range" min="1" max="3" step="0.01" class="h-11 w-full accent-[hsl(var(--primary))]" aria-label="Inzoomen" />
                </label>
            </div>

            <DialogFooter class="gap-2">
                <button
                    type="button"
                    class="inline-flex min-h-11 items-center justify-center rounded-lg border border-border px-4 text-sm font-medium"
                    @click="annuleer"
                >
                    Annuleren
                </button>
                <button
                    type="button"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-primary px-4 text-sm font-semibold text-primary-foreground disabled:opacity-60"
                    :disabled="laden || bezig"
                    @click="bevestig"
                >
                    <LoaderCircle v-if="bezig" class="size-4 animate-spin" />
                    Deze gebruiken
                </button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
