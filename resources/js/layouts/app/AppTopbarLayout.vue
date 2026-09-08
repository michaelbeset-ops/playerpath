<script setup lang="ts">
import AppTabbar from '@/components/AppTabbar.vue';
import AppTopbar from '@/components/AppTopbar.vue';
import AppTour from '@/components/onboarding/AppTour.vue';
import DemoBanner from '@/components/onboarding/DemoBanner.vue';
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from '@/components/ui/breadcrumb';
import type { BreadcrumbItemType, SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage<SharedData>();

// De balk boven de voorbeelddata hoort op elke pagina: het label bij een rij
// zegt wát er nep is, deze balk zegt hoe je ervan af komt.
const toonDemo = computed(() => page.props.onboarding?.demo === true);
</script>

<template>
    <!-- min-w-0 zodat één brede tabel de hele pagina niet zijwaarts laat
         schuiven; zie CLAUDE.md over mobiel. -->
    <div class="flex min-h-screen w-full min-w-0 flex-col bg-background text-foreground">
        <AppTopbar />

        <div v-if="breadcrumbs.length > 1" class="border-b border-border bg-card/40">
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
