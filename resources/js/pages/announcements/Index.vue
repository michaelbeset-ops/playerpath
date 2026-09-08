<script setup lang="ts">
import FlashMessage from '@/components/FlashMessage.vue';
import InputError from '@/components/InputError.vue';
import DemoBadge from '@/components/onboarding/DemoBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { CalendarX2, Megaphone, Users } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    announcements: {
        id: number;
        title: string;
        body: string;
        author: string | null;
        group: string | null;
        recipients_count: number;
        sent_at: string;
        from_cancellation: boolean;
        is_demo?: boolean;
    }[];
    groups: { id: number; name: string; recipients: number }[];
    schoolRecipients: number;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Mededelingen', href: '/announcements' }];

const form = useForm({ title: '', body: '', group_id: '' as string | number });

// Hardop laten zien hoeveel mensen je aanschrijft: dat voorkomt een bericht
// dat per ongeluk naar de hele school gaat in plaats van naar één groep.
const ontvangers = computed(() => {
    if (form.group_id === '') {
        return props.schoolRecipients;
    }

    return props.groups.find((g) => g.id === Number(form.group_id))?.recipients ?? 0;
});

const versturen = () =>
    form.post('/announcements', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
</script>

<template>
    <Head title="Mededelingen" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-4" data-tour="announcements">
            <FlashMessage />

            <div class="flex items-center gap-3">
                <Megaphone class="size-5 text-primary" />
                <div>
                    <h1 class="text-xl font-semibold">Mededelingen</h1>
                    <p class="text-sm text-muted-foreground">Een bericht aan alle gezinnen of aan één groep, in de app en per e-mail.</p>
                </div>
            </div>

            <form class="mt-4 rounded-xl border border-border bg-card p-5 shadow-sm" @submit.prevent="versturen">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="title">Onderwerp</Label>
                        <Input id="title" v-model="form.title" placeholder="Training zaterdag gaat niet door" required />
                        <InputError :message="form.errors.title" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="group_id">Aan wie</Label>
                        <select
                            id="group_id"
                            v-model="form.group_id"
                            class="min-h-11 rounded-lg border border-input bg-background px-3 text-sm outline-none focus:border-primary"
                        >
                            <option value="">Iedereen in de school</option>
                            <option v-for="groep in groups" :key="groep.id" :value="groep.id">{{ groep.name }}</option>
                        </select>
                        <InputError :message="form.errors.group_id" />
                    </div>
                </div>

                <div class="mt-4 grid gap-2">
                    <Label for="body">Bericht</Label>
                    <textarea
                        id="body"
                        v-model="form.body"
                        rows="4"
                        maxlength="2000"
                        required
                        class="min-h-11 rounded-lg border border-input bg-background px-3 py-2 text-sm outline-none focus:border-primary"
                        placeholder="Het veld staat onder water. Volgende week gaan we weer normaal door."
                    ></textarea>
                    <InputError :message="form.errors.body" />
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <Button type="submit" :disabled="form.processing || !form.title || !form.body">Versturen</Button>
                    <p class="flex items-center gap-1.5 text-sm text-muted-foreground">
                        <Users class="size-4" />
                        <span class="tabular font-medium text-foreground">{{ ontvangers }}</span>
                        {{ ontvangers === 1 ? 'ontvanger' : 'ontvangers' }}
                    </p>
                </div>

                <p class="mt-3 text-xs text-muted-foreground">
                    Ouders en spelers met een eigen inlog krijgen het bericht. Een verstuurd bericht kun je niet terugnemen.
                </p>
            </form>

            <div class="mt-4 rounded-xl border border-border bg-card p-5 shadow-sm">
                <p class="font-medium">Verstuurd</p>

                <div v-if="announcements.length" class="mt-3 space-y-3">
                    <div v-for="bericht in announcements" :key="bericht.id" class="rounded-lg border border-border p-4">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <p class="flex items-center gap-2 font-medium">
                                <CalendarX2 v-if="bericht.from_cancellation" class="size-4 text-warning" />
                                {{ bericht.title }}
                                <DemoBadge v-if="bericht.is_demo" />
                            </p>
                            <span class="rounded-md bg-secondary px-2 py-0.5 text-xs text-muted-foreground">
                                {{ bericht.group ?? 'Hele school' }}
                            </span>
                        </div>

                        <p class="mt-2 whitespace-pre-line text-sm text-muted-foreground">{{ bericht.body }}</p>

                        <p class="tabular mt-2 text-xs text-muted-foreground">
                            {{ bericht.sent_at }} · {{ bericht.author ?? 'Onbekend' }} · {{ bericht.recipients_count }}
                            {{ bericht.recipients_count === 1 ? 'ontvanger' : 'ontvangers' }}
                        </p>
                    </div>
                </div>

                <p v-else class="mt-3 text-sm text-muted-foreground">Er is nog geen mededeling verstuurd.</p>
            </div>
        </div>
    </AppLayout>
</template>
