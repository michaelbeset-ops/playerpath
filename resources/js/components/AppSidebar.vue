<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { CalendarDays, CalendarRange, ClipboardList, CreditCard, FileDown, Inbox, LayoutGrid, Receipt, Tag, Users, UsersRound } from 'lucide-vue-next';
import { computed, type Component } from 'vue';
import AppLogo from './AppLogo.vue';

/**
 * Welke menu-items er staan bepaalt de server (MainNavigation), op basis van
 * de policies. Hier vertalen we alleen de iconennaam naar een component, zodat
 * het menu nooit een item kan tonen dat je toch niet mag openen.
 */
const iconen: Record<string, Component> = {
    dashboard: LayoutGrid,
    players: Users,
    groups: UsersRound,
    trainings: CalendarDays,
    calendar: CalendarRange,
    reports: ClipboardList,
    subscriptions: Receipt,
    payments: CreditCard,
    plans: Tag,
    exports: FileDown,
    enrollments: Inbox,
};

const page = usePage<SharedData>();

const mainNavItems = computed<NavItem[]>(() =>
    (page.props.nav ?? []).map((item) => ({
        section: item.section,
        title: item.title,
        href: item.href,
        icon: iconen[item.icon] ?? LayoutGrid,
    })),
);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('dashboard')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
