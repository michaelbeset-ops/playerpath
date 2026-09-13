<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ExternalLink } from 'lucide-vue-next';

/**
 * Je eigen inschrijfpagina, zoals een ouder hem ziet: de echte pagina in een
 * kader, met uitleg ernaast. Geen nagebouwde versie: wat hier staat is wat
 * een ouder krijgt als je de link deelt.
 */
const props = defineProps<{
    url: string;
    school: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Je inschrijfpagina', href: '/onboarding/aanmeldpagina' },
];
</script>

<template>
    <Head title="Je inschrijfpagina" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-5xl p-4">
            <div class="rounded-xl border border-primary/30 bg-primary/5 p-4 text-sm">
                <p class="font-medium">Zo schrijft een ouder zich in</p>
                <p class="mt-1 text-muted-foreground">
                    Dit is de echte inschrijfpagina van {{ school }}. Een ouder kiest een aanbod, vult de gegevens van zijn kind in, kiest hoe hij
                    betaalt (online of contant bij de school) en bevestigt. De aanmelding komt daarna bij jou binnen onder Klanten, waar je hem
                    goedkeurt.
                </p>
                <div class="mt-3 flex flex-wrap gap-3">
                    <a
                        :href="props.url"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-border bg-card px-3 text-sm font-medium transition hover:border-primary"
                    >
                        <ExternalLink class="size-4" />
                        Open in een nieuw tabblad
                    </a>
                    <Link href="/dashboard" class="inline-flex min-h-11 items-center text-sm font-medium text-primary underline underline-offset-4">
                        Terug naar mijn dashboard
                    </Link>
                </div>
            </div>

            <div class="mt-4 overflow-hidden rounded-2xl border border-border bg-card shadow-sm" data-tour="enroll-page">
                <div class="flex items-center gap-2 border-b border-border bg-secondary/60 px-4 py-2 text-xs text-muted-foreground">
                    <span class="size-2.5 rounded-full bg-border"></span>
                    <span class="size-2.5 rounded-full bg-border"></span>
                    <span class="size-2.5 rounded-full bg-border"></span>
                    <span class="ml-2 truncate">{{ props.url }}</span>
                </div>
                <iframe :src="props.url" title="Je inschrijfpagina" class="block h-[75vh] min-h-[32rem] w-full bg-background"></iframe>
            </div>
        </div>
    </AppLayout>
</template>
