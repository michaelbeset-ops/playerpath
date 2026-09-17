<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Eye } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

/**
 * De balk die laat zien dat je als iemand anders kijkt.
 *
 * Bewust bovenaan, bewust in een kleur die nergens anders in de app voorkomt,
 * en bewust altijd zichtbaar - niet weg te klikken. Wie in andermans gegevens
 * kijkt moet daar op elk scherm aan herinnerd worden, anders wijzig je een
 * keer iets in de veronderstelling dat je in je eigen omgeving zit.
 *
 * De balk bovenin (AppTopbar) is ook sticky. Om te voorkomen dat die twee bij
 * scrollen over elkaar schuiven, zet deze balk zijn eigen hoogte in
 * `--pp-impersonation`; de topbalk plakt daaronder.
 */
const page = usePage<{ impersonating: { name: string | null; school: string | null } | null }>();

const bezig = computed(() => page.props.impersonating);

const terug = () => router.post('/stop-bekijken');

const balk = ref<HTMLElement | null>(null);
let waarnemer: ResizeObserver | undefined;

const zetHoogte = (hoogte: number) => document.documentElement.style.setProperty('--pp-impersonation', `${hoogte}px`);

watch(
    balk,
    (element) => {
        waarnemer?.disconnect();

        if (!element) {
            document.documentElement.style.removeProperty('--pp-impersonation');
            return;
        }

        zetHoogte(element.offsetHeight);
        if (typeof ResizeObserver !== 'undefined') {
            waarnemer = new ResizeObserver(() => zetHoogte(element.offsetHeight));
            waarnemer.observe(element);
        }
    },
    { flush: 'post' },
);

onBeforeUnmount(() => {
    waarnemer?.disconnect();
    document.documentElement.style.removeProperty('--pp-impersonation');
});
</script>

<template>
    <div v-if="bezig" ref="balk" class="sticky top-0 z-50 bg-warning text-warning-foreground" data-print-hidden>
        <div class="mx-auto flex w-full max-w-7xl flex-wrap items-center justify-between gap-2 px-4 py-1">
            <p class="flex min-w-0 items-center gap-2 text-sm">
                <Eye class="size-4 shrink-0" />
                <span class="truncate">
                    Je bekijkt de app als <span class="font-semibold">{{ bezig.name }}</span>
                    <template v-if="bezig.school"> van {{ bezig.school }}</template>
                </span>
            </p>

            <button
                type="button"
                class="inline-flex min-h-11 shrink-0 items-center rounded-lg bg-warning-foreground px-3 text-xs font-semibold text-warning transition hover:opacity-90"
                @click="terug"
            >
                Terug naar beheer
            </button>
        </div>
    </div>
</template>
