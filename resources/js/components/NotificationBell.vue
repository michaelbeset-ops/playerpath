<script setup lang="ts">
import type { SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { Bell } from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * Het aantal ongelezen meldingen komt mee met elke pagina, dus de bel klopt
 * altijd zonder te pollen.
 */
const page = usePage<SharedData>();

const ongelezen = computed(() => page.props.unreadNotifications ?? 0);
</script>

<template>
    <Link
        href="/notifications"
        class="relative shrink-0 rounded-lg p-2 text-muted-foreground transition hover:bg-card/60 hover:text-foreground"
        :aria-label="ongelezen > 0 ? ongelezen + ' ongelezen meldingen' : 'Meldingen'"
    >
        <Bell class="size-5" />
        <span
            v-if="ongelezen > 0"
            class="tabular absolute -right-0.5 -top-0.5 flex min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-bold leading-4 text-primary-foreground"
        >
            {{ ongelezen > 9 ? '9+' : ongelezen }}
        </span>
    </Link>
</template>
