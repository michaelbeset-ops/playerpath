<script setup lang="ts">
import { kleurVan, useGrading } from '@/lib/grade';
import { computed } from 'vue';

/**
 * Een categorie beoordelen met vier kleuren: werkpunt, op weg, goed, top.
 *
 * Vier knoppen naast elkaar, elk minstens 44 pixels hoog: op een telefoon van
 * 375 breed is dat per knop nog ruim 70 pixels. De gekozen kleur is gevuld,
 * de rest een rand. Het model is het rapportcijfer dat bij die kleur hoort
 * (zie Grade::NIVEAUS), zodat de rest van de app gewoon doorrekent.
 *
 * Een voorgevuld cijfer uit een oud rapport (7,4) valt vanzelf in zijn kleur.
 */
const model = defineModel<number | null>({ required: true });

const props = defineProps<{ label: string; id: string }>();

const { niveaus, niveauVoor } = useGrading();

const gekozen = computed(() => (model.value === null ? null : niveauVoor(model.value * 10)?.key ?? null));
</script>

<template>
    <div :id="props.id" class="grid grid-cols-4 gap-1.5" role="radiogroup" :aria-label="props.label">
        <button
            v-for="(niveau, i) in niveaus"
            :key="niveau.key"
            type="button"
            role="radio"
            :aria-checked="gekozen === niveau.key"
            class="flex min-h-12 flex-col items-center justify-center gap-1 rounded-xl border-2 px-1 text-[11px] font-semibold leading-tight transition sm:text-xs"
            :style="
                gekozen === niveau.key
                    ? { borderColor: kleurVan(niveau.key), backgroundColor: kleurVan(niveau.key), color: 'white' }
                    : { borderColor: kleurVan(niveau.key, 0.35), color: kleurVan(niveau.key) }
            "
            :title="niveau.label + ' (toets ' + (i + 1) + ')'"
            @click.stop="model = niveau.score"
        >
            <span
                class="inline-block size-2.5 rounded-full"
                :style="{ backgroundColor: gekozen === niveau.key ? 'white' : kleurVan(niveau.key) }"
                aria-hidden="true"
            ></span>
            {{ niveau.label }}
        </button>
    </div>
</template>
