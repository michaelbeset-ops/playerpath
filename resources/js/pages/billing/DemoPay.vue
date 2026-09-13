<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { CheckCircle2, FlaskConical, Landmark, LoaderCircle, ShieldCheck } from 'lucide-vue-next';
import { ref } from 'vue';

/**
 * Het nagebootste iDEAL-scherm van de demo-provider.
 *
 * Het lijkt bewust op wat een ouder kent - bank kiezen, bedrag, betalen -
 * zodat een school in een demo de hele flow ziet. En het zegt bewust
 * overal dat het nep is: er wordt geen geld afgeschreven.
 */
const props = defineProps<{
    payment: { description: string; amount: string; player: string | null; paid: boolean };
    school: { name: string };
    mandate: boolean;
    banks: string[];
    completeUrl: string;
}>();

const bank = ref<string | null>(null);
const bezig = ref<string | null>(null);

const rondAf = (result: 'paid' | 'cancelled' | 'failed') => {
    bezig.value = result;
    router.post(props.completeUrl, { result, bank: bank.value }, { onFinish: () => (bezig.value = null) });
};
</script>

<template>
    <Head title="Demo-betaling" />

    <!-- Licht, en anders van kleur dan de app: dit is "de bank", niet PlayerPath. -->
    <div class="flex min-h-svh items-center justify-center bg-background px-4 py-8 text-foreground">
        <div class="w-full max-w-md">
            <div class="flex items-start gap-3 rounded-xl border border-warning/40 bg-warning/10 p-3 text-sm">
                <FlaskConical class="mt-0.5 size-5 shrink-0 text-warning" />
                <p>
                    <span class="font-semibold">Dit is een demo.</span> Er wordt geen geld afgeschreven en er is geen bank bij betrokken. Wat je hier
                    kiest, is wat de app straks als uitkomst ziet.
                </p>
            </div>

            <div class="mt-4 overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
                <div class="flex items-center gap-3 border-b border-border bg-secondary/60 px-5 py-4">
                    <span class="flex size-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        <Landmark class="size-5" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs uppercase tracking-widest text-muted-foreground">Betalen aan</p>
                        <p class="truncate font-semibold">{{ school.name }}</p>
                    </div>
                </div>

                <div class="p-5">
                    <template v-if="payment.paid">
                        <div class="flex items-center gap-3">
                            <CheckCircle2 class="size-6 text-primary" />
                            <p class="font-semibold">Deze betaling is al voldaan</p>
                        </div>
                    </template>

                    <template v-else>
                        <p class="text-sm text-muted-foreground">
                            {{ payment.description }}<template v-if="payment.player"> · voor {{ payment.player }}</template>
                        </p>
                        <p class="tabular mt-1 text-3xl font-bold">{{ payment.amount }}</p>

                        <p v-if="mandate" class="mt-3 flex items-start gap-2 rounded-lg bg-secondary p-3 text-xs text-muted-foreground">
                            <ShieldCheck class="mt-0.5 size-4 shrink-0 text-primary" />
                            <span>
                                Met deze eerste betaling geef je ook een machtiging voor automatische incasso van de volgende termijnen. In de demo
                                is dat een nagebootst mandaat.
                            </span>
                        </p>

                        <p class="mt-5 text-sm font-medium">Kies je bank</p>
                        <div class="mt-2 grid grid-cols-2 gap-2">
                            <button
                                v-for="b in banks"
                                :key="b"
                                type="button"
                                class="min-h-11 rounded-lg border px-3 text-sm transition"
                                :class="bank === b ? 'border-primary bg-primary/10 font-medium text-primary' : 'border-border hover:border-primary'"
                                :aria-pressed="bank === b"
                                @click="bank = b"
                            >
                                {{ b }}
                            </button>
                        </div>

                        <div class="mt-5 grid gap-2">
                            <button
                                type="button"
                                class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-primary text-sm font-semibold text-primary-foreground transition hover:opacity-90 disabled:opacity-60"
                                :disabled="bezig !== null || bank === null"
                                @click="rondAf('paid')"
                            >
                                <LoaderCircle v-if="bezig === 'paid'" class="size-4 animate-spin" />
                                Betaal {{ payment.amount }} (demo)
                            </button>
                            <p v-if="bank === null" class="text-center text-xs text-muted-foreground">Kies eerst een bank, net als bij iDEAL.</p>

                            <div class="mt-1 grid grid-cols-2 gap-2">
                                <button
                                    type="button"
                                    class="min-h-11 rounded-lg border border-border text-sm font-medium transition hover:border-primary disabled:opacity-60"
                                    :disabled="bezig !== null"
                                    @click="rondAf('cancelled')"
                                >
                                    Annuleren
                                </button>
                                <button
                                    type="button"
                                    class="min-h-11 rounded-lg border border-border text-sm text-muted-foreground transition hover:border-warning disabled:opacity-60"
                                    :disabled="bezig !== null"
                                    @click="rondAf('failed')"
                                >
                                    Laat mislukken
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <p class="mt-4 text-center text-xs text-muted-foreground">Demo-betaalscherm van PlayerPath. Bij een echte koppeling staat hier iDEAL.</p>
        </div>
    </div>
</template>
