<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage<SharedData>();

// Ouders en spelers hebben geen schooldashboard om in te richten; hun dat
// tabblad tonen zou een klik naar een 404 zijn.
const heeftSchooldashboard = computed(() => {
    const rollen = page.props.auth.roles ?? [];

    return rollen.includes('eigenaar') || rollen.includes('trainer');
});

const sidebarNavItems = computed<NavItem[]>(() => [
    {
        title: 'Profiel',
        href: '/settings/profile',
    },
    {
        title: 'Wachtwoord',
        href: '/settings/password',
    },
    {
        title: 'Meldingen',
        href: '/settings/notifications',
    },
    ...(heeftSchooldashboard.value ? [{ title: 'Dashboard', href: '/settings/dashboard' }] : []),
]);

const currentPath = window.location.pathname;
</script>

<template>
    <div class="px-4 py-6">
        <Heading title="Instellingen" description="Beheer je profiel en accountinstellingen" />

        <div class="flex flex-col space-y-8 md:space-y-0 lg:flex-row lg:space-x-12 lg:space-y-0">
            <aside class="w-full max-w-xl lg:w-48">
                <nav class="flex flex-col space-x-0 space-y-1">
                    <Button
                        v-for="item in sidebarNavItems"
                        :key="item.href"
                        variant="ghost"
                        :class="['w-full justify-start', { 'bg-muted': currentPath === item.href }]"
                        as-child
                    >
                        <Link :href="item.href">
                            {{ item.title }}
                        </Link>
                    </Button>
                </nav>
            </aside>

            <Separator class="my-6 md:hidden" />

            <div class="flex-1 md:max-w-2xl">
                <section class="max-w-xl space-y-12">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
