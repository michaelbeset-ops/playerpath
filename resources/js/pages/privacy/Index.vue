<script setup lang="ts">
import FlashMessage from '@/components/FlashMessage.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { FileDown, ShieldCheck, Trash2 } from 'lucide-vue-next';

interface OudLid {
    id: number;
    name: string;
    deactivated_at: string | null;
    months_inactive: number | null;
    reports_count: number;
    attendances_count: number;
    guardians: { name: string; email: string }[];
}

const props = defineProps<{
    retention: {
        retentionMonths: number | null;
        inactiveCount: number;
        eligibleCount: number;
        waitingCount: number;
        players: OudLid[];
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Privacy', href: '/privacy' }];

const form = useForm({ retention_months: props.retention.retentionMonths });

const opslaan = () => form.patch('/privacy', { preserveScroll: true });

/**
 * Verwijderen is onomkeerbaar en neemt de rapporten mee. Daarom moet de naam
 * hardop bevestigd worden: een misklik in een lijst is anders zo gebeurd.
 */
const verwijder = (lid: OudLid) => {
    if (confirm(`Alle gegevens van ${lid.name} definitief verwijderen? Dit kan niet ongedaan worden gemaakt.`)) {
        router.delete('/privacy/players/' + lid.id, { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Privacy" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-4">
            <FlashMessage />

            <div class="flex items-center gap-3">
                <ShieldCheck class="size-5 text-primary" />
                <div>
                    <h1 class="text-xl font-semibold">Privacy en bewaartermijn</h1>
                    <p class="text-sm text-muted-foreground">
                        Wat de school bewaart van oud-leden, en hoe je een verzoek om inzage of verwijdering afhandelt.
                    </p>
                </div>
            </div>

            <!-- De instelling -->
            <form class="mt-4 rounded-xl border border-border bg-card p-5 shadow-sm" @submit.prevent="opslaan">
                <p class="font-medium">Bewaartermijn</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    Hoe lang bewaar je de gegevens nadat een speler is gestopt? De termijn gaat lopen op de dag dat je hem op niet-actief zet.
                    Laat het veld leeg als je nog niets wilt vastleggen.
                </p>

                <div class="mt-4 flex flex-wrap items-end gap-3">
                    <div class="grid gap-2">
                        <Label for="retention_months">Aantal maanden</Label>
                        <Input
                            id="retention_months"
                            v-model="form.retention_months"
                            type="number"
                            min="1"
                            max="120"
                            placeholder="bijv. 24"
                            class="w-40"
                        />
                    </div>
                    <Button type="submit" :disabled="form.processing">Opslaan</Button>
                </div>
                <InputError class="mt-2" :message="form.errors.retention_months" />

                <p class="mt-3 text-xs text-muted-foreground">
                    Er wordt nooit iets automatisch verwijderd. Zodra een termijn verstrijkt verschijnt de speler hieronder en beslis jij.
                </p>
            </form>

            <!-- Wat er mag weg -->
            <div class="mt-4 rounded-xl border border-border bg-card p-5 shadow-sm">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <p class="font-medium">Mag verwijderd worden</p>
                    <p class="tabular text-xs text-muted-foreground">
                        {{ retention.inactiveCount }} oud-{{ retention.inactiveCount === 1 ? 'lid' : 'leden' }} in totaal
                        <span v-if="retention.retentionMonths !== null"> · {{ retention.waitingCount }} nog binnen de termijn</span>
                    </p>
                </div>

                <p v-if="retention.retentionMonths === null" class="mt-3 text-sm text-muted-foreground">
                    Er is nog geen bewaartermijn ingesteld, dus er wordt niets gesignaleerd. Vul hierboven een termijn in.
                </p>

                <p v-else-if="retention.players.length === 0" class="mt-3 text-sm text-muted-foreground">
                    Van geen enkel oud-lid is de termijn van {{ retention.retentionMonths }} maanden verstreken.
                </p>

                <div v-else class="mt-4 space-y-3">
                    <div v-for="lid in retention.players" :key="lid.id" class="rounded-lg border border-border p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="font-medium">{{ lid.name }}</p>
                                <p class="tabular mt-0.5 text-xs text-muted-foreground">
                                    Gestopt op {{ lid.deactivated_at }} · {{ lid.months_inactive }} maanden geleden ·
                                    {{ lid.reports_count }} {{ lid.reports_count === 1 ? 'rapport' : 'rapporten' }} ·
                                    {{ lid.attendances_count }} keer aanwezigheid
                                </p>
                                <p v-if="lid.guardians.length" class="mt-1 text-xs text-muted-foreground">
                                    Ouders: {{ lid.guardians.map((o) => o.name + ' (' + o.email + ')').join(', ') }}
                                </p>
                            </div>

                            <div class="flex shrink-0 flex-wrap gap-2">
                                <!-- Gewone link: de browser moet het bestand downloaden -->
                                <a
                                    :href="'/players/' + lid.id + '/gegevens'"
                                    class="inline-flex h-9 items-center rounded-lg border border-border px-3 text-sm font-medium hover:border-primary"
                                >
                                    <FileDown class="mr-2 size-4" />
                                    Gegevens
                                </a>
                                <Button variant="destructive" @click="verwijder(lid)">
                                    <Trash2 class="mr-2 size-4" />
                                    Verwijderen
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <p class="mt-4 text-xs text-muted-foreground">
                Vraagt een ouder om inzage van een speler die nog actief is? Dan staat dezelfde knop op de pagina van die speler.
            </p>
        </div>
    </AppLayout>
</template>
