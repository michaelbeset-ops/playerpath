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
 * Een speler krijgt het donker, net als de rest van zijn kant van de app; een
 * lichte pagina tussen twee donkere is precies de witte strook die stoorde.
 */
const items: (NavItem & { icon: Component; hint: string })[] = [
    { title: 'Profiel', href: '/settings/profile', icon: UserRound, hint: 'Foto, naam en e-mail' },
    { title: 'Wachtwoord', href: '/settings/password', icon: KeyRound, hint: 'Kies een nieuw wachtwoord' },
    { title: 'Meldingen', href: '/settings/notifications', icon: Bell, hint: 'Welke e-mails je krijgt' },
];

const page = usePage<SharedData>();
const user = computed(() => page.props.auth.user);
const rollen = computed(() => page.props.auth.roles ?? []);
const isSpeler = computed(() => rollen.value.includes('speler') && !rollen.value.includes('ouder'));

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
    <div :class="isSpeler ? 'theme-donker min-h-screen bg-background text-foreground' : ''">
        <div class="mx-auto w-full max-w-4xl p-4">
            <!-- Wie je bent. Geen kop "Instellingen": je ziet je eigen naam en foto,
                 dat zegt genoeg. -->
            <div class="flex items-center gap-4">
                <Avatar v-if="user" :name="user.name" :photo="user.photo_url ?? null" size="size-16" />
                <div class="min-w-0">
                    <h1 class="truncate text-2xl font-semibold tracking-tight">{{ user?.name }}</h1>
                    <p class="truncate text-sm text-muted-foreground">
                        {{ user?.email }}
                        <template v-if="rollen.length"> &middot; {{ rollen.map((r) => rolLabel[r] ?? r).join(', ') }}</template>
                    </p>
                </div>
            </div>

            <div class="mt-6 flex flex-col gap-6 lg:flex-row lg:gap-10">
                <!-- Tabbladen. Op een telefoon een rij pillen die je kunt
                     schuiven; op een groot scherm een zijbalk met uitleg. -->
                <nav class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 lg:mx-0 lg:w-56 lg:shrink-0 lg:flex-col lg:overflow-visible lg:px-0">
                    <Link
                        v-for="item in items"
                        :key="item.href"
                        :href="item.href"
                        class="flex shrink-0 items-center gap-3 rounded-xl border px-3 py-2.5 text-sm transition lg:w-full"
                        :class="
                            currentPath === item.href
                                ? 'border-primary/40 bg-primary/10 font-semibold text-primary'
                                : 'border-border bg-card text-foreground hover:border-primary'
                        "
                    >
                        <component :is="item.icon" class="size-4 shrink-0" />
                        <span class="min-w-0">
                            <span class="block">{{ item.title }}</span>
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
