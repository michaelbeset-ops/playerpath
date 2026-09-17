<script setup lang="ts">
import { type PageMeta } from '@/types/pagination';
import { router } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { ref } from 'vue';

/**
 * "Meer laden" onder een lange lijst.
 *
 * De server stuurt de lijst per pagina (App\Support\Pagination\LoadMore) en
 * markeert hem als merge-prop: een partial reload met alleen deze lijst plakt
 * de volgende pagina eronder. De filters staan al in het adres en gaan dus
 * vanzelf mee.
 */
const props = withDefaults(
    defineProps<{
        /** De naam van de lijst-prop, bijvoorbeeld "players". */
        prop: string;
        /** De naam van de meta-prop, bijvoorbeeld "playersPage". */
        metaProp: string;
        meta: PageMeta;
        /** Wat er geteld wordt, in het meervoud: "spelers". */
        noun?: string;
        label?: string;
    }>(),
    { noun: 'regels', label: 'Meer laden' },
);

// Twee keer tikken mag niet twee keer dezelfde pagina eronder plakken.
const bezig = ref(false);

const laad = () => {
    if (bezig.value || props.meta.nextPage === null) {
        return;
    }

    router.reload({
        only: [props.prop, props.metaProp],
        data: { page: props.meta.nextPage },
        onStart: () => (bezig.value = true),
        onFinish: () => (bezig.value = false),
    });
};
</script>

<template>
    <div v-if="meta.total > meta.perPage" class="mt-4 flex flex-col items-center gap-2 text-center">
        <p class="text-xs text-muted-foreground">
            <span class="tabular">{{ meta.shown }}</span> van <span class="tabular">{{ meta.total }}</span> {{ noun }} getoond
        </p>

        <button
            v-if="meta.hasMore"
            type="button"
            class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-border bg-card px-5 text-sm font-medium shadow-sm transition hover:border-primary disabled:opacity-60"
            :disabled="bezig"
            @click="laad"
        >
            <LoaderCircle v-if="bezig" class="size-4 animate-spin" />
            {{ bezig ? 'Bezig met laden…' : label }}
        </button>
    </div>
</template>
