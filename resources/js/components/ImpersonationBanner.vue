<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Eye } from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * De balk die laat zien dat je als iemand anders kijkt.
 *
 * Bewust bovenaan, bewust in een kleur die nergens anders in de app voorkomt,
 * en bewust altijd zichtbaar - niet weg te klikken. Wie in andermans gegevens
 * kijkt moet daar op elk scherm aan herinnerd worden, anders wijzig je een
 * keer iets in de veronderstelling dat je in je eigen omgeving zit.
 */
const page = usePage<{ impersonating: { name: string | null; school: string | null } | null }>();

const bezig = computed(() => page.props.impersonating);

const terug = () => router.post('/stop-bekijken');
</script>

<template>
    <div v-if="bezig" class="sticky top-0 z-50 bg-warning text-warning-foreground">
        <div class="mx-auto flex w-full max-w-7xl flex-wrap items-center justify-between gap-2 px-4 py-2">
            <p class="flex min-w-0 items-center gap-2 text-sm">
                <Eye class="size-4 shrink-0" />
                <span class="truncate">
                    Je bekijkt de app als <span class="font-semibold">{{ bezig.name }}</span>
                    <template v-if="bezig.school"> van {{ bezig.school }}</template>
                </span>
            </p>

            <button
                type="button"
                class="h-8 shrink-0 rounded-lg bg-warning-foreground px-3 text-xs font-semibold text-warning transition hover:opacity-90"
                @click="terug"
            >
                Terug naar beheer
            </button>
        </div>
    </div>
</template>
