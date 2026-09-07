<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { CheckCircle2, LoaderCircle } from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * Afrekenen via een link uit een e-mail, zonder in te loggen.
 *
 * Bewust kaal: één bedrag, één knop. Er staat niets op wat niet nodig is om te
 * betalen — geen achternaam, geen geboortedatum, geen andere rekeningen.
 */
const props = defineProps<{
    payment: { description: string; amount: string; due_on: string; paid: boolean; player: string | null };
    school: { name: string };
    connected: boolean;
    payUrl: string;
}>();

const page = usePage();
const melding = computed(() => (page.props.flash as { status: string | null } | undefined)?.status ?? null);

const form = useForm({});

const betaal = () => form.post(props.payUrl);
</script>

<template>
    <Head title="Betalen" />

    <div class="theme-donker flex min-h-svh items-center justify-center bg-background px-4 py-10 text-foreground">
        <div class="w-full max-w-md">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-xl bg-primary text-primary-foreground">
                    <AppLogoIcon class="size-6" />
                </div>
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
                    <p class="mt-2 text-sm text-muted-foreground">
                        Je hoeft niets meer te doen. Bedankt!
                    </p>
                </template>

                <template v-else>
                    <p class="text-sm text-muted-foreground">
                        {{ payment.description }}<template v-if="payment.player"> · voor {{ payment.player }}</template>
                    </p>
                    <p class="tabular mt-1 text-3xl font-bold">{{ payment.amount }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">Vervaldatum {{ payment.due_on }}</p>

                    <form v-if="connected" class="mt-6" @submit.prevent="betaal">
                        <Button type="submit" class="w-full" :disabled="form.processing">
                            <LoaderCircle v-if="form.processing" class="mr-2 size-4 animate-spin" />
                            Betalen met iDEAL
                        </Button>
                        <InputError class="mt-2" :message="form.errors.payment" />
                    </form>

                    <p v-else class="mt-6 rounded-lg border border-border bg-background p-4 text-sm text-muted-foreground">
                        Online betalen kan hier op dit moment niet. Neem contact op met {{ school.name }} om af te rekenen.
                    </p>
                </template>
            </div>

            <p class="mt-4 text-center text-xs text-muted-foreground">
                Je betaalt via de betaalprovider van de school. Deze link is persoonlijk.
            </p>
        </div>
    </div>
</template>
