<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { CalendarRange, Info, MapPin, ShoppingBag, Ticket, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';

/**
 * De shop: het aanbod van de school, per soort gegroepeerd, compact.
 *
 * Elk aanbod brengt je direct in de inschrijving van dát aanbod; "voor wie"
 * kies je daar. Alleen een privétraining boek je hier zelf, op een moment:
 * daar hoort geen inschrijfstap bij maar een agenda.
 */
interface Product {
    id: number;
    name: string;
    description: string | null;
    type: string;
    type_key: string;
    amount: string;
    is_free: boolean;
    billing: string;
    period: string | null;
    location: string | null;
    spots_left: number | null;
    is_full: boolean;
    image: string | null;
    credits: number | null;
    validity_months: number | null;
    enroll_url: string;
    slots: { id: number; day: string; time: string; trainer: string | null; location: string | null }[];
}

const props = defineProps<{
    groups: { key: string; title: string; products: Product[] }[];
    players: { id: number; name: string }[];
    connected: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Shop', href: '/shop' }];

const page = usePage();
// Elke fout van de server, niet alleen die bij betalen: een moment dat net geboekt is hoort er ook bij.
const fout = computed(() => Object.values((page.props.errors as Record<string, string> | undefined) ?? {})[0] ?? null);

const leeg = computed(() => props.groups.every((g) => g.products.length === 0));

// Alleen bij een privétraining: voor wie, en op welk moment.
const speler = ref<number>(props.players[0]?.id ?? 0);
const gekozenMoment = ref<Record<number, number | null>>({});
const bezig = ref<number | null>(null);

const boek = (product: Product) => {
    bezig.value = product.id;

    router.post(
        '/shop/' + product.id,
        { player_id: speler.value, slot_id: gekozenMoment.value[product.id] ?? null },
        { onFinish: () => (bezig.value = null) },
    );
};
</script>

<template>
    <Head title="Shop" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-3xl p-4">

            <h1 class="text-2xl font-semibold tracking-tight">Shop</h1>
            <p class="mt-1 text-sm text-muted-foreground">Het aanbod van de school. Tik op iets om je kind in te schrijven.</p>

            <p v-if="fout" class="mt-4 rounded-xl border border-destructive/40 bg-destructive/10 p-4 text-sm text-destructive">{{ fout }}</p>

            <div v-if="leeg" class="mt-5 rounded-2xl border border-dashed border-border bg-card/50 p-10 text-center">
                <p class="font-medium">Nog niets om je voor in te schrijven</p>
                <p class="mt-1 text-sm text-muted-foreground">Zodra de school iets openzet, staat het hier.</p>
            </div>

            <template v-for="groep in groups" :key="groep.key">
                <section v-if="groep.products.length" class="mt-6">
                    <h2 class="font-semibold">{{ groep.title }}</h2>

                    <div class="mt-3 space-y-3">
                        <article
                            v-for="product in groep.products"
                            :key="product.id"
                            class="overflow-hidden rounded-xl border border-border bg-card shadow-sm"
                        >
                            <img v-if="product.image" :src="product.image" alt="" class="h-36 w-full object-cover" />

                            <div class="p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <p class="min-w-0 flex-1 font-medium">{{ product.name }}</p>
                                    <p class="tabular shrink-0 text-right">
                                        <span class="text-lg font-bold">{{ product.is_free ? 'gratis' : product.amount }}</span>
                                        <span v-if="!product.is_free" class="block text-xs text-muted-foreground">{{ product.billing }}</span>
                                    </p>
                                </div>

                                <p v-if="product.description" class="mt-1 line-clamp-2 text-sm text-muted-foreground">{{ product.description }}</p>

                                <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                    <span v-if="product.period" class="tabular flex items-center gap-1"
                                        ><CalendarRange class="size-3.5" />{{ product.period }}</span
                                    >
                                    <span v-if="product.location" class="flex items-center gap-1"
                                        ><MapPin class="size-3.5" />{{ product.location }}</span
                                    >
                                    <span v-if="product.credits" class="flex items-center gap-1">
                                        <Ticket class="size-3.5" />{{ product.credits }} beurten<template v-if="product.validity_months"
                                            >, {{ product.validity_months }} mnd geldig</template
                                        >
                                    </span>
                                    <span v-if="product.is_full" class="flex items-center gap-1 font-medium text-warning"
                                        ><Users class="size-3.5" />Vol · wachtlijst</span
                                    >
                                    <span
                                        v-else-if="product.spots_left !== null"
                                        class="flex items-center gap-1"
                                        :class="product.spots_left <= 3 ? 'font-medium text-warning' : ''"
                                    >
                                        <Users class="size-3.5" />Nog {{ product.spots_left }} {{ product.spots_left === 1 ? 'plek' : 'plekken' }}
                                    </span>
                                </div>

                                <!-- Privétraining: hier boeken, op een moment. -->
                                <template v-if="product.type_key === 'privetraining'">
                                    <div v-if="players.length > 1" class="mt-3 grid gap-1">
                                        <label :for="'speler_' + product.id" class="text-sm font-medium">Voor wie?</label>
                                        <select
                                            :id="'speler_' + product.id"
                                            v-model="speler"
                                            class="h-11 rounded-lg border border-input bg-card px-3 text-sm outline-none focus:border-primary"
                                        >
                                            <option v-for="p in players" :key="p.id" :value="p.id">{{ p.name }}</option>
                                        </select>
                                    </div>

                                    <div v-if="product.slots.length" class="mt-3 grid gap-2">
                                        <p class="text-sm font-medium">Kies een moment</p>
                                        <label
                                            v-for="moment in product.slots"
                                            :key="moment.id"
                                            class="flex min-h-11 cursor-pointer items-start gap-3 rounded-lg border p-3 transition"
                                            :class="
                                                gekozenMoment[product.id] === moment.id
                                                    ? 'border-primary bg-primary/5'
                                                    : 'border-border hover:border-primary/40'
                                            "
                                        >
                                            <input
                                                v-model="gekozenMoment[product.id]"
                                                type="radio"
                                                :value="moment.id"
                                                class="mt-0.5 size-4 shrink-0 accent-primary"
                                            />
                                            <span class="min-w-0">
                                                <span class="block text-sm font-medium first-letter:uppercase"
                                                    >{{ moment.day }} · {{ moment.time }}</span
                                                >
                                                <span class="block text-xs text-muted-foreground">
                                                    {{ moment.trainer ?? 'trainer volgt'
                                                    }}<template v-if="moment.location"> · {{ moment.location }}</template>
                                                </span>
                                            </span>
                                        </label>
                                    </div>
                                    <p v-else class="mt-3 rounded-lg bg-secondary p-3 text-sm text-muted-foreground">
                                        Er staan nu geen vrije momenten. Neem contact op met de school.
                                    </p>

                                    <button
                                        type="button"
                                        class="mt-3 inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground transition hover:opacity-90 disabled:opacity-60 sm:w-auto"
                                        :disabled="bezig === product.id || !gekozenMoment[product.id]"
                                        @click="boek(product)"
                                    >
                                        <ShoppingBag class="size-4" />
                                        {{ connected && !product.is_free ? 'Boeken en afrekenen' : 'Boeken' }}
                                    </button>
                                </template>

                                <!-- Al het andere: direct de inschrijving van dit aanbod in. -->
                                <a
                                    v-else
                                    :href="product.enroll_url"
                                    class="mt-3 inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground transition hover:opacity-90 sm:w-auto"
                                >
                                    <ShoppingBag class="size-4" />
                                    {{ product.is_full ? 'Op de wachtlijst' : 'Inschrijven' }}
                                </a>
                            </div>
                        </article>
                    </div>
                </section>
            </template>

            <p v-if="!leeg" class="mt-6 flex items-start gap-2 text-xs text-muted-foreground">
                <Info class="mt-0.5 size-3.5 shrink-0" />
                <span>Bij het inschrijven kies je voor welk kind, en zie je precies wat je betaalt vóórdat je bevestigt.</span>
            </p>
        </div>
    </AppLayout>
</template>
