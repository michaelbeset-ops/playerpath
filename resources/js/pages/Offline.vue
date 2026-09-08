<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Head } from '@inertiajs/vue3';
import { WifiOff } from 'lucide-vue-next';

const opnieuw = () => window.location.reload();
</script>

<template>
    <Head title="Geen verbinding" />

    <div class="theme-donker flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 text-foreground">
        <AppLogoIcon class="size-10 rounded-xl" />

        <div class="max-w-sm text-center">
            <p class="flex items-center justify-center gap-2 text-lg font-semibold">
                <WifiOff class="size-5" />
                Geen verbinding
            </p>
            <p class="mt-2 text-sm text-muted-foreground">
                We laten met opzet niets uit het geheugen zien: gegevens van spelers en betalingen horen altijd vers van de server te komen. Zodra je
                weer bereik hebt, werkt alles gewoon.
            </p>

            <button
                type="button"
                class="mt-6 inline-flex min-h-11 items-center rounded-lg bg-primary px-4 text-sm font-medium text-primary-foreground"
                @click="opnieuw"
            >
                Opnieuw proberen
            </button>
        </div>
    </div>
</template>
