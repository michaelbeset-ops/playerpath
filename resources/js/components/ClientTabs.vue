<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Contact, Users } from 'lucide-vue-next';

/**
 * De twee klantlijsten naast elkaar. Eén component, zodat de tabbladen op
 * beide schermen gelijk blijven staan.
 */
defineProps<{
    actief: 'players' | 'guardians';
    counts: { players: number; guardians: number };
}>();

const tabbladen = [
    { key: 'players', label: 'Spelers', href: '/clients', icon: Users },
    { key: 'guardians', label: 'Ouders', href: '/clients/guardians', icon: Contact },
] as const;
</script>

<template>
    <div class="mt-5 grid grid-cols-2 gap-1 rounded-xl border border-border bg-card p-1 shadow-sm">
        <Link
            v-for="tab in tabbladen"
            :key="tab.key"
            :href="tab.href"
            class="flex items-center justify-center gap-1.5 rounded-lg px-2 py-2.5 text-sm font-medium transition sm:gap-2"
            :class="actief === tab.key ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'"
        >
            <component :is="tab.icon" class="hidden size-4 shrink-0 sm:block" />
            <span class="truncate">{{ tab.label }}</span>
            <span class="tabular text-xs opacity-70">{{ counts[tab.key] }}</span>
        </Link>
    </div>
</template>
