<script setup lang="ts">
import FlashMessage from '@/components/FlashMessage.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { CalendarDays, Check, MapPin, Plus, X } from 'lucide-vue-next';
import { ref } from 'vue';

interface TrainingRij {
    id: number;
    group: string;
    date: string;
    time: string;
    location: string | null;
    trainers: string[];
    attendance_count: number;
    expected_count: number;
    my_registration: string | null;
}

defineProps<{
    upcoming: TrainingRij[];
    past: TrainingRij[];
    canManage: boolean;
    isParticipant: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Trainingen', href: '/trainings' }];

const tab = ref<'upcoming' | 'past'>('upcoming');
</script>

<template>
    <Head title="Trainingen" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-3xl p-4">
            <FlashMessage />

            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Trainingen</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ isParticipant ? 'De trainingen van jouw groep.' : 'Het rooster van je school.' }}
                    </p>
                </div>

                <Link
                    v-if="canManage"
                    href="/trainings/create"
                    class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                >
                    <Plus class="size-4" />
                    Training inplannen
                </Link>
            </div>

            <!-- Komend / geweest -->
            <div class="mt-6 inline-flex rounded-lg border border-border bg-card p-1 shadow-sm">
                <button
                    type="button"
                    class="rounded-md px-4 py-1.5 text-sm font-medium transition"
                    :class="tab === 'upcoming' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'"
                    @click="tab = 'upcoming'"
                >
                    Komend ({{ upcoming.length }})
                </button>
                <button
                    type="button"
                    class="rounded-md px-4 py-1.5 text-sm font-medium transition"
                    :class="tab === 'past' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'"
                    @click="tab = 'past'"
                >
                    Geweest ({{ past.length }})
                </button>
            </div>

            <div v-if="(tab === 'upcoming' ? upcoming : past).length" class="mt-4 space-y-2">
                <Link
                    v-for="training in tab === 'upcoming' ? upcoming : past"
                    :key="training.id"
                    :href="'/trainings/' + training.id"
                    class="flex items-center gap-4 rounded-xl border border-border bg-card p-4 shadow-sm transition hover:border-primary/40"
                >
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <CalendarDays class="size-5" />
                    </span>

                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium">{{ training.group }}</p>
                        <p class="truncate text-xs text-muted-foreground">
                            <span class="first-letter:uppercase">{{ training.date }}</span> &middot; {{ training.time }}
                            <span v-if="training.location"> &middot; {{ training.location }}</span>
                            <span v-if="training.trainers.length"> &middot; {{ training.trainers.join(', ') }}</span>
                        </p>
                    </div>

                    <!-- Voor een ouder of speler: wat gaf ik door? -->
                    <span
                        v-if="isParticipant && training.my_registration"
                        class="inline-flex shrink-0 items-center gap-1 rounded-lg px-2 py-1 text-xs font-medium"
                        :class="
                            training.my_registration === 'attending'
                                ? 'bg-primary/10 text-primary'
                                : 'bg-secondary text-muted-foreground'
                        "
                    >
                        <Check v-if="training.my_registration === 'attending'" class="size-3" />
                        <X v-else class="size-3" />
                        {{ training.my_registration === 'attending' ? 'Aangemeld' : 'Afgemeld' }}
                    </span>

                    <!-- Voor een trainer: hoeveel al afgevinkt -->
                    <span v-else-if="!isParticipant" class="tabular shrink-0 text-xs text-muted-foreground">
                        {{ training.attendance_count }}/{{ training.expected_count }}
                    </span>
                </Link>
            </div>

            <div v-else class="mt-4 rounded-2xl border border-dashed border-border bg-card/50 p-10 text-center">
                <p class="font-medium">
                    {{ tab === 'upcoming' ? 'Geen komende trainingen' : 'Nog geen trainingen geweest' }}
                </p>
                <p class="mt-1 text-sm text-muted-foreground">
                    <template v-if="tab === 'upcoming' && canManage">Plan er een in om te beginnen.</template>
                    <template v-else-if="tab === 'upcoming'">Zodra je trainer er een inplant, staat hij hier.</template>
                </p>
            </div>
        </div>
    </AppLayout>
</template>
