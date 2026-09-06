<script setup lang="ts">
import { Button } from '@/components/ui/button';
import PlatformLayout from '@/layouts/PlatformLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Power, PowerOff } from 'lucide-vue-next';

const props = defineProps<{
    school: {
        id: number;
        name: string;
        slug: string;
        is_active: boolean;
        players_count: number;
        trainers_count: number;
        users_count: number;
        brand_color: string | null;
        contact_name: string | null;
        contact_email: string | null;
        contact_phone: string | null;
        notes: string | null;
        created_at: string | null;
    };
    owners: { id: number; name: string; email: string }[];
    domain: string | null;
}>();

const wissel = () => {
    const vraag = props.school.is_active
        ? `${props.school.name} uitzetten? Niemand van die school kan dan nog inloggen.`
        : `${props.school.name} weer aanzetten?`;

    if (confirm(vraag)) {
        router.patch('/beheer/scholen/' + props.school.id + '/status', {}, { preserveScroll: true });
    }
};
</script>

<template>
    <Head :title="school.name" />

    <PlatformLayout>
        <Link href="/beheer/scholen" class="text-sm text-muted-foreground underline underline-offset-4">Terug naar scholen</Link>

        <div class="mt-2 flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <h1 class="flex flex-wrap items-center gap-2 text-2xl font-semibold tracking-tight">
                    {{ school.name }}
                    <span v-if="!school.is_active" class="rounded bg-secondary px-2 py-0.5 text-xs font-medium text-muted-foreground">
                        inactief
                    </span>
                </h1>
                <p class="text-sm text-muted-foreground">
                    <template v-if="domain">{{ school.slug }}.{{ domain }}</template>
                    <template v-else>{{ school.slug }}</template>
                    <span v-if="school.created_at"> &middot; sinds {{ school.created_at }}</span>
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <Link
                    :href="'/beheer/scholen/' + school.id + '/bewerken'"
                    class="inline-flex h-10 items-center gap-2 rounded-xl border border-border bg-card px-4 text-sm font-medium shadow-sm transition hover:border-primary"
                >
                    <Pencil class="size-4" />
                    Bewerken
                </Link>

                <Button :variant="school.is_active ? 'secondary' : 'default'" @click="wissel">
                    <component :is="school.is_active ? PowerOff : Power" class="mr-2 size-4" />
                    {{ school.is_active ? 'Uitzetten' : 'Aanzetten' }}
                </Button>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
                <p class="tabular text-2xl font-bold text-primary">{{ school.players_count }}</p>
                <p class="text-xs text-muted-foreground">actieve spelers</p>
            </div>
            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
                <p class="tabular text-2xl font-bold text-primary">{{ school.trainers_count }}</p>
                <p class="text-xs text-muted-foreground">trainers</p>
            </div>
            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
                <p class="tabular text-2xl font-bold">{{ school.users_count }}</p>
                <p class="text-xs text-muted-foreground">accounts totaal</p>
            </div>
            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
                <div class="flex items-center gap-2">
                    <span
                        class="size-5 shrink-0 rounded border border-border"
                        :style="school.brand_color ? { backgroundColor: school.brand_color } : {}"
                    ></span>
                    <p class="truncate text-sm font-medium">{{ school.brand_color ?? 'Standaard' }}</p>
                </div>
                <p class="mt-1 text-xs text-muted-foreground">merkkleur</p>
            </div>
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            <div class="rounded-xl border border-border bg-card p-5 shadow-sm">
                <p class="font-medium">Eigenaren</p>

                <div v-if="owners.length" class="mt-3 space-y-2">
                    <div v-for="eigenaar in owners" :key="eigenaar.id" class="rounded-lg border border-border p-3">
                        <p class="text-sm font-medium">{{ eigenaar.name }}</p>
                        <p class="truncate text-xs text-muted-foreground">{{ eigenaar.email }}</p>
                    </div>
                </div>

                <p v-else class="mt-3 rounded-lg border border-warning/30 bg-warning/10 p-3 text-sm">
                    Deze school heeft nog geen eigenaar. Er kan dus niemand inloggen.
                </p>
            </div>

            <div class="rounded-xl border border-border bg-card p-5 shadow-sm">
                <p class="font-medium">Contact</p>

                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex gap-2">
                        <dt class="w-28 shrink-0 text-muted-foreground">Contactpersoon</dt>
                        <dd class="min-w-0">{{ school.contact_name ?? '—' }}</dd>
                    </div>
                    <div class="flex gap-2">
                        <dt class="w-28 shrink-0 text-muted-foreground">E-mail</dt>
                        <dd class="min-w-0 break-words">{{ school.contact_email ?? '—' }}</dd>
                    </div>
                    <div class="flex gap-2">
                        <dt class="w-28 shrink-0 text-muted-foreground">Telefoon</dt>
                        <dd class="min-w-0">{{ school.contact_phone ?? '—' }}</dd>
                    </div>
                </dl>

                <p v-if="school.notes" class="mt-3 whitespace-pre-line rounded-lg bg-secondary px-3 py-2 text-sm">{{ school.notes }}</p>
            </div>
        </div>
    </PlatformLayout>
</template>
