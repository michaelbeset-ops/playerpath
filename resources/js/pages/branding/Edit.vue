<script setup lang="ts">
import FlashMessage from '@/components/FlashMessage.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { Globe, Palette, Upload } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    school: { name: string; slug: string; logo: string | null; brand_color: string | null };
    domain: string | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Huisstijl', href: '/branding' }];

const form = useForm({
    brand_color: props.school.brand_color ?? '',
    logo: null as File | null,
    remove_logo: false as boolean,
});

const voorbeeld = ref<string | null>(props.school.logo);

const kiesBestand = (event: Event) => {
    const bestand = (event.target as HTMLInputElement).files?.[0] ?? null;
    form.logo = bestand;
    form.remove_logo = false;
    voorbeeld.value = bestand ? URL.createObjectURL(bestand) : props.school.logo;
};

const verwijderLogo = () => {
    form.logo = null;
    form.remove_logo = true;
    voorbeeld.value = null;
};

// De knop hieronder krijgt live de gekozen kleur, zodat je meteen ziet of de
// tekst erop leesbaar blijft in plaats van dat pas na opslaan te ontdekken.
const proefKleur = computed(() => (/^#[0-9a-fA-F]{6}$/.test(form.brand_color) ? form.brand_color : null));

const opslaan = () =>
    form.post('/branding', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.logo = null;
            form.remove_logo = false;

            // De merkkleur staat als <style> in de <head>, en die schrijft
            // Inertia niet opnieuw. Zonder deze herlading sla je op, verandert
            // er zichtbaar niets, en denk je dat het niet werkt.
            window.location.reload();
        },
    });
</script>

<template>
    <Head title="Huisstijl" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-4">
            <FlashMessage />

            <div class="flex items-center gap-3">
                <Palette class="size-5 text-primary" />
                <div>
                    <h1 class="text-xl font-semibold">Huisstijl</h1>
                    <p class="text-sm text-muted-foreground">Je eigen logo en kleur, in de app en op het inschrijfformulier.</p>
                </div>
            </div>

            <form class="mt-4 space-y-4" @submit.prevent="opslaan">
                <div class="rounded-xl border border-border bg-card p-5 shadow-sm">
                    <p class="font-medium">Logo</p>
                    <p class="mt-1 text-sm text-muted-foreground">PNG, JPG, SVG of WebP, tot 1 MB. Een liggend logo werkt het best.</p>

                    <div class="mt-4 flex flex-wrap items-center gap-4">
                        <div class="flex h-16 w-40 items-center justify-center rounded-lg border border-border bg-background p-2">
                            <img v-if="voorbeeld" :src="voorbeeld" alt="Logo van de school" class="max-h-full max-w-full object-contain" />
                            <span v-else class="text-xs text-muted-foreground">Nog geen logo</span>
                        </div>

                        <label
                            class="inline-flex h-9 cursor-pointer items-center rounded-lg border border-border px-3 text-sm font-medium hover:border-primary"
                        >
                            <Upload class="mr-2 size-4" />
                            Kies een bestand
                            <input type="file" accept="image/*" class="hidden" @change="kiesBestand" />
                        </label>

                        <button
                            v-if="voorbeeld"
                            type="button"
                            class="inline-flex min-h-11 items-center text-sm text-muted-foreground underline underline-offset-4 hover:text-destructive"
                            @click="verwijderLogo"
                        >
                            Logo verwijderen
                        </button>
                    </div>
                    <InputError class="mt-2" :message="form.errors.logo" />
                </div>

                <div class="rounded-xl border border-border bg-card p-5 shadow-sm">
                    <p class="font-medium">Merkkleur</p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Dit wordt de kleur van knoppen en accenten. Statuskleuren blijven staan: een waarschuwing hoort overal hetzelfde te betekenen.
                    </p>

                    <div class="mt-4 flex flex-wrap items-end gap-4">
                        <div class="grid gap-2">
                            <Label for="brand_color">Kleurcode</Label>
                            <div class="flex items-center gap-2">
                                <input
                                    type="color"
                                    :value="proefKleur ?? '#1BB85E'"
                                    class="size-11 cursor-pointer rounded-lg border border-input bg-background"
                                    @input="form.brand_color = ($event.target as HTMLInputElement).value"
                                />
                                <Input id="brand_color" v-model="form.brand_color" placeholder="#1BB85E" class="w-36" />
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <Label>Zo ziet een knop eruit</Label>
                            <span
                                class="inline-flex min-h-11 items-center rounded-lg px-4 text-sm font-medium"
                                :style="proefKleur ? { backgroundColor: proefKleur, color: '#fff' } : {}"
                                :class="proefKleur ? '' : 'bg-primary text-primary-foreground'"
                            >
                                Rapport opslaan
                            </span>
                        </div>

                        <button
                            v-if="form.brand_color"
                            type="button"
                            class="inline-flex min-h-11 items-center text-sm text-muted-foreground underline underline-offset-4"
                            @click="form.brand_color = ''"
                        >
                            Terug naar PlayerPath-groen
                        </button>
                    </div>
                    <InputError class="mt-2" :message="form.errors.brand_color" />
                </div>

                <div v-if="domain" class="rounded-xl border border-border bg-card p-5 shadow-sm">
                    <p class="flex items-center gap-2 font-medium">
                        <Globe class="size-4 text-muted-foreground" />
                        Eigen adres
                    </p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Je school is bereikbaar op <span class="font-medium text-foreground">{{ school.slug }}.{{ domain }}</span
                        >. Daar zien bezoekers meteen jouw logo en kleur, ook voordat ze inloggen.
                    </p>
                    <p class="mt-2 text-xs text-muted-foreground">
                        Het adres bepaalt alleen hoe het eruitziet. Wat je te zien krijgt hangt af van je account, nooit van het adres.
                    </p>
                </div>

                <Button type="submit" :disabled="form.processing">Opslaan</Button>
            </form>
        </div>
    </AppLayout>
</template>
