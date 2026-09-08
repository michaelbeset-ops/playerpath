<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import PlatformLayout from '@/layouts/PlatformLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Eye, Pencil, Power, PowerOff, Trash2, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    school: {
        id: number;
        name: string;
        slug: string;
        is_active: boolean;
        players_count: number;
        trainers_count: number;
        users_count: number;
        brand_color: string | null;
        contact_name: string | null;
        contact_email: string | null;
        contact_phone: string | null;
        notes: string | null;
        created_at: string | null;
    };
    owners: { id: number; name: string; email: string }[];
    features: { key: string; label: string; description: string; enabled: boolean }[];
    package: string | null;
    packages: { value: string; label: string; description: string; price: string; features: string[] }[];
    deletes: { users: number; players: number; reports: number; trainings: number; payments: number; enrollments: number };
    domain: string | null;
}>();

const functies = useForm({
    features: Object.fromEntries(props.features.map((f) => [f.key, f.enabled])) as Record<string, boolean>,
});

const bewaarFuncties = () => functies.patch('/beheer/scholen/' + props.school.id + '/functies', { preserveScroll: true });

const bekijkAls = (id: number) => {
    if (confirm('Je gaat de app bekijken als deze gebruiker. Dit wordt vastgelegd.')) {
        router.post('/beheer/gebruikers/' + id + '/bekijken');
    }
};

// Wijkt deze school af van wat zijn pakket normaal aanzet? Dat is geen fout,
// maar je wilt het wel zien voordat je je afvraagt waarom iets ontbreekt.
const pakket = computed(() => props.packages.find((p) => p.value === props.package) ?? null);

const afwijkend = computed(() => {
    if (pakket.value === null) {
        return false;
    }

    return props.features.some((f) => f.enabled !== pakket.value!.features.includes(f.label));
});

const toonVerwijderen = ref(false);
const verwijderForm = useForm({ confirm: '' });

const verwijder = () => verwijderForm.delete('/beheer/scholen/' + props.school.id);

const wissel = () => {
    const vraag = props.school.is_active
        ? `${props.school.name} uitzetten? Niemand van die school kan dan nog inloggen.`
        : `${props.school.name} weer aanzetten?`;

    if (confirm(vraag)) {
        router.patch('/beheer/scholen/' + props.school.id + '/status', {}, { preserveScroll: true });
    }
};
</script>

