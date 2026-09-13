<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Check, PartyPopper, Star, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

/**
 * De startlijst: wat er moet gebeuren voordat de school van jou is.
 *
 * Geen rondleiding maar een lijst die tot handelingen leidt: elke stap is een
 * knop naar de plek waar je hem afmaakt. Vier dingen die dit bruikbaar houden:
 *
 * 1. **Je ziet hoe ver je bent** - "3 van 8", plus een balk. Zonder dat is een
 *    lijst van acht dingen een muur.
 * 2. **Het eerste rapport is gemarkeerd.** Dat is het moment waarop een lege
 *    kaart een spelerskaart wordt; de rest is administratie eromheen.
 * 3. **Af is af.** Eén felicitatie, dan voorgoed weg. Een lijst die blijft
 *    hangen nadat je klaar bent, leer je negeren.
 * 4. **Wegklikken mag**, en de melding zegt waar je hem terugvindt. Anders is
 *    de enige uitweg: alle stappen doen, ook die je niet wilt.
 *
 * De volgorde komt van de server en is die van het fundament: school →
 * locatie → groep, en dan pas spelers en trainingen.
 */
export interface Stap {
    key: string;
    title: string;
    body: string;
    href: string;
    action: string;
    done: boolean;
    highlight?: boolean;
}

const props = defineProps<{
    data: { steps: Stap[]; done: number; total: number; complete: boolean; dismissed: boolean };
}>();

const percentage = computed(() => Math.round((props.data.done / props.data.total) * 100));

// De eerstvolgende open stap: die krijgt de knop, de rest een tekstlink. Acht
// even zware knoppen onder elkaar is geen lijst maar een keuzemenu.
const volgende = computed(() => props.data.steps.find((stap) => !stap.done)?.key ?? null);

const bezig = ref(false);

const wegklikken = () => {
    bezig.value = true;
    router.post('/onboarding/startlijst/gezien', {}, { preserveScroll: true, onFinish: () => (bezig.value = false) });
};

// De felicitatie is gezien: pas daarna gaat het blok voorgoed weg. Zou de
// server het bij het laatste vinkje wegzetten, dan zag niemand ooit dat hij
// klaar was - het blok zou gewoon verdwenen zijn.
const afronden = () => {
    bezig.value = true;
    router.post('/onboarding/startlijst/klaar', {}, { preserveScroll: true, onFinish: () => (bezig.value = false) });
};
</script>

<template>
    <!-- Weggeklikt: dan staat er niets. Terughalen kan bij de instellingen. -->
    <section v-if="!data.dismissed" class="rounded-2xl border border-primary/30 bg-primary/5 p-4 sm:p-5">
        <!-- ===== Af: één felicitatie, en dan is hij weg ===== -->
        <template v-if="data.complete">
            <div class="text-center">
                <PartyPopper class="mx-auto size-7 text-primary" />
                <p class="mt-2 text-lg font-semibold">Je school draait</p>
                <p class="mx-auto mt-1 max-w-md text-sm text-muted-foreground">
                    Alle stappen zijn gedaan. Vanaf nu zien je trainers hun rooster en je ouders de kaart van hun kind.
                </p>
                <button
                    type="button"
                    class="mt-4 inline-flex min-h-11 items-center gap-2 rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground transition hover:opacity-90 disabled:opacity-60"
                    :disabled="bezig"
                    @click="afronden"
                >
                    <Check class="size-4" />
                    Mooi, sluit maar
                </button>
            </div>
        </template>

        <!-- ===== Onderweg ===== -->
        <template v-else>
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="font-medium">Nog even dit, dan draait je school</p>
                    <p class="tabular mt-0.5 text-xs text-muted-foreground">{{ data.done }} van {{ data.total }} gedaan</p>
                </div>

                <button
                    type="button"
                    class="-my-2.5 flex size-11 shrink-0 items-center justify-center rounded-lg text-muted-foreground transition hover:bg-card hover:text-foreground"
                    aria-label="Startlijst wegklikken"
                    title="Wegklikken. Je haalt hem terug bij Instellingen."
                    :disabled="bezig"
                    @click="wegklikken"
                >
                    <X class="size-4" />
                </button>
            </div>

            <!-- De balk is de tweede drager van hetzelfde cijfer -->
            <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-secondary">
                <div class="h-full rounded-full bg-primary transition-all" :style="{ width: percentage + '%' }"></div>
            </div>

            <ol class="mt-4 space-y-2">
                <li
                    v-for="stap in data.steps"
                    :key="stap.key"
                    class="flex flex-col gap-2 rounded-xl border bg-card p-3 sm:flex-row sm:items-center sm:gap-3"
                    :class="[
                        stap.done ? 'border-border opacity-60' : 'border-border',
                        !stap.done && stap.highlight ? 'border-primary/50 bg-primary/5' : '',
                    ]"
                >
                    <div class="flex min-w-0 flex-1 items-start gap-3">
                        <span
                            class="flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold"
                            :class="stap.done ? 'bg-primary text-primary-foreground' : 'border border-border text-muted-foreground'"
                        >
                            <Check v-if="stap.done" class="size-3.5" />
                            <Star v-else-if="stap.highlight" class="size-3 text-primary" />
                            <template v-else>{{ data.steps.indexOf(stap) + 1 }}</template>
                        </span>

                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium" :class="stap.done ? 'line-through' : ''">{{ stap.title }}</p>
                            <p v-if="!stap.done" class="text-xs text-muted-foreground">{{ stap.body }}</p>
                        </div>
                    </div>

                    <Link
                        v-if="!stap.done"
                        :href="stap.href"
                        class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-lg px-3 text-sm font-medium transition"
                        :class="
                            stap.key === volgende
                                ? 'bg-primary text-primary-foreground hover:opacity-90'
                                : 'border border-border bg-background hover:border-primary'
                        "
                    >
                        {{ stap.action }}
                    </Link>
                </li>
            </ol>
        </template>
    </section>
</template>
