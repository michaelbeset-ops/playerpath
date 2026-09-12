<script setup lang="ts">
import PhotoUpload from '@/components/PhotoUpload.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { strooiConfetti } from '@/lib/confetti';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { Camera } from 'lucide-vue-next';

/**
 * Eén scherm, meteen na het activeren van een account: zet een foto op de
 * kaart. Per kind (of jezelf) een kiezer; klaar is klaar, en "later" mag
 * altijd. Zolang de foto er niet is, blijft de herinnering op het dashboard
 * en op de kaart staan — dus hier hoeft niets afgedwongen te worden.
 */
defineProps<{
    players: { id: number; name: string; first_name: string; photo: string | null }[];
    self: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Foto', href: '/welkom/foto' },
];
</script>

<template>
    <Head title="Zet een foto op de kaart" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-lg p-4">
            <div class="text-center">
                <span class="mx-auto flex size-12 items-center justify-center rounded-full bg-primary/10 text-primary">
                    <Camera class="size-6" />
                </span>
                <h1 class="mt-3 text-2xl font-semibold tracking-tight">
                    {{ self ? 'Zet je foto op je kaart' : players.length === 1 ? 'Zet een foto op de kaart van ' + players[0].first_name : 'Zet een foto op de kaarten' }}
                </h1>
                <p class="mx-auto mt-2 max-w-sm text-sm text-muted-foreground">
                    {{
                        self
                            ? 'Je spelerskaart is pas echt van jou met je eigen gezicht erop. Maak een foto of kies er een uit je galerij.'
                            : 'Een spelerskaart met een gezicht erop is voor een kind de helft meer waard. Maak een foto of kies er een uit je galerij; je kunt hem uitsnijden.'
                    }}
                </p>
            </div>

            <div class="mt-6 space-y-3">
                <div v-for="speler in players" :key="speler.id" class="rounded-xl border border-border bg-card p-4 shadow-sm">
                    <p v-if="!self" class="mb-3 font-medium">{{ speler.name }}</p>
                    <PhotoUpload
                        :name="speler.name"
                        :photo="speler.photo"
                        :action="'/players/' + speler.id + '/photo'"
                        :kaart="{ first_name: speler.first_name, last_name: speler.name.slice(speler.first_name.length + 1), overall: null, position: '' }"
                        @uploaded="(eerste) => eerste && strooiConfetti()"
                    />
                </div>
            </div>

            <div class="mt-6 text-center">
                <Link href="/dashboard" class="inline-flex min-h-11 items-center px-3 text-sm font-medium text-muted-foreground underline underline-offset-4">
                    Later doen
                </Link>
                <p class="mt-1 text-xs text-muted-foreground">Je vindt de knop "Foto toevoegen" terug op je dashboard en op de kaart.</p>
            </div>
        </div>
    </AppLayout>
</template>