<template>
    <Head :title="school.name" />

    <PlatformLayout>
        <Link href="/beheer/scholen" class="text-sm text-muted-foreground underline underline-offset-4">Terug naar scholen</Link>

        <div class="mt-2 flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <h1 class="flex flex-wrap items-center gap-2 text-2xl font-semibold tracking-tight">
                    {{ school.name }}
                    <span v-if="!school.is_active" class="rounded bg-secondary px-2 py-0.5 text-xs font-medium text-muted-foreground">
                        inactief
                    </span>
                </h1>
                <p class="text-sm text-muted-foreground">
                    <template v-if="domain">{{ school.slug }}.{{ domain }}</template>
                    <template v-else>{{ school.slug }}</template>
                    <span v-if="school.created_at"> &middot; sinds {{ school.created_at }}</span>
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <Link
                    :href="'/beheer/scholen/' + school.id + '/bewerken'"
                    class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-border bg-card px-4 text-sm font-medium shadow-sm transition hover:border-primary"
                >
                    <Pencil class="size-4" />
                    Bewerken
                </Link>

                <Link
                    :href="'/beheer/scholen/' + school.id + '/gebruikers'"
                    class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-border bg-card px-4 text-sm font-medium shadow-sm transition hover:border-primary"
                >
                    <Users class="size-4" />
                    Gebruikers
                </Link>

                <Button :variant="school.is_active ? 'secondary' : 'default'" @click="wissel">
                    <component :is="school.is_active ? PowerOff : Power" class="mr-2 size-4" />
                    {{ school.is_active ? 'Uitzetten' : 'Aanzetten' }}
                </Button>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
                <p class="tabular text-2xl font-bold text-primary">{{ school.players_count }}</p>
                <p class="text-xs text-muted-foreground">actieve spelers</p>
            </div>
            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
                <p class="tabular text-2xl font-bold text-primary">{{ school.trainers_count }}</p>
                <p class="text-xs text-muted-foreground">trainers</p>
            </div>
            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
                <p class="tabular text-2xl font-bold">{{ school.users_count }}</p>
                <p class="text-xs text-muted-foreground">accounts totaal</p>
            </div>
            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
                <div class="flex items-center gap-2">
                    <span
                        class="size-5 shrink-0 rounded border border-border"
                        :style="school.brand_color ? { backgroundColor: school.brand_color } : {}"
                    ></span>
                    <p class="truncate text-sm font-medium">{{ school.brand_color ?? 'Standaard' }}</p>
                </div>
                <p class="mt-1 text-xs text-muted-foreground">merkkleur</p>
            </div>
        </div>

        <p v-if="pakket" class="mt-3 text-sm text-muted-foreground">
            Pakket <span class="font-medium text-foreground">{{ pakket.label }}</span> &middot; {{ pakket.price }} per maand
            <span v-if="afwijkend" class="text-warning"> &middot; wijkt af van het pakket</span>
        </p>
        <p v-else class="mt-3 text-sm text-muted-foreground">Geen pakket gekozen; de functies staan los ingesteld.</p>

        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            <div class="rounded-xl border border-border bg-card p-5 shadow-sm">
                <p class="font-medium">Eigenaren</p>

                <div v-if="owners.length" class="mt-3 space-y-2">
                    <div
                        v-for="eigenaar in owners"
                        :key="eigenaar.id"
                        class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-border p-3"
                    >
                        <div class="min-w-0">
                            <p class="text-sm font-medium">{{ eigenaar.name }}</p>
                            <p class="truncate text-xs text-muted-foreground">{{ eigenaar.email }}</p>
                        </div>

                        <button
                            type="button"
                            class="inline-flex h-8 shrink-0 items-center gap-1.5 rounded-lg border border-border px-2.5 text-xs font-medium hover:border-primary"
                            @click="bekijkAls(eigenaar.id)"
                        >
                            <Eye class="size-3.5" />
                            Bekijk als
                        </button>
                    </div>
                </div>

                <p v-else class="mt-3 rounded-lg border border-warning/30 bg-warning/10 p-3 text-sm">
                    Deze school heeft nog geen eigenaar. Er kan dus niemand inloggen.
                </p>
            </div>

            <div class="rounded-xl border border-border bg-card p-5 shadow-sm">
                <p class="font-medium">Functies</p>
                <p class="mt-1 text-sm text-muted-foreground">Wat je hier uitzet is voor deze school ook echt dicht, niet alleen verborgen.</p>

                <form class="mt-3 space-y-2" @submit.prevent="bewaarFuncties">
                    <label
                        v-for="functie in features"
                        :key="functie.key"
                        class="flex cursor-pointer items-start gap-3 rounded-lg border border-border p-3"
                    >
                        <input
                            v-model="functies.features[functie.key]"
                            type="checkbox"
                            class="mt-0.5 size-4 shrink-0 rounded border-input accent-primary"
                        />
                        <span class="min-w-0">
                            <span class="block text-sm font-medium">{{ functie.label }}</span>
                            <span class="block text-xs text-muted-foreground">{{ functie.description }}</span>
                        </span>
                    </label>

                    <Button type="submit" size="sm" :disabled="functies.processing">Functies opslaan</Button>
                </form>
            </div>

            <div class="rounded-xl border border-border bg-card p-5 shadow-sm">
                <p class="font-medium">Contact</p>

                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex gap-2">
                        <dt class="w-28 shrink-0 text-muted-foreground">Contactpersoon</dt>
                        <dd class="min-w-0">{{ school.contact_name ?? '—' }}</dd>
                    </div>
                    <div class="flex gap-2">
                        <dt class="w-28 shrink-0 text-muted-foreground">E-mail</dt>
                        <dd class="min-w-0 break-words">{{ school.contact_email ?? '—' }}</dd>
                    </div>
                    <div class="flex gap-2">
                        <dt class="w-28 shrink-0 text-muted-foreground">Telefoon</dt>
                        <dd class="min-w-0">{{ school.contact_phone ?? '—' }}</dd>
                    </div>
                </dl>

                <p v-if="school.notes" class="mt-3 whitespace-pre-line rounded-lg bg-secondary px-3 py-2 text-sm">{{ school.notes }}</p>
            </div>
        </div>

        <!-- Onomkeerbaar, dus onderaan en met de naam als sleutel -->
        <div class="mt-6 rounded-xl border border-destructive/25 bg-destructive/5 p-5">
            <p class="font-medium text-destructive">School verwijderen</p>
            <p class="mt-1 text-sm">
                Alles van {{ school.name }} verdwijnt definitief: <span class="tabular font-medium">{{ deletes.users }}</span> accounts,
                <span class="tabular font-medium">{{ deletes.players }}</span> spelers,
                <span class="tabular font-medium">{{ deletes.reports }}</span> rapporten,
                <span class="tabular font-medium">{{ deletes.trainings }}</span> trainingen,
                <span class="tabular font-medium">{{ deletes.payments }}</span> betalingen en
                <span class="tabular font-medium">{{ deletes.enrollments }}</span> inschrijvingen. Niemand van deze school kan daarna nog inloggen.
            </p>
            <p class="mt-1 text-xs text-muted-foreground">
                Wil je ze alleen tijdelijk buitensluiten? Gebruik dan "Uitzetten" hierboven; dan blijven de gegevens staan.
            </p>

            <template v-if="!toonVerwijderen">
                <Button variant="destructive" class="mt-4" @click="toonVerwijderen = true">
                    <Trash2 class="mr-2 size-4" />
                    Definitief verwijderen
                </Button>
            </template>

            <form v-else class="mt-4" @submit.prevent="verwijder">
                <label for="confirm" class="block text-sm font-medium">
                    Typ <span class="font-semibold">{{ school.name }}</span> over om te bevestigen
                </label>
                <Input id="confirm" v-model="verwijderForm.confirm" class="mt-2 max-w-sm" autocomplete="off" />
                <InputError class="mt-2" :message="verwijderForm.errors.confirm" />

                <div class="mt-3 flex items-center gap-3">
                    <Button type="submit" variant="destructive" :disabled="verwijderForm.processing || verwijderForm.confirm !== school.name">
                        Ja, verwijder alles
                    </Button>
                    <button type="button" class="text-sm text-muted-foreground underline underline-offset-4" @click="toonVerwijderen = false">
                        Annuleren
                    </button>
                </div>
            </form>
        </div>
    </PlatformLayout>
</template>
