<script setup lang="ts">
import AppTabbar from '@/components/AppTabbar.vue';
import AppTopbar from '@/components/AppTopbar.vue';
import AppTour from '@/components/onboarding/AppTour.vue';
import DemoBanner from '@/components/onboarding/DemoBanner.vue';
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from '@/components/ui/breadcrumb';
import type { BreadcrumbItemType, SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/vue3';
import { ChevronLeft } from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

const props = withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage<SharedData>();

/*
 * De weg terug. Op een telefoon is er geen broodkruimelpad en geen menu in
 * beeld, dus een scherm dat geen hoofdtabblad is heeft een knop linksboven
 * nodig — anders loop je vast.
 *
 * Twee gevallen. Heeft de pagina een pad van twee of meer kruimels, dan is de
 * weg terug de kruimel ervoor, met naam. Is het één kruimel maar staat het
 * adres niet in het menu (de kaart van een speler, de meldingen achter het
 * belletje), dan gaat de knop een stap terug in de geschiedenis, en naar het
 * dashboard als die er niet is.
 */
const pad = computed(() => (page.url ?? '').split('?')[0]);

const hoofdschermen = computed(() => {
    const nav = page.props.nav ?? [];
    const hrefs = nav.flatMap((groep) => [groep.href, ...groep.items.map((item) => item.href)]).filter((h): h is string => !!h);

    return new Set(['/dashboard', ...hrefs]);
});

const terug = computed<{ href: string | null; label: string } | null>(() => {
    if (props.breadcrumbs.length > 1) {
        const vorige = props.breadcrumbs[props.breadcrumbs.length - 2];

        return { href: vorige.href, label: vorige.title };
    }

    if (hoofdschermen.value.has(pad.value)) {
        return null;
    }

    return { href: null, label: 'Terug' };
});

const gaTerug = () => {
    if (window.history.length > 1) {
        window.history.back();
    } else {
        router.visit('/dashboard');
    }
};

// De balk boven de voorbeelddata hoort op elke pagina: het label bij een rij
// zegt wát er nep is, deze balk zegt hoe je ervan af komt.
const toonDemo = computed(() => page.props.onboarding?.demo === true);
</script>

<template>
    <!-- min-w-0 zodat één brede tabel de hele pagina niet zijwaarts laat
         schuiven; zie CLAUDE.md over mobiel. -->
    <div class="flex min-h-screen w-full min-w-0 flex-col bg-background text-foreground">
        <AppTopbar />

        <!-- Op een telefoon: één knop terug, met de naam van waar je vandaan
             komt. Het hele pad staat er op een groot scherm. -->
        <div v-if="terug" class="border-b border-border bg-card/40" :class="breadcrumbs.length > 1 ? 'sm:hidden' : ''">
            <div class="mx-auto w-full max-w-6xl px-2">
                <component
                    :is="terug.href ? Link : 'button'"
                    :href="terug.href ?? undefined"
                    :type="terug.href ? undefined : 'button'"
                    class="inline-flex min-h-11 max-w-full items-center gap-1 pr-3 text-sm font-medium text-foreground transition hover:text-primary"
                    @click="terug.href ? undefined : gaTerug()"
                >
                    <ChevronLeft class="size-5 shrink-0" aria-hidden="true" />
                    <span class="truncate">{{ terug.label }}</span>
                </component>
            </div>
        </div>

        <div v-if="breadcrumbs.length > 1" class="hidden border-b border-border bg-card/40 sm:block">
            <div class="mx-auto w-full max-w-6xl px-4 py-2">
                <Breadcrumb>
                    <BreadcrumbList>
                        <template v-for="(item, index) in breadcrumbs" :key="index">
                            <BreadcrumbItem>
                                <BreadcrumbPage v-if="index === breadcrumbs.length - 1">{{ item.title }}</BreadcrumbPage>
                                <BreadcrumbLink v-else :href="item.href">{{ item.title }}</BreadcrumbLink>
                            </BreadcrumbItem>
                            <BreadcrumbSeparator v-if="index !== breadcrumbs.length - 1" />
                        </template>
                    </BreadcrumbList>
                </Breadcrumb>
            </div>
        </div>

        <!-- pb-[var(--pp-tabbar)]: buiten de app is die nul, dus dit doet daar
             niets. Als app houdt het de laatste knop van een pagina vrij van de
             tabbalk in plaats van eronder. -->
        <div v-if="toonDemo" class="mx-auto w-full max-w-6xl px-4 pt-4">
            <DemoBanner />
        </div>

        <main class="min-w-0 flex-1 pb-[var(--pp-tabbar)]">
            <slot />
        </main>

        <!-- De rondleiding. Staat in de schil omdat hij naar de balk wijst. -->
        <AppTour />

        <!-- Het menu onderin, alleen als de app als app draait. -->
        <AppTabbar />
    </div>
</template>
