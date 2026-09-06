<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, useForm } from '@inertiajs/vue3';
import { CheckCircle2, LoaderCircle } from 'lucide-vue-next';

/**
 * Het openbare inschrijfformulier. Geen inlog, geen app-schil: de ouder
 * ziet alleen de school, het formulier en een bevestiging.
 *
 * Eén kolom, grote velden, alles op mobiel in te vullen met de duim.
 */
const props = defineProps<{
    school: { name: string; slug: string };
    products: { id: number; name: string; description: string | null; amount: string; interval: string }[];
    positions: Record<string, string>;
    methods: Record<string, string>;
    submitted: boolean;
}>();

const form = useForm({
    first_name: '',
    last_name: '',
    date_of_birth: '',
    position: 'keeper',
    guardian_name: '',
    guardian_email: '',
    guardian_phone: '',
    relationship: '',
    product_id: props.products[0]?.id ?? null,
    payment_method: 'directdebit',
    note: '',
    privacy: false,
});

const verstuur = () => form.post('/inschrijven/' + props.school.slug);
</script>

<template>
    <Head :title="'Inschrijven bij ' + school.name" />

    <div class="theme-donker min-h-svh bg-background px-4 py-8 text-foreground sm:py-12">
        <div class="mx-auto w-full max-w-lg">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-xl bg-primary text-primary-foreground">
                    <AppLogoIcon class="size-6" />
                </div>
                <div>
                    <p class="text-xs uppercase tracking-widest text-muted-foreground">Inschrijven bij</p>
                    <h1 class="text-xl font-bold leading-tight">{{ school.name }}</h1>
                </div>
            </div>

            <!-- Bevestiging -->
            <div v-if="submitted" class="mt-8 rounded-2xl border border-primary/30 bg-card p-6">
                <div class="flex items-start gap-3">
                    <CheckCircle2 class="mt-0.5 size-6 shrink-0 text-primary" />
                    <div>
                        <p class="text-lg font-semibold">Inschrijving ontvangen</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Bedankt! {{ school.name }} bekijkt de inschrijving en neemt contact met je op. Zodra hij is goedgekeurd krijg je een
                            e-mail om in te loggen — daarmee zie je de spelerskaart, de trainingen en de voortgang van je kind.
                        </p>
                    </div>
                </div>
            </div>

            <form v-else class="mt-8 space-y-6" @submit.prevent="verstuur">
                <!-- Het kind -->
                <section class="rounded-2xl border border-border bg-card p-5">
                    <p class="font-semibold">Je kind</p>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="first_name">Voornaam</Label>
                            <Input id="first_name" v-model="form.first_name" required autocomplete="off" class="h-11" />
                            <InputError :message="form.errors.first_name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="last_name">Achternaam</Label>
                            <Input id="last_name" v-model="form.last_name" required autocomplete="off" class="h-11" />
                            <InputError :message="form.errors.last_name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="date_of_birth">Geboortedatum</Label>
                            <Input id="date_of_birth" v-model="form.date_of_birth" type="date" required class="h-11" />
                            <InputError :message="form.errors.date_of_birth" />
                        </div>
                        <div class="grid gap-2">
                            <Label>Positie</Label>
                            <div class="grid grid-cols-2 gap-2">
                                <button
                                    v-for="(label, waarde) in positions"
                                    :key="waarde"
                                    type="button"
                                    class="h-11 rounded-lg border text-sm font-medium transition"
                                    :class="
                                        form.position === waarde ? 'border-primary bg-primary/15 text-primary' : 'border-border text-muted-foreground'
                                    "
                                    @click="form.position = waarde"
                                >
                                    {{ label }}
                                </button>
                            </div>
                            <InputError :message="form.errors.position" />
                        </div>
                    </div>
                </section>

                <!-- De ouder -->
                <section class="rounded-2xl border border-border bg-card p-5">
                    <p class="font-semibold">Jijzelf</p>
                    <p class="mt-0.5 text-xs text-muted-foreground">Met dit e-mailadres log je straks in om alles van je kind te volgen.</p>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="guardian_name">Naam</Label>
                            <Input id="guardian_name" v-model="form.guardian_name" required autocomplete="name" class="h-11" />
                            <InputError :message="form.errors.guardian_name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="relationship">Relatie <span class="text-muted-foreground">(optioneel)</span></Label>
                            <Input id="relationship" v-model="form.relationship" placeholder="moeder, vader, verzorger" class="h-11" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="guardian_email">E-mailadres</Label>
                            <Input id="guardian_email" v-model="form.guardian_email" type="email" required autocomplete="email" class="h-11" />
                            <InputError :message="form.errors.guardian_email" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="guardian_phone">Telefoon <span class="text-muted-foreground">(optioneel)</span></Label>
                            <Input id="guardian_phone" v-model="form.guardian_phone" type="tel" autocomplete="tel" class="h-11" />
                            <InputError :message="form.errors.guardian_phone" />
                        </div>
                    </div>
                </section>

                <!-- Het tarief -->
                <section v-if="products.length" class="rounded-2xl border border-border bg-card p-5">
                    <p class="font-semibold">Abonnement</p>
                    <p class="mt-0.5 text-xs text-muted-foreground">De school bevestigt dit bij de goedkeuring. Er wordt nu nog niets betaald.</p>

                    <div class="mt-4 space-y-2">
                        <button
                            v-for="product in products"
                            :key="product.id"
                            type="button"
                            class="flex w-full items-center justify-between gap-3 rounded-xl border p-4 text-left transition"
                            :class="form.product_id === product.id ? 'border-primary bg-primary/10' : 'border-border'"
                            @click="form.product_id = product.id"
                        >
                            <div class="min-w-0">
                                <p class="font-medium">{{ product.name }}</p>
                                <p v-if="product.description" class="text-xs text-muted-foreground">{{ product.description }}</p>
                            </div>
                            <p class="tabular shrink-0 text-right">
                                <span class="font-bold">{{ product.amount }}</span>
                                <span class="block text-xs text-muted-foreground">{{ product.interval.toLowerCase() }}</span>
                            </p>
                        </button>
                    </div>
                    <InputError :message="form.errors.product_id" />

                    <div class="mt-4 grid gap-2">
                        <Label>Betaalmethode</Label>
                        <div class="grid grid-cols-3 gap-2">
                            <button
                                v-for="(label, waarde) in methods"
                                :key="waarde"
                                type="button"
                                class="h-11 rounded-lg border text-xs font-medium transition sm:text-sm"
                                :class="
                                    form.payment_method === waarde
                                        ? 'border-primary bg-primary/15 text-primary'
                                        : 'border-border text-muted-foreground'
                                "
                                @click="form.payment_method = waarde"
                            >
                                {{ label }}
                            </button>
                        </div>
                    </div>
                </section>

                <section class="rounded-2xl border border-border bg-card p-5">
                    <Label for="note">Opmerking <span class="text-muted-foreground">(optioneel)</span></Label>
                    <textarea
                        id="note"
                        v-model="form.note"
                        rows="3"
                        class="mt-2 w-full rounded-lg border border-input bg-background p-3 text-sm outline-none focus:border-primary"
                        placeholder="Bijvoorbeeld: speelt al bij een club, of wil graag op zaterdag trainen."
                    ></textarea>

                    <label class="mt-4 flex items-start gap-3 text-sm">
                        <input v-model="form.privacy" type="checkbox" class="mt-0.5 size-4 accent-[hsl(var(--primary))]" />
                        <span>
                            Ik ga ermee akkoord dat {{ school.name }} deze gegevens gebruikt om de inschrijving af te handelen en contact met me op te
                            nemen.
                        </span>
                    </label>
                    <InputError :message="form.errors.privacy" />
                </section>

                <Button type="submit" size="lg" class="w-full text-base" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="mr-2 size-4 animate-spin" />
                    Inschrijving versturen
                </Button>
            </form>

            <p class="mt-8 text-center text-xs text-muted-foreground">Inschrijven via PlayerPath</p>
        </div>
    </div>
</template>
