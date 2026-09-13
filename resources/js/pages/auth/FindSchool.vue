<script setup lang="ts">
import TextLink from '@/components/TextLink.vue';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronRight, School, Search } from 'lucide-vue-next';
import { ref, watch } from 'vue';

/**
 * Zoek je school: van de inlogpagina naar de inschrijfpagina van een school.
 *
 * Zoeken gaat naar de server bij elke wijziging (met een korte pauze), zodat
 * je onder het typen de lijst ziet - een ouder die "keep" typt vindt zo
 * Keepersschool Rob zonder op een knop te drukken.
 */
const props = defineProps<{
    query: string;
    schools: { name: string; logo: string | null; href: string }[];
}>();

const zoek = ref(props.query);
let timer: ReturnType<typeof setTimeout> | null = null;

watch(zoek, (waarde) => {
    if (timer) {
        clearTimeout(timer);
    }

    timer = setTimeout(() => {
        router.get('/scholen/zoeken', { q: waarde.trim() }, { preserveState: true, replace: true, only: ['schools', 'query'] });
    }, 250);
});
</script>

<template>
    <AuthBase title="Zoek je school" description="Typ de naam van de voetbal- of keepersschool. Je komt dan op haar inschrijfpagina.">
        <Head title="Zoek je school" />

        <form class="flex flex-col gap-5" @submit.prevent>
            <label class="relative block">
                <span class="sr-only">Naam van de school</span>
                <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <input
                    v-model="zoek"
                    type="search"
                    autofocus
                    autocomplete="off"
                    placeholder="Bijvoorbeeld Keepersschool Rob"
                    class="h-12 w-full rounded-lg border border-input bg-card pl-10 pr-3 text-base text-foreground outline-none placeholder:text-muted-foreground focus:border-primary"
                />
            </label>

            <ul v-if="schools.length" class="divide-y divide-border overflow-hidden rounded-xl border border-border bg-card">
                <li v-for="school in schools" :key="school.href">
                    <Link :href="school.href" class="flex min-h-14 items-center gap-3 px-3 py-2 transition hover:bg-secondary">
                        <span class="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-secondary">
                            <img v-if="school.logo" :src="school.logo" :alt="''" class="size-full object-contain" />
                            <School v-else class="size-5 text-muted-foreground" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium">{{ school.name }}</span>
                            <span class="block text-xs text-muted-foreground">Naar de inschrijfpagina</span>
                        </span>
                        <ChevronRight class="size-4 shrink-0 text-muted-foreground" />
                    </Link>
                </li>
            </ul>

            <p v-else-if="zoek.trim().length >= 2" class="rounded-xl border border-dashed border-border p-4 text-center text-sm text-muted-foreground">
                Geen school gevonden met "{{ zoek.trim() }}". Vraag je school naar de link van haar inschrijfpagina.
            </p>

            <p v-else class="text-center text-xs text-muted-foreground">Vanaf twee letters zie je hier de scholen die erbij passen.</p>

            <p class="text-center text-sm text-muted-foreground">
                Heb je al een account?
                <TextLink :href="route('login')">Inloggen</TextLink>
            </p>
        </form>
    </AuthBase>
</template>
