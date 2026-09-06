<script setup lang="ts">
import ImpersonationBanner from '@/components/ImpersonationBanner.vue';
import InstallPrompt from '@/components/InstallPrompt.vue';
import AppLayout from '@/layouts/app/AppTopbarLayout.vue';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <ImpersonationBanner />

    <AppLayout :breadcrumbs="breadcrumbs">
        <slot />
        <InstallPrompt />
    </AppLayout>
</template>
