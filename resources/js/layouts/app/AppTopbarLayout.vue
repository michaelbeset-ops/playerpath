<script setup lang="ts">
import AppTopbar from '@/components/AppTopbar.vue';
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

// Een speler zit op de donkere kant van het merk, op elk scherm. Dat staat
// hier en nergens anders: per pagina een wrapper zetten leverde precies de
// lichte tussenschermen op die het onrustig maakten.
const page = usePage<SharedData>();
const isSpeler = computed(() => {
    const rollen = page.props.auth.roles ?? [];

    return rollen.includes('speler') && !rollen.includes('ouder');
});
</script>

<template>
    <!-- min-w-0 zodat één brede tabel de hele pagina niet zijwaarts laat
         schuiven; zie CLAUDE.md over mobiel. -->
    <div class="flex min-h-screen w-full min-w-0 flex-col bg-background text-foreground" :class="{ 'theme-donker': isSpeler }">
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

        <main class="min-w-0 flex-1">
            <slot />
        </main>
    </div>
</template>
