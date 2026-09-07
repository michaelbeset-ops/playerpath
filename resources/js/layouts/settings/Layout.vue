<script setup lang="ts">
import Avatar from '@/components/Avatar.vue';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { Bell, KeyRound, UserRound } from 'lucide-vue-next';
import { computed, type Component } from 'vue';

/**
 * De schil van de instellingen.
 *
 * Bovenaan wie je bent, daaronder de tabbladen: op een telefoon naast elkaar
 * als pillen, op een groot scherm als zijbalk. Elke pagina zet haar inhoud in
 * kaarten (`SettingsCard`), zodat een profiel, een wachtwoord en de meldingen
 * er hetzelfde uitzien.
 *
 * Het donkere thema voor een speler staat op de layout, niet hier.
 */
const items: (NavItem & { icon: Component; hint: string })[] = [
    { title: 'Profiel', href: '/settings/profile', icon: UserRound, hint: 'Foto, naam en e-mail' },
    { title: 'Wachtwoord', href: '/settings/password', icon: KeyRound, hint: 'Kies een nieuw wachtwoord' },
    { title: 'Meldingen', href: '/settings/notifications', icon: Bell, hint: 'Welke e-mails je krijgt' },
];

const page = usePage<SharedData>();
const user = computed(() => page.props.auth.user);
const rollen = computed(() => page.props.auth.roles ?? []);

const rolLabel: Record<string, string> = {
    eigenaar: 'Eigenaar',
    trainer: 'Trainer',
    ouder: 'Ouder',
    speler: 'Speler',
    platformbeheerder: 'Platformbeheerder',
};

const currentPath = window.location.pathname;
</script>

<template>
    <div>
        <div class="mx-auto w-full max-w-4xl p-4">
            <!-- Wie je bent. Geen kop "Instellingen": je ziet je eigen naam en foto,
                 dat zegt genoeg. -->
            <div class="flex items-center gap-4">
                <Avatar v-if="user" :name="user.name" :photo="user.photo_url ?? null" size="size-16" />
                <div class="min-w-0">
                    <h1 class="break-words text-2xl font-semibold tracking-tight">{{ user?.name }}</h1>
                    <p class="break-words text-sm text-muted-foreground">
                        {{ user?.email }}
                        <template v-if="rollen.length"> &middot; {{ rollen.map((r) => rolLabel[r] ?? r).join(', ') }}</template>
                    </p>
                </div>
            </div>

            <div class="mt-6 flex flex-col gap-6 lg:flex-row lg:gap-10">
                <!-- Tabbladen. Op een telefoon drie naast elkaar met het icoon
                     erboven; op een groot scherm een zijbalk met uitleg. -->
                <nav class="grid grid-cols-3 gap-2 lg:flex lg:w-56 lg:shrink-0 lg:flex-col">
                    <Link
                        v-for="item in items"
                        :key="item.href"
                        :href="item.href"
                        class="flex min-w-0 flex-col items-center gap-1.5 rounded-xl border px-2 py-2.5 text-center text-sm transition lg:w-full lg:flex-row lg:gap-3 lg:px-3 lg:text-left"
                        :class="
                            currentPath === item.href
                                ? 'border-primary/40 bg-primary/10 font-semibold text-primary'
                                : 'border-border bg-card text-foreground hover:border-primary'
                        "
                    >
                        <component :is="item.icon" class="size-4 shrink-0" />
                        <span class="min-w-0">
                            <span class="block text-xs lg:text-sm">{{ item.title }}</span>
                            <span class="hidden text-xs font-normal text-muted-foreground lg:block">{{ item.hint }}</span>
                        </span>
                    </Link>
                </nav>

                <div class="min-w-0 flex-1 space-y-4">
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
