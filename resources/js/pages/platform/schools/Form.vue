<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import PlatformLayout from '@/layouts/PlatformLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    school: {
        id: number;
        name: string;
        slug: string;
        brand_color: string | null;
        contact_name: string | null;
        contact_email: string | null;
        contact_phone: string | null;
        notes: string | null;
        package: string | null;
    } | null;
    packages: { value: string; label: string; description: string; price: string; features: string[] }[];
    domain: string | null;
}>();

const bewerken = computed(() => props.school !== null);

const form = useForm({
    name: props.school?.name ?? '',
    slug: props.school?.slug ?? '',
    brand_color: props.school?.brand_color ?? '',
    contact_name: props.school?.contact_name ?? '',
    contact_email: props.school?.contact_email ?? '',
    contact_phone: props.school?.contact_phone ?? '',
    notes: props.school?.notes ?? '',
    package: props.school?.package ?? '',
    owner_name: '',
    owner_email: '',
});

// Het adres volgt de naam zolang je hem niet zelf hebt aangepast. Bij een
// bestaande school niet: die slug is het subdomein en mag niet zomaar
// verschuiven onder de voeten van een school die al draait.
const slugAangeraakt = computed(() => bewerken.value || form.slug !== slugVan(form.name));

function slugVan(waarde: string): string {
    return waarde
        .toLowerCase()
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

const naamGewijzigd = () => {
    if (!bewerken.value && !slugAangeraakt.value) {
        form.slug = slugVan(form.name);
    }
};

const opslaan = () => (bewerken.value ? form.patch('/beheer/scholen/' + props.school!.id) : form.post('/beheer/scholen'));
</script>

<template>
    <Head :title="bewerken ? 'School bewerken' : 'Nieuwe school'" />

    <PlatformLayout>
        <Link href="/beheer/scholen" class="inline-flex min-h-11 items-center text-sm text-muted-foreground underline underline-offset-4">Terug naar scholen</Link>

        <h1 class="mt-2 text-2xl font-semibold tracking-tight">
            {{ bewerken ? school!.name + ' bewerken' : 'Nieuwe school' }}
        </h1>

        <form class="mt-4 space-y-4" @submit.prevent="opslaan">
            <div class="rounded-xl border border-border bg-card p-5 shadow-sm">
                <p class="font-medium">De school</p>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="name">Naam</Label>
                        <Input id="name" v-model="form.name" required placeholder="Keepersschool Rob" @input="naamGewijzigd" />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="slug">Adres</Label>
                        <Input id="slug" v-model="form.slug" required placeholder="keepersschool-rob" />
                        <p class="text-xs text-muted-foreground">
                            <template v-if="domain"
                                >Wordt <span class="font-medium text-foreground">{{ form.slug || '…' }}.{{ domain }}</span></template
                            >
                            <template v-else>Alleen kleine letters, cijfers en streepjes. Dit wordt later het subdomein.</template>
                        </p>
                        <InputError :message="form.errors.slug" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="brand_color">Merkkleur <span class="text-muted-foreground">(optioneel)</span></Label>
                        <div class="flex items-center gap-2">
                            <input
                                type="color"
                                :value="/^#[0-9a-fA-F]{6}$/.test(form.brand_color) ? form.brand_color : '#12813D'"
                                class="size-10 shrink-0 cursor-pointer rounded-lg border border-input bg-background"
                                aria-label="Kies een merkkleur"
                                @input="form.brand_color = ($event.target as HTMLInputElement).value"
                            />
                            <Input id="brand_color" v-model="form.brand_color" placeholder="#12813D" />
                        </div>
                        <InputError :message="form.errors.brand_color" />
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-border bg-card p-5 shadow-sm">
                <p class="font-medium">Pakket</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    Zet in een keer de juiste functies aan. Je kunt er daarna per school nog van afwijken.
                </p>

                <div class="mt-4 grid gap-3 sm:grid-cols-3">
                    <label
                        v-for="pakket in packages"
                        :key="pakket.value"
                        class="flex cursor-pointer flex-col gap-1 rounded-lg border p-3 transition"
                        :class="form.package === pakket.value ? 'border-primary bg-primary/5' : 'border-border hover:border-primary/40'"
                    >
                        <span class="flex items-center gap-2">
                            <input v-model="form.package" type="radio" :value="pakket.value" class="size-4 accent-primary" />
                            <span class="text-sm font-medium">{{ pakket.label }}</span>
                        </span>
                        <span class="tabular text-sm font-semibold"
                            >{{ pakket.price }}<span class="text-xs font-normal text-muted-foreground"> /mnd</span></span
                        >
                        <span class="text-xs text-muted-foreground">{{ pakket.description }}</span>
                    </label>
                </div>

                <button
                    v-if="form.package"
                    type="button"
                    class="mt-3 text-xs text-muted-foreground underline underline-offset-4"
                    @click="form.package = ''"
                >
                    Geen pakket
                </button>
                <InputError class="mt-2" :message="form.errors.package" />
            </div>

            <div class="rounded-xl border border-border bg-card p-5 shadow-sm">
                <p class="font-medium">Contactgegevens</p>
                <p class="mt-1 text-sm text-muted-foreground">Van de school zelf. Blijft staan als de eigenaar wisselt.</p>

                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    <div class="grid gap-2">
                        <Label for="contact_name">Contactpersoon</Label>
                        <Input id="contact_name" v-model="form.contact_name" />
                        <InputError :message="form.errors.contact_name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="contact_email">E-mailadres</Label>
                        <Input id="contact_email" v-model="form.contact_email" type="email" placeholder="info@school.nl" />
                        <InputError :message="form.errors.contact_email" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="contact_phone">Telefoon</Label>
                        <Input id="contact_phone" v-model="form.contact_phone" placeholder="06 12345678" />
                        <InputError :message="form.errors.contact_phone" />
                    </div>
                </div>

                <div class="mt-4 grid gap-2">
                    <Label for="notes">Aantekeningen <span class="text-muted-foreground">(alleen voor jou)</span></Label>
                    <textarea
                        id="notes"
                        v-model="form.notes"
                        rows="3"
                        maxlength="2000"
                        class="rounded-lg border border-input bg-background px-3 py-2 text-base outline-none focus:border-primary sm:text-sm"
                        placeholder="Afspraken, bijzonderheden, wat er speelt."
                    ></textarea>
                    <InputError :message="form.errors.notes" />
                </div>
            </div>

            <div v-if="!bewerken" class="rounded-xl border border-border bg-card p-5 shadow-sm">
                <p class="font-medium">Eigenaar</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    Zonder eigenaar kan er niemand inloggen. Hij krijgt een e-mail om zelf een wachtwoord te kiezen; jij bedenkt er dus geen.
                </p>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="owner_name">Naam</Label>
                        <Input id="owner_name" v-model="form.owner_name" />
                        <InputError :message="form.errors.owner_name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="owner_email">E-mailadres</Label>
                        <Input id="owner_email" v-model="form.owner_email" type="email" placeholder="naam@school.nl" />
                        <InputError :message="form.errors.owner_email" />
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="form.processing">
                    {{ bewerken ? 'Opslaan' : 'School aanmaken' }}
                </Button>
                <Link href="/beheer/scholen" class="inline-flex min-h-11 items-center text-sm text-muted-foreground underline underline-offset-4">Annuleren</Link>
            </div>
        </form>
    </PlatformLayout>
</template>
