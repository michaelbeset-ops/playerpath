<script setup lang="ts">
import InstallSteps from '@/components/InstallSteps.vue';
import { useInstall } from '@/composables/useInstall';
import { usePage } from '@inertiajs/vue3';
import { ArrowDown, Download, X } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';

/**
 * "Zet PlayerPath op je beginscherm."
 *
 * Iedereen hoort de app op zijn beginscherm te hebben: een ouder die hem
 * elke week opent, een trainer die na de training zijn rapporten invult,
 * een kind dat zijn kaart wil zien. Daarom komt de vraag vroeg en komt hij
 * terug, maar niet vervelend:
 *
 * 1. **Niet als het al een app is.** Wie hem al heeft ziet dit nooit.
 * 2. **Op iPhone en iPad na een paar seconden, elders na een halve minuut.**
 *    Apple laat een website die vraag niet zelf stellen; zonder deze balk
 *    ontdekt op een iPhone niemand dat het kan. Android toont soms zelf al
 *    iets, dus daar mag het rustiger.
 * 3. **Via de kind-link meteen**, ook als het eerder is weggeklikt
 *    (`flash.kindWelkom`). De ouder heeft de link net naar dit apparaat
 *    gestuurd om precies dit te doen.
 * 4. **Wegklikken is "niet nu", en dat onthouden we een week.** Na "Nee"
 *    op de echte knop een maand.
 * 5. **De uitleg past bij het toestel** (useInstall): de deelknop onderin op
 *    een iPhone, bovenin op een iPad, in de adresbalk van Chrome, en in een
 *    ingebouwde browser zoals WhatsApp eerst "open in Safari". Op een iPhone
 *    in Safari wijst een pijl naar de knop onderin.
 */
const UITGESTELD = 'playerpath.install-uitgesteld';
const DAG = 24 * 60 * 60 * 1000;

const page = usePage();
const { isApp, kan, kanKnop, weg, installeer } = useInstall();

// Het welkom via de kind-link. Vastgehouden in een ref: de flash is bij de
// volgende klik weg, maar de vraag hoort te blijven staan tot hij beantwoord is.
const kind = ref(Boolean((page.props.flash as { kindWelkom?: boolean } | undefined)?.kindWelkom));

const rijp = ref(kind.value);
const uitgesteld = ref(false);

const zichtbaar = computed(() => !isApp.value && kan.value && rijp.value && (kind.value || !uitgesteld.value));

const titel = computed(() => (kind.value ? 'Zet je kaart op je beginscherm' : 'Zet PlayerPath op je beginscherm'));
const uitleg = computed(() =>
    kind.value
        ? 'Dan staat je kaart als app tussen je andere apps, en kun je hem altijd met één tik bekijken.'
        : 'Dan opent hij als een app, zonder adresbalk, met één tik. Zo gebruik je hem het makkelijkst.',
);

const lees = (sleutel: string): string | null => {
    try {
        return localStorage.getItem(sleutel);
    } catch {
        return null;
    }
};

const schrijf = (sleutel: string, waarde: string) => {
    try {
        localStorage.setItem(sleutel, waarde);
    } catch {
        // Privémodus of geblokkeerde opslag: dan vragen we het gewoon nog eens.
    }
};

const stelUit = (dagen: number) => {
    kind.value = false;
    uitgesteld.value = true;
    schrijf(UITGESTELD, String(Date.now() + dagen * DAG));
};

const klik = async () => {
    const uitkomst = await installeer();

    if (uitkomst === 'dismissed') {
        stelUit(30);
    } else {
        kind.value = false;
        uitgesteld.value = true;
    }
};

let timer: ReturnType<typeof setTimeout> | null = null;

onMounted(() => {
    const tot = Number(lees(UITGESTELD) ?? 0);
    uitgesteld.value = tot > Date.now();

    if (!rijp.value) {
        const ios = weg.value === 'iphone' || weg.value === 'ipad' || weg.value === 'ios-andere-browser' || weg.value === 'ingebouwd';
        timer = setTimeout(() => (rijp.value = true), (ios ? 6 : 30) * 1000);
    }
});

onUnmounted(() => {
    if (timer) {
        clearTimeout(timer);
    }
});
</script>

<template>
    <div
        v-if="zichtbaar"
        class="fixed inset-x-3 bottom-[calc(var(--pp-tabbar)+0.75rem)] z-50 rounded-xl border bg-card p-3 shadow-lg sm:left-auto sm:w-96"
        :class="kind ? 'border-primary/50' : 'border-border'"
        role="dialog"
        :aria-label="titel"
    >
        <div class="flex items-start gap-3">
            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                <Download class="size-4" />
            </span>

            <div class="min-w-0 flex-1">
                <p class="text-sm font-medium">{{ titel }}</p>
                <p class="text-xs text-muted-foreground">{{ uitleg }}</p>

                <InstallSteps v-if="!kanKnop" class="mt-2" klein :knop="false" />
            </div>

            <button
                type="button"
                class="-m-1 flex size-9 shrink-0 items-center justify-center rounded-lg text-muted-foreground hover:text-foreground"
                aria-label="Niet nu"
                @click="stelUit(7)"
            >
                <X class="size-4" />
            </button>
        </div>

        <div v-if="kanKnop" class="mt-3 flex gap-2">
            <button type="button" class="h-11 flex-1 rounded-lg bg-primary px-3 text-sm font-semibold text-primary-foreground" @click="klik">
                Op beginscherm zetten
            </button>
            <button type="button" class="h-11 rounded-lg border border-border px-3 text-sm font-medium" @click="stelUit(7)">Niet nu</button>
        </div>
        <div v-else class="mt-3">
            <button type="button" class="h-11 w-full rounded-lg border border-border px-3 text-sm font-medium" @click="stelUit(7)">Begrepen</button>
        </div>
    </div>

    <!-- iPhone in Safari: de deelknop zit in de balk van Safari, onder de pagina. Een pijl wijst hem aan. -->
    <div
        v-if="zichtbaar && weg === 'iphone'"
        class="pointer-events-none fixed inset-x-0 bottom-1 z-50 flex justify-center"
        aria-hidden="true"
    >
        <span class="flex size-9 animate-bounce items-center justify-center rounded-full bg-primary text-primary-foreground shadow-lg">
            <ArrowDown class="size-5" />
        </span>
    </div>
</template>
