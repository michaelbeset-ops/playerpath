<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Globe, Palette, Upload } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

const props = defineProps<{
    schoolInfo: { name: string; slug: string; logo: string | null; brand_color: string | null; brand_foreground: string | null };
    domain: string | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Huisstijl', href: '/branding' }];

const form = useForm({
    brand_color: props.schoolInfo.brand_color ?? '',
    logo: null as File | null,
    remove_logo: false as boolean,
});

const voorbeeld = ref<string | null>(props.schoolInfo.logo);

const kiesBestand = (event: Event) => {
    const bestand = (event.target as HTMLInputElement).files?.[0] ?? null;
    form.logo = bestand;
    form.remove_logo = false;
    voorbeeld.value = bestand ? URL.createObjectURL(bestand) : props.schoolInfo.logo;
};

const verwijderLogo = () => {
    form.logo = null;
    form.remove_logo = true;
    voorbeeld.value = null;
};

// De knop hieronder krijgt live de gekozen kleur, zodat je meteen ziet of de
// tekst erop leesbaar blijft in plaats van dat pas na opslaan te ontdekken.
const proefKleur = computed(() => (/^#[0-9a-fA-F]{6}$/.test(form.brand_color) ? form.brand_color : null));

// Dezelfde regel als BrandColor::readableForeground() op de server: wit of
// bijna-zwart, wat het meeste contrast geeft. Zo zie je geen witte tekst op geel.
const luminantie = (r: number, g: number, b: number) => {
    const kanaal = (w: number) => {
        const v = w / 255;
        return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4;
    };
    return 0.2126 * kanaal(r) + 0.7152 * kanaal(g) + 0.0722 * kanaal(b);
};
const contrast = (a: number, b: number) => (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);

const proefTekst = computed(() => {
    if (!proefKleur.value) return null;
    if (proefKleur.value.toLowerCase() === (props.schoolInfo.brand_color ?? '').toLowerCase() && props.schoolInfo.brand_foreground) {
        return { kleur: props.schoolInfo.brand_foreground, leesbaar: true };
    }
    const hex = proefKleur.value.slice(1);
    const l = luminantie(parseInt(hex.slice(0, 2), 16), parseInt(hex.slice(2, 4), 16), parseInt(hex.slice(4, 6), 16));
    const opWit = contrast(l, 1);
    const opDonker = contrast(l, luminantie(15, 23, 42));
    return { kleur: opWit >= opDonker ? '#FFFFFF' : '#0F172A', leesbaar: Math.max(opWit, opDonker) >= 4.5 };
});

// De bevestiging overleeft de herlading hieronder niet vanzelf: de melding
// hoort bij het verzoek ervoor. Daarom even bewaren en na het laden tonen.
const page = usePage<SharedData>();
const BEWAARD = 'pp-huisstijl-opgeslagen';

onMounted(() => {
    try {
        const melding = sessionStorage.getItem(BEWAARD);
        if (melding) {
            sessionStorage.removeItem(BEWAARD);
            page.props.flash = { ...(page.props.flash ?? {}), status: melding };
        }
    } catch {
        // Geen opslag beschikbaar: dan alleen geen melding.
    }
});

const opslaan = () =>
    form.post('/branding', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: (antwoord) => {
            form.logo = null;
            form.remove_logo = false;

            // De merkkleur staat als <style> in de <head>, en die schrijft
            // Inertia niet opnieuw. Zonder deze herlading sla je op, verandert
            // er zichtbaar niets, en denk je dat het niet werkt.
            try {
                const melding = (antwoord.props.flash as { status?: string } | undefined)?.status;
                if (melding) sessionStorage.setItem(BEWAARD, melding);
            } catch {
                // Geen opslag beschikbaar: dan alleen geen melding.
            }
            window.location.reload();
        },
    });
</script>

<template>
    <Head title="Huisstijl" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-4">
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
                    <p class="mt-1 text-sm text-muted-foreground">PNG, JPG of WebP, tot 1 MB. Een liggend logo werkt het best.</p>

                    <div class="mt-4 flex flex-wrap items-center gap-4">
                        <div class="flex h-16 w-40 items-center justify-center rounded-lg border border-border bg-background p-2">
                            <img v-if="voorbeeld" :src="voorbeeld" alt="Logo van de school" class="max-h-full max-w-full object-contain" />
                            <span v-else class="text-xs text-muted-foreground">Nog geen logo</span>
                        </div>

                        <label
                            class="inline-flex min-h-11 cursor-pointer items-center rounded-lg border border-border px-3 text-sm font-medium hover:border-primary"
                        >
                            <Upload class="mr-2 size-4" />
                            Kies een bestand
                            <input type="file" accept="image/png,image/jpeg,image/webp" class="hidden" @change="kiesBestand" />
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
                                    :value="proefKleur ?? '#12813D'"
                                    class="size-11 cursor-pointer rounded-lg border border-input bg-background"
                                    @input="form.brand_color = ($event.target as HTMLInputElement).value"
                                />
                                <Input id="brand_color" v-model="form.brand_color" placeholder="#12813D" class="w-36" />
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <Label>Zo ziet een knop eruit</Label>
                            <span
                                class="inline-flex min-h-11 items-center rounded-lg px-4 text-sm font-medium"
                                :style="proefKleur && proefTekst ? { backgroundColor: proefKleur, color: proefTekst.kleur } : {}"
                                :class="proefKleur ? '' : 'bg-primary text-primary-foreground'"
                            >
                                Rapport opslaan
                            </span>
                            <p v-if="proefTekst && !proefTekst.leesbaar" class="max-w-56 text-xs text-muted-foreground">
                                Deze kleur is fel; we passen de helderheid iets aan zodat de tekst op knoppen leesbaar blijft.
                            </p>
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
                        Je school is bereikbaar op <span class="font-medium text-foreground">{{ schoolInfo.slug }}.{{ domain }}</span
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
