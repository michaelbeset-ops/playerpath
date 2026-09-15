<script setup lang="ts">
import CardVariantChoice from '@/components/cards/CardVariantChoice.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { CircleCheck } from 'lucide-vue-next';
import { ref, watch } from 'vue';

/**
 * De keuze voor de spelerskaart, in de rondleiding.
 *
 * Vóór de stappen over invullen en de kaart: dan laten die meteen de kaart zien
 * die de school koos. Niets voorgekozen; een tik slaat de keuze op, en in de
 * wizard (stap 8) en bij Mijn bedrijf → Spelerskaart kan hij later anders.
 */
const props = defineProps<{
    cardMode: 'prestatie' | 'inzet' | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Kies je spelerskaart', href: '/onboarding/spelerskaart' },
];

const gekozen = ref(props.cardMode);

watch(gekozen, (mode) => {
    if (mode && mode !== props.cardMode) {
        router.post('/instellingen/spelerskaart/variant', { mode }, { preserveScroll: true, preserveState: true });
    }
});
</script>

<template>
    <Head title="Kies je spelerskaart" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-3xl p-4">
            <h1 class="text-2xl font-semibold tracking-tight">Kies je spelerskaart</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Veel voetbal- en keepersscholen willen bewust niet met cijfers werken, omdat kinderen zich dan met elkaar gaan vergelijken. Daarom zijn
                er twee kaarten. Kies wat bij jullie past; de rest van de rondleiding laat daarna die kaart zien.
            </p>

            <div class="mt-5" data-tour="card-choice">
                <CardVariantChoice v-model="gekozen" />
            </div>

            <p v-if="cardMode" class="mt-4 flex items-start gap-2 rounded-xl border border-primary/30 bg-primary/5 p-3 text-sm">
                <CircleCheck class="mt-0.5 size-4 shrink-0 text-primary" />
                <span>
                    Gekozen: <strong>{{ cardMode === 'inzet' ? 'de inzetkaart' : 'de prestatiekaart' }}</strong>. Wisselen kan later in de wizard of bij
                    Mijn bedrijf &rarr; Spelerskaart; er gaat dan niets verloren.
                </span>
            </p>
        </div>
    </AppLayout>
</template>
