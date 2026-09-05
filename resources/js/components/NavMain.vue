<script setup lang="ts">
import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    items: NavItem[];
}>();

const page = usePage<SharedData>();

// Ook actief op onderliggende schermen: /players/1/reports/create hoort bij
// het menu-item Rapporten.
const isActief = (item: NavItem) => {
    const huidig = page.url.split('?')[0];

    return huidig === item.href || huidig.startsWith(item.href + '/');
};

// Secties in de volgorde waarin ze voor het eerst voorkomen. Een sectie met
// maar één item krijgt geen kopje: dat zou meer ruis zijn dan hulp.
const secties = computed(() => {
    const map = new Map<string, NavItem[]>();

    for (const item of props.items) {
        const key = item.section ?? 'Menu';
        (map.get(key) ?? map.set(key, []).get(key)!).push(item);
    }

    return [...map.entries()].map(([label, items]) => ({ label, items }));
});
</script>

<template>
    <SidebarGroup v-for="sectie in secties" :key="sectie.label" class="px-2 py-0">
        <SidebarGroupLabel v-if="secties.length > 1">{{ sectie.label }}</SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="item in sectie.items" :key="item.title">
                <SidebarMenuButton as-child :is-active="isActief(item)">
                    <Link :href="item.href">
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
