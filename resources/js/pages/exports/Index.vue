<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { FileDown, FileSpreadsheet, FileText } from 'lucide-vue-next';
import { reactive } from 'vue';

interface Overzicht {
    key: string;
    title: string;
    description: string;
    supportsDateRange: boolean;
}

const props = defineProps<{
    exports: Overzicht[];
    defaultRange: { from: string; to: string };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Overzichten', href: '/exports' }];

// Eén periode voor alle overzichten die er een hebben: minder te bedienen.
const periode = reactive({ ...props.defaultRange });

/**
 * Een gewone link, geen Inertia-navigatie: de browser moet het bestand
 * gewoon downloaden.
 */
const url = (overzicht: Overzicht, format: 'csv' | 'xlsx') => {
    const params = new URLSearchParams({ format });

    if (overzicht.supportsDateRange) {
        params.set('from', periode.from);
        params.set('to', periode.to);
    }

    return '/exports/' + overzicht.key + '?' + params.toString();
};
</script>

<template>
    <Head title="Overzichten" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-3xl p-4">
            <h1 class="text-2xl font-semibold tracking-tight">Overzichten</h1>
            <p class="mt-1 text-sm text-muted-foreground">Download je gegevens als Excel of CSV, bijvoorbeeld voor je boekhouder of een teamoverleg.</p>

            <!-- Periode: geldt voor de overzichten met een datum -->
            <div class="mt-6 rounded-xl border border-border bg-card p-4 shadow-sm">
                <p class="text-sm font-medium">Periode</p>
                <p class="mt-0.5 text-xs text-muted-foreground">Voor trainingen en aanwezigheid. Spelers zijn altijd de volledige lijst.</p>

                <div class="mt-3 grid grid-cols-2 gap-3">
                    <div class="grid gap-1.5">
                        <Label for="from">Van</Label>
                        <Input id="from" v-model="periode.from" type="date" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="to">Tot en met</Label>
                        <Input id="to" v-model="periode.to" type="date" />
                    </div>
                </div>
            </div>

            <div class="mt-4 space-y-3">
                <div v-for="overzicht in exports" :key="overzicht.key" class="rounded-xl border border-border bg-card p-4 shadow-sm sm:p-5">
                    <div class="flex items-start gap-3">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <FileDown class="size-5" />
                        </span>

                        <div class="min-w-0 flex-1">
                            <p class="font-medium">{{ overzicht.title }}</p>
                            <p class="mt-0.5 text-sm text-muted-foreground">{{ overzicht.description }}</p>
                            <p v-if="overzicht.supportsDateRange" class="mt-1 text-xs text-muted-foreground">
                                Periode {{ periode.from }} t/m {{ periode.to }}
                            </p>
                        </div>
                    </div>

                    <!-- Twee grote knoppen, ook op mobiel naast elkaar -->
                    <div class="mt-4 grid grid-cols-2 gap-2">
                        <a
                            :href="url(overzicht, 'xlsx')"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                        >
                            <FileSpreadsheet class="size-4" />
                            Excel
                        </a>
                        <a
                            :href="url(overzicht, 'csv')"
                            class="inline-flex items-center justify-center gap-2 rounded-xl border border-border bg-card px-4 py-2.5 text-sm font-medium shadow-sm transition hover:border-primary"
                        >
                            <FileText class="size-4" />
                            CSV
                        </a>
                    </div>
                </div>
            </div>

            <p class="mt-6 text-xs text-muted-foreground">
                Het betalingsoverzicht komt hier bij zodra Mollie is aangesloten. De opzet is daar al op voorbereid.
            </p>
        </div>
    </AppLayout>
</template>
