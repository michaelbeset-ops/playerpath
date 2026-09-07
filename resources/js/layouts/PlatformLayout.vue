<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { Building2, LayoutGrid, LogOut, ScrollText } from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * De schil van de beheeromgeving.
 *
 * Bewust niet dezelfde als de app. Je moet in één oogopslag kunnen zien dat je
 * boven alle scholen staat en niet in één school werkt — anders wijzig je een
 * keer iets bij de verkeerde. Vandaar de donkere balk met "Platformbeheer" en
 * geen schoolnaam of huisstijl van een school.
 */
const page = usePage<{ auth: { user: { name: string } | null } }>();
const naam = computed(() => page.props.auth?.user?.name ?? '');
</script>

<template>
    <div class="min-h-svh bg-background">
        <header class="theme-donker sticky top-0 z-30 bg-background text-foreground">
            <div class="mx-auto flex w-full max-w-6xl items-center justify-between gap-3 px-4 py-3">
                <Link href="/beheer/scholen" class="flex min-w-0 items-center gap-2.5">
                    <AppLogoIcon class="size-6 shrink-0 rounded" />
                    <span class="truncate text-sm font-semibold">Platformbeheer</span>
                </Link>

                <div class="flex shrink-0 items-center gap-1">
                    <Link
                        href="/beheer"
                        class="inline-flex h-9 items-center gap-2 rounded-lg px-3 text-sm font-medium text-muted-foreground transition hover:bg-secondary hover:text-foreground"
                    >
                        <LayoutGrid class="size-4" />
                        <span class="hidden sm:inline">Overzicht</span>
                    </Link>

                    <Link
                        href="/beheer/scholen"
                        class="inline-flex h-9 items-center gap-2 rounded-lg px-3 text-sm font-medium text-muted-foreground transition hover:bg-secondary hover:text-foreground"
                    >
                        <Building2 class="size-4" />
                        <span class="hidden sm:inline">Scholen</span>
                    </Link>

                    <Link
                        href="/beheer/logboek"
                        class="inline-flex h-9 items-center gap-2 rounded-lg px-3 text-sm font-medium text-muted-foreground transition hover:bg-secondary hover:text-foreground"
                    >
                        <ScrollText class="size-4" />
                        <span class="hidden sm:inline">Logboek</span>
                    </Link>

                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        class="inline-flex h-9 items-center gap-2 rounded-lg px-3 text-sm text-muted-foreground transition hover:bg-secondary hover:text-foreground"
                    >
                        <LogOut class="size-4" />
                        <span class="hidden sm:inline">Uitloggen</span>
                    </Link>
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-6xl p-4">
            <FlashMessage />
            <slot />
        </main>

        <p class="pb-8 text-center text-xs text-muted-foreground">Ingelogd als {{ naam }} · platformbeheerder</p>
    </div>
</template>
