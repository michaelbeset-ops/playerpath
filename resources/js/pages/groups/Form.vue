<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    group: { id: number; name: string; age_category: string | null; is_active: boolean } | null;
}>();

const bewerken = computed(() => props.group !== null);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Groepen', href: '/groups' },
    bewerken.value ? { title: props.group!.name, href: '/groups/' + props.group!.id + '/edit' } : { title: 'Nieuwe groep', href: '/groups/create' },
]);

const form = useForm({
    name: props.group?.name ?? '',
    age_category: props.group?.age_category ?? '',
    is_active: props.group?.is_active ?? true,
});

const opslaan = () => {
    if (bewerken.value) {
        form.put('/groups/' + props.group!.id);
    } else {
        form.post('/groups');
    }
};

const verwijderen = () => {
    if (confirm('Groep "' + props.group!.name + '" verwijderen? De spelers blijven bestaan, alleen de indeling verdwijnt.')) {
        router.delete('/groups/' + props.group!.id);
    }
};
</script>

<template>
    <Head :title="bewerken ? 'Groep bewerken' : 'Groep toevoegen'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-xl p-4">
            <h1 class="text-2xl font-semibold tracking-tight">
                {{ bewerken ? 'Groep bewerken' : 'Groep toevoegen' }}
            </h1>

            <form class="mt-6 space-y-5 rounded-xl border border-border bg-card p-5 shadow-sm" @submit.prevent="opslaan">
                <div class="grid gap-2">
                    <Label for="name">Naam</Label>
                    <Input id="name" v-model="form.name" required autofocus placeholder="Keepers ochtend" />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="age_category">Leeftijdscategorie <span class="text-muted-foreground">(optioneel)</span></Label>
                    <Input id="age_category" v-model="form.age_category" placeholder="Onder 12" />
                    <p class="text-xs text-muted-foreground">De categorie hoort bij de groep, niet bij de speler - die heeft een geboortedatum.</p>
                    <InputError :message="form.errors.age_category" />
                </div>

                <label class="flex items-center gap-3 rounded-lg border border-border p-3">
                    <input v-model="form.is_active" type="checkbox" class="size-4 accent-[hsl(var(--primary))]" />
                    <span class="text-sm font-medium">Actieve groep</span>
                </label>

                <div class="flex items-center gap-3 pt-1">
                    <Button type="submit" :disabled="form.processing">
                        <LoaderCircle v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                        {{ bewerken ? 'Wijzigingen opslaan' : 'Groep aanmaken' }}
                    </Button>

                    <Link
                        href="/groups"
                        class="inline-flex min-h-11 items-center text-sm text-muted-foreground underline underline-offset-4 hover:text-foreground"
                    >
                        Annuleren
                    </Link>
                </div>
            </form>

            <div v-if="bewerken" class="mt-4 rounded-xl border border-destructive/25 bg-destructive/5 p-5">
                <p class="font-medium text-destructive">Groep verwijderen</p>
                <p class="mt-1 text-sm text-muted-foreground">De spelers blijven bestaan; alleen hun indeling in deze groep verdwijnt.</p>
                <Button variant="destructive" class="mt-4" @click="verwijderen">
                    <Trash2 class="mr-2 size-4" />
                    Groep verwijderen
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
