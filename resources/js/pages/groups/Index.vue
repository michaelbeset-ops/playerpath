<script setup lang="ts">
import FlashMessage from '@/components/FlashMessage.vue';
import DemoBadge from '@/components/onboarding/DemoBadge.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { CalendarDays, ChevronRight, Plus, Users } from 'lucide-vue-next';

defineProps<{
    groups: {
        id: number;
        name: string;
        age_category: string | null;
        is_active: boolean;
        is_demo: boolean;
        players_count: number;
        upcoming_trainings_count: number;
    }[];
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
                    <p class="mt-1 text-sm text-muted-foreground">Wie traint er samen, en wanneer.</p>
                </div>

                <Link
                    v-if="canManage"
                    href="/groups/create"
                    class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                >
                    <Plus class="size-4" />
                    Groep toevoegen
                </Link>
            </div>

            <!-- Wat een groep is. Kort, want wie het weet leest eroverheen; wie
                 het niet weet vult anders spelers in zonder te snappen waarom. -->
            <div class="mt-5 rounded-xl bg-primary/10 p-4 text-sm leading-relaxed">
                <p>
                    <span class="font-medium">Een groep is een vaste club spelers die samen traint</span>, bijvoorbeeld "Keepers O12". Je
                    plant een training voor een groep, en iedereen die erin zit staat dan vanzelf op de aanwezigheidslijst. Rapporten vul je
                    per training in, dus ook per groep.
                </p>
                <p class="mt-2">Een speler mag in meerdere groepen zitten. Indelen doe je hier, of op de pagina van de speler zelf.</p>
            </div>

            <div v-if="groups.length" class="mt-4 space-y-2">
                <Link
                    v-for="groep in groups"
                    :key="groep.id"
                    :href="'/groups/' + groep.id"
                    class="flex items-center gap-4 rounded-xl border border-border bg-card p-4 shadow-sm transition hover:border-primary"
                >
                    <span
                        class="flex size-10 shrink-0 items-center justify-center rounded-lg"
                        :class="groep.players_count ? 'bg-primary/10 text-primary' : 'bg-secondary text-muted-foreground/70'"
                    >
                        <Users class="size-5" />
                    </span>

                    <div class="min-w-0 flex-1">
                        <p class="flex flex-wrap items-center gap-2 font-medium">
                            <DemoBadge v-if="groep.is_demo" />
                            {{ groep.name }}
                            <span v-if="!groep.is_active" class="rounded bg-secondary px-1.5 py-0.5 text-[10px] text-muted-foreground">
                                niet actief
                            </span>
                        </p>
                        <p class="flex flex-wrap gap-x-2 text-xs text-muted-foreground">
                            <span v-if="groep.age_category">{{ groep.age_category }}</span>
                            <span><span class="tabular">{{ groep.players_count }}</span> {{ groep.players_count === 1 ? 'speler' : 'spelers' }}</span>
                            <span class="inline-flex items-center gap-1">
                                <CalendarDays class="size-3" />
                                <span class="tabular">{{ groep.upcoming_trainings_count }}</span>
                                {{ groep.upcoming_trainings_count === 1 ? 'training gepland' : 'trainingen gepland' }}
                            </span>
                        </p>
                    </div>

                    <ChevronRight class="size-4 shrink-0 text-muted-foreground" />
                </Link>
            </div>

            <div v-else class="mt-6 rounded-2xl border border-dashed border-border bg-card/50 p-10 text-center">
                <p class="font-medium">Nog geen groepen</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    Begin met één groep, bijvoorbeeld "Keepers" of "Onder 12". Daarna zet je er spelers in en plan je er trainingen voor.
                </p>
            </div>
        </div>
    </AppLayout>
</template>
