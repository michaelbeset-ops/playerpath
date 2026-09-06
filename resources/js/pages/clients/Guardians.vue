<script setup lang="ts">
import Avatar from '@/components/Avatar.vue';
import ClientTabs from '@/components/ClientTabs.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import PhotoUpload from '@/components/PhotoUpload.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    counts: { players: number; guardians: number };
    can: { managePlayers: boolean };
    guardians: {
        id: number;
        name: string;
        photo: string | null;
        email: string;
        children: { id: number; name: string; relationship: string | null }[];
    }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Klanten', href: '/clients' },
    { title: 'Ouders', href: '/clients/guardians' },
];
</script>

<template>
    <Head title="Ouders" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-4xl p-4">
            <FlashMessage />

            <h1 class="text-2xl font-semibold tracking-tight">Klanten</h1>
            <p class="mt-1 text-sm text-muted-foreground">De spelers van je school en de ouders die erbij horen.</p>

            <ClientTabs actief="guardians" :counts="counts" />

            <p class="mt-4 text-sm text-muted-foreground">Ouders koppel je aan een kind op de spelerspagina. Hier zie je wie er aan wie hangt.</p>

            <div v-if="guardians.length" class="mt-4 overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <div v-for="(ouder, index) in guardians" :key="ouder.id" class="p-4" :class="index > 0 ? 'border-t border-border' : ''">
                    <div class="flex items-center gap-4">
                        <!-- De eigenaar mag de foto zetten; wie dat niet mag
                             ziet dezelfde avatar, alleen niet klikbaar. -->
                        <PhotoUpload
                            v-if="can.managePlayers"
                            compact
                            :name="ouder.name"
                            :photo="ouder.photo"
                            :action="'/users/' + ouder.id + '/photo'"
                        />
                        <Avatar v-else :name="ouder.name" :photo="ouder.photo" size="size-11" />

                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium">{{ ouder.name }}</p>
                            <p class="truncate text-xs text-muted-foreground">{{ ouder.email }}</p>
                        </div>
                    </div>

                    <div v-if="ouder.children.length" class="mt-3 flex flex-wrap gap-2 pl-[3.75rem]">
                        <Link
                            v-for="kind in ouder.children"
                            :key="kind.id"
                            :href="'/players/' + kind.id"
                            class="rounded-lg border border-border px-2.5 py-1 text-xs transition hover:border-primary"
                        >
                            {{ kind.name }}
                            <span v-if="kind.relationship" class="text-muted-foreground">({{ kind.relationship }})</span>
                        </Link>
                    </div>

                    <p v-else class="mt-2 pl-[3.75rem] text-xs text-warning">Nog niet aan een kind gekoppeld.</p>
                </div>
            </div>

            <div v-else class="mt-4 rounded-2xl border border-dashed border-border bg-card/50 p-10 text-center">
                <p class="font-medium">Nog geen ouders</p>
                <p class="mt-1 text-sm text-muted-foreground">Nodig een ouder uit vanaf de pagina van een speler.</p>
            </div>
        </div>
    </AppLayout>
</template>
