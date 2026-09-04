<script setup lang="ts">
import FlashMessage from '@/components/FlashMessage.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { Pencil, Plus, Users } from 'lucide-vue-next';

defineProps<{
    groups: { id: number; name: string; age_category: string | null; is_active: boolean; players_count: number }[];
    canManage: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Groepen', href: '/groups' }];
</script>

<template>
    <Head title="Groepen" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-3xl p-4">
            <FlashMessage />

            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Groepen</h1>
                    <p class="mt-1 text-sm text-muted-foreground">Trainingsgroepen met hun leeftijdscategorie.</p>
                </div>

                <Link
                    v-if="canManage"
                    href="/groups/create"
                    class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                >
                    <Plus class="size-4" />
                    Groep toevoegen
                </Link>
            </div>

            <div v-if="groups.length" class="mt-6 space-y-2">
                <div
                    v-for="groep in groups"
                    :key="groep.id"
                    class="flex items-center gap-4 rounded-xl border border-border bg-card p-4 shadow-sm"
                >
                    <span
                        class="flex size-10 shrink-0 items-center justify-center rounded-lg"
                        :class="groep.players_count ? 'bg-primary/10 text-primary' : 'bg-secondary text-muted-foreground/70'"
                    >
                        <Users class="size-5" />
                    </span>

                    <div class="min-w-0 flex-1">
                        <p class="flex items-center gap-2 truncate font-medium">
                            {{ groep.name }}
                            <span v-if="!groep.is_active" class="rounded bg-secondary px-1.5 py-0.5 text-[10px] text-muted-foreground">
                                niet actief
                            </span>
                        </p>
                        <p class="truncate text-xs text-muted-foreground">
                            <span v-if="groep.age_category">{{ groep.age_category }} &middot; </span>
                            <span class="tabular">{{ groep.players_count }}</span>
                            {{ groep.players_count === 1 ? 'speler' : 'spelers' }}
                        </p>
                    </div>

                    <Link
                        v-if="canManage"
                        :href="'/groups/' + groep.id + '/edit'"
                        class="rounded-lg p-2 text-muted-foreground transition hover:bg-secondary hover:text-foreground"
                        :aria-label="groep.name + ' bewerken'"
                    >
                        <Pencil class="size-4" />
                    </Link>
                </div>
            </div>

            <div v-else class="mt-6 rounded-2xl border border-dashed border-border bg-card/50 p-10 text-center">
                <p class="font-medium">Nog geen groepen</p>
                <p class="mt-1 text-sm text-muted-foreground">Maak een groep aan om je spelers in te delen.</p>
            </div>
        </div>
    </AppLayout>
</template>
