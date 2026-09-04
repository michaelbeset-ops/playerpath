<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import type { SharedData } from '@/types';

const page = usePage<SharedData>();
</script>

<template>
    <Head title="Welkom" />

    <div class="flex min-h-svh flex-col items-center justify-center bg-background p-6">
        <div class="w-full max-w-md text-center">
            <div class="mx-auto mb-6 flex size-14 items-center justify-center rounded-2xl bg-primary text-primary-foreground">
                <AppLogoIcon class="size-8" />
            </div>

            <h1 class="text-3xl font-bold tracking-tight">PlayerPath</h1>
            <p class="mt-3 text-sm text-muted-foreground">
                Het platform voor keeper- en voetbalscholen. Rapporten, spelerskaarten en voortgang op één plek.
            </p>

            <div class="mt-8 flex items-center justify-center gap-3">
                <Link
                    v-if="page.props.auth.user"
                    :href="route('dashboard')"
                    class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                >
                    Naar het dashboard
                </Link>

                <template v-else>
                    <Link
                        :href="route('login')"
                        class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                    >
                        Inloggen
                    </Link>
                    <Link
                        :href="route('register')"
                        class="rounded-xl border border-border px-5 py-2.5 text-sm font-semibold transition hover:bg-accent"
                    >
                        Account aanmaken
                    </Link>
                </template>
            </div>
        </div>
    </div>
</template>
