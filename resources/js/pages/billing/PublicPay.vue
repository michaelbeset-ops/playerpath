<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { CheckCircle2, FlaskConical, LoaderCircle } from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * Afrekenen via een link uit een e-mail, zonder in te loggen.
 *
 * Bewust kaal: één bedrag, één knop. Er staat niets op wat niet nodig is om te
 * betalen - geen achternaam, geen geboortedatum, geen andere rekeningen.
 */
const props = defineProps<{
    payment: { description: string; amount: string; due_on: string; paid: boolean; closed?: boolean; player: string | null };
    school: { name: string };
    connected: boolean;
    payUrl: string;
}>();

const page = usePage<SharedData>();
const melding = computed(() => (page.props.flash as { status: string | null } | undefined)?.status ?? null);
const demo = computed(() => page.props.paymentsDemo === true);

// Leeg formulier; de server kan wel een fout onder 'payment' teruggeven.
const form = useForm<{ payment?: string }>({});

const betaal = () => form.post(props.payUrl);
</script>

<template>
    <Head title="Betalen" />

    <!-- Licht, net als de aanmeldpagina: dit is dezelfde bezoeker, een paar
         dagen later. -->
    <div class="flex min-h-svh items-center justify-center bg-background px-4 py-10 text-foreground">
        <div class="w-full max-w-md">
            <div class="flex items-center gap-3">
                <AppLogoIcon class="size-10 rounded-xl" />
                <div class="min-w-0">
                    <p class="text-xs uppercase tracking-widest text-muted-foreground">Betalen aan</p>
                    <p class="truncate text-lg font-semibold">{{ school.name }}</p>
                </div>
            </div>

            <div v-if="melding" class="mt-6 rounded-xl border border-primary/40 bg-primary/10 p-4 text-sm">
                {{ melding }}
            </div>

            <div class="mt-6 rounded-2xl border border-border bg-card p-6">
                <template v-if="payment.paid">
                    <div class="flex items-center gap-3">
                        <CheckCircle2 class="size-6 text-primary" />
                        <p class="font-semibold">Deze betaling is voldaan</p>
                    </div>
                    <p class="mt-2 text-sm text-muted-foreground">Je hoeft niets meer te doen. Bedankt!</p>
                </template>

                <template v-else>
                    <p class="text-sm text-muted-foreground">
                        {{ payment.description }}<template v-if="payment.player"> · voor {{ payment.player }}</template>
                    </p>
                    <p class="tabular mt-1 text-3xl font-bold">{{ payment.amount }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">Vervaldatum {{ payment.due_on }}</p>

                    <p v-if="payment.closed" class="mt-6 rounded-xl border border-border bg-secondary/60 p-4 text-sm">
                        Deze rekening staat niet meer open. Heb je een vraag, neem dan contact op met de school.
                    </p>
                    <form v-else-if="connected" class="mt-6" @submit.prevent="betaal">
                        <p v-if="demo" class="mb-3 flex items-start gap-2 rounded-lg border border-warning/40 bg-warning/10 p-3 text-xs">
                            <FlaskConical class="mt-0.5 size-4 shrink-0 text-warning" />
                            <span>Demo: je ziet de betaalflow, er wordt geen geld afgeschreven.</span>
                        </p>
                        <Button type="submit" class="w-full" :disabled="form.processing">
                            <LoaderCircle v-if="form.processing" class="mr-2 size-4 animate-spin" />
                            {{ demo ? 'Betalen (demo)' : 'Betalen met iDEAL' }}
                        </Button>
                        <InputError class="mt-2" :message="form.errors.payment" />
                    </form>

                    <p v-else class="mt-6 rounded-lg border border-border bg-background p-4 text-sm text-muted-foreground">
                        Online betalen kan hier op dit moment niet. Neem contact op met {{ school.name }} om af te rekenen.
                    </p>
                </template>
            </div>

            <p class="mt-4 text-center text-xs text-muted-foreground">Je betaalt via de betaalprovider van de school. Deze link is persoonlijk.</p>
        </div>
    </div>
</template>
