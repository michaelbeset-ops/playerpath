<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import ToggleSwitch from '@/components/ToggleSwitch.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { Award, Check, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

/**
 * Welke mijlpalen gelden: voor iedereen, en per leeftijdscategorie waar je
 * wilt afwijken. Standaard vier; een lijst van negen is te lang.
 */
interface Badge {
    key: string;
    label: string;
    description: string;
}

const props = defineProps<{
    catalogue: Badge[];
    defaultKeys: string[];
    categories: { key: string; label: string }[];
    overrides: Record<string, string[]>;
    standard: string[];
    /** De mijlpalen die deze school zelf heeft bedacht. */
    custom: { key: string; label: string; description: string }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Mijlpalen', href: '/mijlpalen' }];

const form = useForm({
    default: [...props.defaultKeys] as string[],
    overrides: Object.fromEntries(Object.entries(props.overrides ?? {}).map(([k, v]) => [k, [...v]])) as Record<string, string[]>,
    custom: props.custom.map((b) => ({ ...b })) as { key: string | null; label: string; description: string }[],
});

// Eigen mijlpalen: een naam die je zelf typt. De sleutel komt van de server
// bij het opslaan, zodat een toekenning aan de mijlpaal blijft hangen als je
// hem later hernoemt.
const voegEigenToe = () => form.custom.push({ key: null, label: '', description: '' });
const haalEigenWeg = (i: number) => form.custom.splice(i, 1);
const eigenFout = (i: number, veld: 'label' | 'description') => (form.errors as Record<string, string>)[`custom.${i}.${veld}`];

const wissel = (lijst: string[], key: string) => {
    const i = lijst.indexOf(key);
    if (i === -1) lijst.push(key);
    else lijst.splice(i, 1);
};

// Per categorie: afwijken of de standaard volgen.
const gekozenCategorie = ref<string>(props.categories[0]?.key ?? 'O12');
const wijktAf = computed({
    get: () => gekozenCategorie.value in form.overrides,
    set: (aan: boolean) => {
        if (aan) {
            form.overrides[gekozenCategorie.value] = [...form.default];
        } else {
            delete form.overrides[gekozenCategorie.value];
        }
    },
});

const herstel = () => (form.default = [...props.standard]);
const opslaan = () => form.patch('/mijlpalen', { preserveScroll: true });
</script>

<template>
    <Head title="Mijlpalen" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl p-4 pb-28">

            <div class="flex items-start gap-3">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-gold/15 text-gold">
                    <Award class="size-5" />
                </span>
                <div class="min-w-0">
                    <h1 class="text-2xl font-semibold tracking-tight">Mijlpalen</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Wat een speler kan behalen, op de kaart en in de tijdlijn. Vier is een goed aantal: haalbaar, en elke badge betekent iets.
                    </p>
                </div>
            </div>

            <form id="mijlpalen" class="mt-6 space-y-4" @submit.prevent="opslaan">
                <section class="rounded-xl border border-border bg-card p-5 shadow-sm">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <p class="font-medium">Voor alle spelers</p>
                        <button
                            type="button"
                            class="inline-flex min-h-11 items-center text-xs text-muted-foreground underline underline-offset-4"
                            @click="herstel"
                        >
                            Terug naar de standaard
                        </button>
                    </div>
                    <p class="mt-1 text-sm text-muted-foreground">{{ form.default.length }} van {{ catalogue.length }} aan.</p>

                    <div class="mt-4 grid gap-2">
                        <button
                            v-for="badge in catalogue"
                            :key="badge.key"
                            type="button"
                            class="flex min-h-11 items-start gap-3 rounded-xl border p-3 text-left transition"
                            :class="form.default.includes(badge.key) ? 'border-gold/60 bg-gold/10' : 'border-border hover:border-primary/40'"
                            :aria-pressed="form.default.includes(badge.key)"
                            @click="wissel(form.default, badge.key)"
                        >
                            <span
                                class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-md border"
                                :class="form.default.includes(badge.key) ? 'border-gold bg-gold text-gold-foreground' : 'border-border'"
                            >
                                <Check v-if="form.default.includes(badge.key)" class="size-3.5" />
                            </span>
                            <span class="min-w-0">
                                <span class="block text-sm font-medium">{{ badge.label }}</span>
                                <span class="block text-xs text-muted-foreground">{{ badge.description }}</span>
                            </span>
                        </button>
                    </div>
                    <InputError class="mt-2" :message="form.errors.default" />
                </section>

                <!-- Eigen mijlpalen: wat de school zelf belangrijk vindt en wat
                     nergens uit af te leiden is. De trainer kent ze toe op de
                     pagina van de speler. -->
                <section class="rounded-xl border border-border bg-card p-5 shadow-sm">
                    <p class="font-medium">Eigen mijlpalen</p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Iets wat jouw school viert en wat de app niet kan meten: "Eerste wedstrijd gekeept", "Strafschop gestopt". Je kent ze zelf toe
                        op de pagina van een speler; ze komen op de kaart en in de tijdlijn.
                    </p>

                    <div v-if="form.custom.length" class="mt-4 space-y-3">
                        <div v-for="(badge, i) in form.custom" :key="badge.key ?? 'nieuw-' + i" class="rounded-xl border border-border p-3">
                            <div class="flex items-start gap-2">
                                <div class="min-w-0 flex-1 space-y-2">
                                    <div>
                                        <label :for="'eigen-naam-' + i" class="sr-only">Naam van mijlpaal {{ i + 1 }}</label>
                                        <input
                                            :id="'eigen-naam-' + i"
                                            v-model="badge.label"
                                            type="text"
                                            maxlength="40"
                                            placeholder="Naam, bijvoorbeeld: Eerste wedstrijd gekeept"
                                            class="min-h-11 w-full rounded-lg border border-input bg-background px-3 text-sm font-medium outline-none focus:border-primary"
                                        />
                                        <InputError class="mt-1" :message="eigenFout(i, 'label')" />
                                    </div>
                                    <div>
                                        <label :for="'eigen-omschrijving-' + i" class="sr-only">Omschrijving van mijlpaal {{ i + 1 }}</label>
                                        <input
                                            :id="'eigen-omschrijving-' + i"
                                            v-model="badge.description"
                                            type="text"
                                            maxlength="120"
                                            placeholder="Omschrijving (optioneel), bijvoorbeeld: Je eerste hele wedstrijd in het doel"
                                            class="min-h-11 w-full rounded-lg border border-input bg-background px-3 text-sm outline-none focus:border-primary"
                                        />
                                        <InputError class="mt-1" :message="eigenFout(i, 'description')" />
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    class="flex size-11 shrink-0 items-center justify-center rounded-lg text-muted-foreground transition hover:text-destructive"
                                    :aria-label="'Mijlpaal ' + (badge.label || i + 1) + ' weghalen'"
                                    @click="haalEigenWeg(i)"
                                >
                                    <Trash2 class="size-4" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="mt-4 inline-flex min-h-11 items-center gap-2 rounded-xl border border-border bg-background px-3 text-sm font-medium transition hover:border-primary"
                        @click="voegEigenToe"
                    >
                        <Plus class="size-4" />
                        Eigen mijlpaal toevoegen
                    </button>
                </section>

                <section class="rounded-xl border border-border bg-card p-5 shadow-sm">
                    <p class="font-medium">Per leeftijdscategorie</p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Een O8 hoeft niet aan dezelfde dingen te werken als een O16. Kies een categorie en wijk af waar dat helpt.
                    </p>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <button
                            v-for="c in categories"
                            :key="c.key"
                            type="button"
                            class="inline-flex min-h-11 items-center rounded-full border px-3 text-sm transition"
                            :class="gekozenCategorie === c.key ? 'border-primary bg-primary/10 font-medium text-primary' : 'border-border'"
                            @click="gekozenCategorie = c.key"
                        >
                            {{ c.label }}<span v-if="c.key in form.overrides" class="ml-1 text-xs text-gold">•</span>
                        </button>
                    </div>

                    <div class="mt-4 border-t border-border pt-2">
                        <ToggleSwitch
                            v-model="wijktAf"
                            :label="'Eigen mijlpalen voor ' + (categories.find((c) => c.key === gekozenCategorie)?.label ?? '')"
                            description="Uit: deze categorie volgt de lijst hierboven."
                        />
                    </div>

                    <div v-if="wijktAf" class="mt-3 grid gap-2">
                        <button
                            v-for="badge in catalogue"
                            :key="badge.key"
                            type="button"
                            class="flex min-h-11 items-start gap-3 rounded-xl border p-3 text-left transition"
                            :class="
                                form.overrides[gekozenCategorie]?.includes(badge.key)
                                    ? 'border-gold/60 bg-gold/10'
                                    : 'border-border hover:border-primary/40'
                            "
                            @click="wissel(form.overrides[gekozenCategorie], badge.key)"
                        >
                            <span
                                class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-md border"
                                :class="
                                    form.overrides[gekozenCategorie]?.includes(badge.key)
                                        ? 'border-gold bg-gold text-gold-foreground'
                                        : 'border-border'
                                "
                            >
                                <Check v-if="form.overrides[gekozenCategorie]?.includes(badge.key)" class="size-3.5" />
                            </span>
                            <span class="min-w-0">
                                <span class="block text-sm font-medium">{{ badge.label }}</span>
                                <span class="block text-xs text-muted-foreground">{{ badge.description }}</span>
                            </span>
                        </button>
                    </div>
                </section>
            </form>
        </div>

        <div class="fixed inset-x-0 bottom-[var(--pp-tabbar)] z-30 border-t border-border bg-card/95 backdrop-blur">
            <div class="mx-auto flex w-full max-w-2xl items-center justify-end p-4">
                <button
                    type="submit"
                    form="mijlpalen"
                    :disabled="form.processing"
                    class="inline-flex h-11 items-center gap-2 rounded-xl bg-primary px-5 text-sm font-semibold text-primary-foreground transition hover:opacity-90 disabled:opacity-60"
                >
                    <Check class="size-4" />
                    Mijlpalen opslaan
                </button>
            </div>
        </div>
    </AppLayout>
</template>
