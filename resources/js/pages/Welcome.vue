<script setup lang="ts">
import AppWordmark from '@/components/AppWordmark.vue';
import type { SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';

const page = usePage<SharedData>();
</script>

<template>
    <Head title="Welkom" />

    <div class="theme-donker flex min-h-svh flex-col items-center justify-center bg-background p-6 text-foreground">
        <div class="w-full max-w-md text-center">
            <!-- Het hele logo: dit is de voordeur, daar hoort de naam bij het
                 teken te staan. -->
            <AppWordmark donker size="lg" class="mx-auto mb-6" />

            <h1 class="sr-only">PlayerPath</h1>
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

                <Link
                    v-else
                    :href="route('login')"
                    class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                >
                    Inloggen
                </Link>
            </div>
        </div>
    </div>
</template>
