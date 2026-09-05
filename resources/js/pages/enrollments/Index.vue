<script setup lang="ts">
import FlashMessage from '@/components/FlashMessage.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Check, Copy, Inbox, Mail, Phone, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Inschrijving {
    id: number;
    child_name: string;
    age: number | null;
    date_of_birth: string;
    position: string;
    guardian_name: string;
    guardian_email: string;
    guardian_phone: string | null;
    relationship: string | null;
    plan: string | null;
    payment_method: string | null;
    note: string | null;
    status: string;
    status_label: string;
    received: string;
    handled_at: string | null;
    player_id: number | null;
}

const props = defineProps<{
    pending: Inschrijving[];
    handled: Inschrijving[];
    formUrl: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Inschrijvingen', href: '/enrollments' }];

const page = usePage<SharedData>();
const fout = computed(() => (page.props as any).errors?.enrollment as string | undefined);

const gekopieerd = ref(false);

const kopieer = async () => {
    await navigator.clipboard.writeText(props.formUrl);
    gekopieerd.value = true;
    setTimeout(() => (gekopieerd.value = false), 2000);
};

const keurGoed = (i: Inschrijving) => {
    const wat = i.plan ? ' en een abonnement (' + i.plan + ')' : '';

    if (confirm(`${i.child_name} toevoegen als speler, met een account voor ${i.guardian_name}${wat}?`)) {
        router.post('/enrollments/' + i.id + '/approve', {}, { preserveScroll: true });
    }
};

const wijsAf = (i: Inschrijving) => {
    if (confirm(`De inschrijving van ${i.child_name} afwijzen?`)) {
        router.post('/enrollments/' + i.id + '/decline', {}, { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Inschrijvingen" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-3xl p-4">
            <FlashMessage />

            <p v-if="fout" class="mb-4 rounded-xl border border-destructive/30 bg-destructive/5 p-4 text-sm text-destructive">{{ fout }}</p>

            <h1 class="text-2xl font-semibold tracking-tight">Inschrijvingen</h1>
            <p class="mt-1 text-sm text-muted-foreground">Ouders melden hun kind aan via jouw inschrijfformulier. Jij keurt goed.</p>

            <!-- De link naar het formulier: dit is wat je op je website en in WhatsApp zet -->
            <div class="mt-5 rounded-xl border border-border bg-card p-4 shadow-sm">
                <p class="text-sm font-medium">Jouw inschrijfformulier</p>
                <p class="mt-0.5 text-xs text-muted-foreground">Zet deze link op je website of stuur hem in WhatsApp. Iedereen kan zich ermee aanmelden; jij beslist wie erin komt.</p>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <input
                        :value="formUrl"
                        readonly
                        class="min-w-0 flex-1 rounded-lg border border-input bg-background px-3 py-2 text-xs text-muted-foreground"
                        @focus="($event.target as HTMLInputElement).select()"
                    />
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg border border-border px-3 py-2 text-sm font-medium transition hover:border-primary"
                        @click="kopieer"
                    >
                        <Check v-if="gekopieerd" class="size-4 text-primary" />
                        <Copy v-else class="size-4" />
                        {{ gekopieerd ? 'Gekopieerd' : 'Kopieer' }}
                    </button>
                    <a :href="formUrl" target="_blank" rel="noopener" class="text-sm font-medium text-primary underline underline-offset-4">Bekijk</a>
                </div>
            </div>

            <!-- Nieuw -->
            <h2 class="mt-8 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                Nieuw <span class="tabular">({{ pending.length }})</span>
            </h2>

            <div v-if="pending.length" class="mt-3 space-y-3">
                <div v-for="i in pending" :key="i.id" class="rounded-xl border border-warning/40 bg-card p-5 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-lg font-semibold">{{ i.child_name }}</p>
                            <p class="text-sm text-muted-foreground">{{ i.position }} &middot; {{ i.age }} jaar ({{ i.date_of_birth }})</p>
                        </div>
                        <p class="shrink-0 text-xs text-muted-foreground">{{ i.received }}</p>
                    </div>

                    <div class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                        <div class="rounded-lg bg-secondary/60 p-3">
                            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Ouder</p>
                            <p class="mt-1 font-medium">{{ i.guardian_name }} <span v-if="i.relationship" class="font-normal text-muted-foreground">({{ i.relationship }})</span></p>
                            <a :href="'mailto:' + i.guardian_email" class="mt-1 flex items-center gap-1.5 text-xs text-primary underline underline-offset-2">
                                <Mail class="size-3" /> {{ i.guardian_email }}
                            </a>
                            <a v-if="i.guardian_phone" :href="'tel:' + i.guardian_phone" class="mt-0.5 flex items-center gap-1.5 text-xs text-primary underline underline-offset-2">
                                <Phone class="size-3" /> {{ i.guardian_phone }}
                            </a>
                        </div>
                        <div class="rounded-lg bg-secondary/60 p-3">
                            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Gewenst</p>
                            <p class="mt-1 font-medium">{{ i.plan ?? 'Geen tarief gekozen' }}</p>
                            <p v-if="i.payment_method" class="text-xs text-muted-foreground">{{ i.payment_method }}</p>
                        </div>
                    </div>

                    <p v-if="i.note" class="mt-3 rounded-lg border border-border p-3 text-sm">{{ i.note }}</p>

                    <p class="mt-4 text-xs text-muted-foreground">
                        Goedkeuren maakt de speler aan, koppelt de ouder (die krijgt een e-mail om in te loggen)<template v-if="i.plan"> en start het abonnement</template>.
                    </p>

                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <Button @click="keurGoed(i)"><Check class="mr-2 size-4" /> Goedkeuren</Button>
                        <Button variant="secondary" @click="wijsAf(i)"><X class="mr-2 size-4" /> Afwijzen</Button>
                    </div>
                </div>
            </div>

            <div v-else class="mt-3 rounded-2xl border border-dashed border-border bg-card/50 p-8 text-center">
                <Inbox class="mx-auto size-8 text-muted-foreground/60" />
                <p class="mt-2 font-medium">Geen nieuwe inschrijvingen</p>
                <p class="mt-1 text-sm text-muted-foreground">Deel de link hierboven en ze komen hier binnen.</p>
            </div>

            <!-- Afgehandeld -->
            <template v-if="handled.length">
                <h2 class="mt-8 text-sm font-semibold uppercase tracking-wide text-muted-foreground">Afgehandeld</h2>
                <div class="mt-3 overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                    <div v-for="(i, index) in handled" :key="i.id" class="flex items-center gap-3 p-4" :class="index > 0 ? 'border-t border-border' : ''">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">{{ i.child_name }}</p>
                            <p class="truncate text-xs text-muted-foreground">{{ i.guardian_name }} &middot; {{ i.handled_at }}</p>
                        </div>
                        <Link v-if="i.player_id" :href="'/players/' + i.player_id" class="text-xs font-medium text-primary underline underline-offset-4">Speler</Link>
                        <span
                            class="shrink-0 rounded-lg px-2 py-1 text-xs font-medium"
                            :class="i.status === 'approved' ? 'bg-primary/10 text-primary' : 'bg-secondary text-muted-foreground'"
                        >
                            {{ i.status_label }}
                        </span>
                    </div>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
