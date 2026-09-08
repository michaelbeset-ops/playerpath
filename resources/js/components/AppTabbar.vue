<script setup lang="ts">
import { useAppMode } from '@/composables/useAppMode';
import { navIcoon } from '@/lib/nav-icons';
import { type NavGroup, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { MoreHorizontal, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

/**
 * Het menu onderin, als de app als app draait.
 *
 * In een browsertabblad hoort het menu bovenaan: daar staat het adres, en
 * onderin zitten de knoppen van de browser al. Als geïnstalleerde app is die
 * onderrand leeg, en het is het enige stuk scherm dat je staand met één duim
 * comfortabel raakt. Vandaar dit: dezelfde items, andere plek.
 *
 * Vier regels die deze balk bruikbaar houden:
 *
 * 1. **Het is hetzelfde menu.** De items komen uit `MainNavigation`, net als de
 *    balk bovenin — dus wat je hier ziet is wat je mag, en er is geen tweede
 *    lijst die kan gaan afwijken.
 * 2. **Hooguit vijf tabs.** Op 375 pixels is dat 75 per tab; bij zes wordt het
 *    label onleesbaar en raak je met een duim de verkeerde. Passen ze niet
 *    allemaal, dan zijn het er vier plus "Meer".
 * 3. **Een groep opent een blad, geen tweede balk.** Uitklappen bovenop een
 *    balk die zelf al onderaan staat valt half buiten beeld.
 * 4. **De actieve tab is te zien zonder kleur alleen**: het icoon krijgt een
 *    vlak achter zich én het label wordt zwaarder.
 */
const page = usePage<SharedData>();
const { isApp } = useAppMode();

const groepen = computed<NavGroup[]>(() =>
    (page.props.nav ?? []).map((groep) => ({
        title: groep.title,
        href: groep.href,
        icon: navIcoon(groep.icon),
        badge: (groep as { badge?: number }).badge ?? 0,
        items: (groep.items ?? []).map((item) => ({
            title: item.title,
            href: item.href,
            icon: navIcoon(item.icon),
            badge: (item as { badge?: number }).badge ?? 0,
        })),
    })),
);

/** Hooguit vijf; passen ze niet, dan vier plus "Meer". */
const MAX = 5;

const tabs = computed(() => (groepen.value.length <= MAX ? groepen.value : groepen.value.slice(0, MAX - 1)));
const rest = computed(() => (groepen.value.length <= MAX ? [] : groepen.value.slice(MAX - 1)));

const restBadge = computed(() => rest.value.reduce((som, groep) => som + (groep.badge ?? 0), 0));

const huidig = computed(() => page.url.split('?')[0]);

// Ook actief op onderliggende schermen; zelfde regel als in de balk bovenin.
const raakt = (href: string) => huidig.value === href || huidig.value.startsWith(href + '/');

const isActief = (groep: NavGroup) => (groep.href ? raakt(groep.href) : groep.items.some((item) => raakt(item.href)));

// In "Meer" zit een groep die actief kan zijn; dan hoort die tab op te lichten.
const meerActief = computed(() => rest.value.some((groep) => isActief(groep)));

// Welk blad staat open: de titel van een groep, of 'meer'. Eén tegelijk.
const blad = ref<string | null>(null);

const open = computed(() =>
    blad.value === 'meer' ? rest.value : blad.value === null ? [] : groepen.value.filter((groep) => groep.title === blad.value),
);

const sluit = () => (blad.value = null);

const tik = (groep: NavGroup) => {
    // Een groep zonder eigen items is zelf een link; die vangt Inertia af.
    blad.value = blad.value === groep.title ? null : groep.title;
};

// Navigeren sluit het blad: anders blijft het over de nieuwe pagina staan.
watch(() => page.url, sluit);

const opToets = (event: KeyboardEvent) => {
    if (event.key === 'Escape') {
        sluit();
    }
};

onMounted(() => document.addEventListener('keydown', opToets));
onBeforeUnmount(() => document.removeEventListener('keydown', opToets));
</script>

<template>
    <!-- Alleen als app, alleen op smal: op een laptop is de balk bovenin
         gewoon de beste plek en is dit een strook verspild scherm. -->
    <div v-if="isApp && groepen.length" class="lg:hidden">
        <!-- Het blad met de items van één groep. Boven de balk, niet erover. -->
        <Teleport to="body">
            <div v-if="blad" class="fixed inset-0 z-50 lg:hidden">
                <div class="absolute inset-0 bg-foreground/40" @click="sluit"></div>

                <div
                    class="theme-donker absolute inset-x-0 bottom-[var(--pp-tabbar)] max-h-[70vh] overflow-y-auto rounded-t-2xl bg-[hsl(var(--topbar))] pb-2 text-foreground shadow-2xl"
                >
                    <div class="sticky top-0 flex items-center justify-between gap-3 bg-[hsl(var(--topbar))] px-4 pb-2 pt-3">
                        <p class="min-w-0 truncate font-medium">{{ blad === 'meer' ? 'Meer' : blad }}</p>
                        <button
                            type="button"
                            class="-mr-2 flex size-11 shrink-0 items-center justify-center rounded-lg text-foreground/70 transition hover:bg-foreground/10"
                            aria-label="Sluiten"
                            @click="sluit"
                        >
                            <X class="size-5" />
                        </button>
                    </div>

                    <div class="px-3 pb-3">
                        <template v-for="groep in open" :key="groep.title">
                            <!-- In "Meer" staan meerdere groepen onder elkaar, met
                                 hun naam erboven; bij één groep is die naam de
                                 kop van het blad en zou hij er twee keer staan. -->
                            <p
                                v-if="blad === 'meer' && groep.items.length"
                                class="mt-3 px-3 text-xs font-medium uppercase tracking-wide text-foreground/50"
                            >
                                {{ groep.title }}
                            </p>

                            <Link
                                v-if="groep.href"
                                :href="groep.href"
                                class="mt-1 flex min-h-12 items-center gap-3 rounded-lg px-3 text-sm font-medium"
                                :class="isActief(groep) ? 'bg-primary/15 text-primary' : 'text-foreground/80'"
                                @click="sluit"
                            >
                                <component :is="groep.icon" class="size-4 shrink-0 opacity-70" />
                                {{ groep.title }}
                            </Link>

                            <Link
                                v-for="item in groep.items"
                                v-else
                                :key="item.href"
                                :href="item.href"
                                class="mt-1 flex min-h-12 items-center gap-3 rounded-lg px-3 text-sm"
                                :class="raakt(item.href) ? 'bg-primary/15 text-primary' : 'text-foreground/80'"
                                @click="sluit"
                            >
                                <component :is="item.icon" class="size-4 shrink-0 opacity-70" />
                                {{ item.title }}
                                <span
                                    v-if="item.badge"
                                    class="tabular ml-auto rounded-full bg-warning px-1.5 text-[10px] font-semibold text-warning-foreground"
                                    >{{ item.badge }}</span
                                >
                            </Link>
                        </template>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- De balk zelf. De onderrand loopt door tot onder het home-streepje,
             zodat er geen streepje achtergrond onder de balk vandaan piept. -->
        <nav
            class="theme-donker fixed inset-x-0 bottom-0 z-40 border-t border-foreground/10 bg-[hsl(var(--topbar))] pb-[env(safe-area-inset-bottom,0px)] text-foreground"
            aria-label="Hoofdmenu"
        >
            <div class="flex h-14 items-stretch">
                <component
                    :is="groep.href ? Link : 'button'"
                    v-for="groep in tabs"
                    :key="groep.title"
                    :href="groep.href ?? undefined"
                    :type="groep.href ? undefined : 'button'"
                    class="relative flex min-w-0 flex-1 flex-col items-center justify-center gap-0.5 px-1 transition"
                    :class="isActief(groep) || blad === groep.title ? 'text-primary' : 'text-foreground/65'"
                    :aria-current="isActief(groep) ? 'page' : undefined"
                    :aria-expanded="groep.href ? undefined : blad === groep.title"
                    @click="groep.href ? sluit() : tik(groep)"
                >
                    <span
                        class="flex h-6 w-12 items-center justify-center rounded-full transition"
                        :class="isActief(groep) || blad === groep.title ? 'bg-primary/15' : ''"
                    >
                        <component :is="groep.icon" class="size-5" />
                    </span>
                    <span class="w-full truncate px-0.5 text-center text-[10px] leading-tight" :class="isActief(groep) ? 'font-semibold' : ''">
                        {{ groep.title }}
                    </span>
                    <span v-if="groep.badge" class="absolute right-1/2 top-1.5 -mr-4 size-2 rounded-full bg-warning" aria-hidden="true"></span>
                </component>

                <button
                    v-if="rest.length"
                    type="button"
                    class="relative flex min-w-0 flex-1 flex-col items-center justify-center gap-0.5 px-1 transition"
                    :class="meerActief || blad === 'meer' ? 'text-primary' : 'text-foreground/65'"
                    :aria-expanded="blad === 'meer'"
                    @click="blad = blad === 'meer' ? null : 'meer'"
                >
                    <span
                        class="flex h-6 w-12 items-center justify-center rounded-full transition"
                        :class="meerActief || blad === 'meer' ? 'bg-primary/15' : ''"
                    >
                        <MoreHorizontal class="size-5" />
                    </span>
                    <span class="w-full truncate px-0.5 text-center text-[10px] leading-tight" :class="meerActief ? 'font-semibold' : ''">Meer</span>
                    <span v-if="restBadge" class="absolute right-1/2 top-1.5 -mr-4 size-2 rounded-full bg-warning" aria-hidden="true"></span>
                </button>
            </div>
        </nav>
    </div>
</template>
