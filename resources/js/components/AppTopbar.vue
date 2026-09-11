<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import { useAppMode } from '@/composables/useAppMode';
import { navIconen, navIcoon } from '@/lib/nav-icons';
import { type NavGroup, type SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Bell, ChevronDown, CircleHelp, LogOut, Menu, Plus, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

/**
 * De menubalk bovenin.
 *
 * Welke items er staan bepaalt de server (MainNavigation en QuickActions), op
 * basis van de policies. Hier wordt alleen de iconennaam vertaald naar een
 * component (via lib/nav-icons, gedeeld met de tabbalk onderin), zodat het menu
 * nooit een item kan tonen dat je toch niet mag openen.
 *
 * De balk is donkerblauw en de werkvloer eronder licht. Dat is geen sier: het
 * scheidt "waar ben ik in de app" van "waar werk ik aan", en het is dezelfde
 * donkere kant van het merk als de spelerskaart.
 *
 * Drie dingen staan rechts vast op volle hoogte: de bel, de plusknop en jij.
 * Die zoek je op positie en niet op vorm, dus ze horen altijd op dezelfde plek
 * te staan, ook als de balk verder leegloopt bij een ouder.
 */

const page = usePage<SharedData>();

const groepen = computed<NavGroup[]>(() =>
    (page.props.nav ?? []).map((groep) => ({
        title: groep.title,
        href: groep.href,
        icon: navIcoon(groep.icon),
        badge: (groep as { badge?: number }).badge ?? 0,
        // Waar de rondleiding naar wijst. Op de iconennaam en niet op de titel:
        // een school die een menu-item hernoemt hoort de tour niet te breken.
        tour: tourAnker(groep),
        items: (groep.items ?? []).map((item) => ({
            title: item.title,
            href: item.href,
            icon: navIcoon(item.icon),
            badge: (item as { badge?: number }).badge ?? 0,
        })),
    })),
);

// Draait dit als app? Dan staat het menu in de tabbalk onderin, en is de
// hamburger hierboven een tweede weg naar hetzelfde — die laten we weg.
const { isApp } = useAppMode();

const acties = computed(() => page.props.quickAdd ?? []);
const ongelezen = computed(() => page.props.unreadNotifications ?? 0);
const rol = computed(() => (page.props.auth.roles ?? [])[0] ?? null);

// De initialen in het rondje. Twee letters: meer wordt onleesbaar klein.
const initialen = computed(() =>
    (page.props.auth.user?.name ?? '')
        .split(' ')
        .filter(Boolean)
        .map((deel) => deel[0])
        .slice(0, 2)
        .join('')
        .toUpperCase(),
);

/**
 * Welk anker van de rondleiding bij deze groep hoort.
 *
 * Een groep met één item wordt dat item (zie MainNavigation), dus de tour moet
 * ook naar de items kunnen kijken. Vindt hij niets, dan krijgt de groep geen
 * anker en slaat die stap zichzelf over.
 */
const TOUR_ANKERS = ['reports', 'calendar', 'players', 'enrollments', 'settings'];

const tourAnker = (groep: { icon: string; href?: string | null; items?: { icon: string; href: string }[] }): string | undefined => {
    const kandidaten = [groep.icon, ...(groep.items ?? []).map((item) => item.icon)];
    const treffer = kandidaten.find((icoon) => TOUR_ANKERS.includes(icoon));

    // "Klanten" heet in de tour clients; het icoon heet players.
    return treffer === 'players' ? 'clients' : treffer;
};

const startRondleiding = () => {
    sluit();
    router.post('/onboarding/rondleiding/opnieuw');
};

const huidig = computed(() => page.url.split('?')[0]);

// Ook actief op onderliggende schermen: /players/1/reports/create hoort bij
// Rapporten. Alleen een heel pad-deel telt, zodat /trainings niet oplicht op
// /trainingsmateriaal.
const raakt = (href: string) => huidig.value === href || huidig.value.startsWith(href + '/');

const isActief = (groep: NavGroup) => (groep.href ? raakt(groep.href) : groep.items.some((item) => raakt(item.href)));

// Welke uitklap staat open. Eén tegelijk, en klikken sluit hem weer.
const open = ref<string | null>(null);
const mobiel = ref(false);

const wissel = (sleutel: string) => (open.value = open.value === sleutel ? null : sleutel);

const sluit = () => {
    open.value = null;
    mobiel.value = false;
};

// Navigeren sluit alles: anders blijft een uitklap over de nieuwe pagina staan.
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
    <!-- De balk heeft zijn eigen tint (--topbar in app.css), zoals hij altijd was. -->
    <header class="theme-donker sticky top-0 z-40 bg-[hsl(var(--topbar))] text-foreground">
        <!-- Klik naast een open uitklap sluit hem. Zit achter de balk, dus
             hij vangt niets af zolang er niets openstaat. -->
        <div v-if="open" class="fixed inset-0 -z-10" @click="sluit"></div>

        <div class="flex h-14 w-full min-w-0 items-center">
            <Link
                href="/dashboard"
                class="flex h-full shrink-0 items-center px-3 sm:px-4"
                :title="page.props.branding?.name ?? undefined"
                @click="sluit"
            >
                <AppLogo :with-name="false" />
            </Link>

            <!-- Groot scherm: de balk zelf -->
            <nav class="hidden min-w-0 flex-1 items-center gap-0.5 lg:flex" aria-label="Hoofdmenu">
                <template v-for="groep in groepen" :key="groep.title">
                    <Link
                        v-if="groep.href"
                        :href="groep.href"
                        :data-tour="groep.tour"
                        class="min-h-11 rounded-lg px-3 py-2 text-sm font-medium transition"
                        :class="
                            isActief(groep) ? 'bg-foreground/10 text-foreground' : 'text-foreground/75 hover:bg-foreground/10 hover:text-foreground'
                        "
                        @click="sluit"
                    >
                        {{ groep.title }}
                    </Link>

                    <div v-else class="relative" :data-tour="groep.tour">
                        <button
                            type="button"
                            class="flex min-h-11 items-center gap-1 rounded-lg px-3 py-2 text-sm font-medium transition"
                            :class="
                                isActief(groep) || open === groep.title
                                    ? 'bg-foreground/10 text-foreground'
                                    : 'text-foreground/75 hover:bg-foreground/10 hover:text-foreground'
                            "
                            :aria-expanded="open === groep.title"
                            aria-haspopup="true"
                            @click="wissel(groep.title)"
                        >
                            {{ groep.title }}
                            <span v-if="groep.badge" class="size-2 rounded-full bg-warning" aria-label="Er staat iets open"></span>
                            <ChevronDown class="size-3.5 transition" :class="open === groep.title ? 'rotate-180' : ''" />
                        </button>

                        <div
                            v-if="open === groep.title"
                            class="absolute left-0 top-full mt-1 min-w-56 overflow-hidden rounded-xl border border-border bg-card py-1 shadow-lg"
                        >
                            <Link
                                v-for="item in groep.items"
                                :key="item.href"
                                :href="item.href"
                                class="flex items-center gap-3 px-3 py-2.5 text-sm transition"
                                :class="raakt(item.href) ? 'bg-primary/10 text-primary' : 'hover:bg-foreground/10'"
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
                        </div>
                    </div>
                </template>
            </nav>

            <!-- Rechts, alles op volle hoogte -->
            <div class="ml-auto flex h-full shrink-0 items-stretch">
                <!-- De rondleiding opnieuw. Alleen voor wie de school bedient;
                     een ouder krijgt er geen, dus een vraagteken zou hem naar
                     iets wijzen dat niet bestaat. -->
                <button
                    v-if="page.props.onboarding?.canRestartTour"
                    type="button"
                    class="hidden items-center px-3 text-foreground/75 transition hover:bg-foreground/10 hover:text-foreground sm:flex"
                    aria-label="Rondleiding opnieuw starten"
                    title="Rondleiding opnieuw starten"
                    @click="startRondleiding"
                >
                    <CircleHelp class="size-5" />
                </button>

                <Link
                    href="/notifications"
                    class="relative flex items-center px-3 text-foreground/75 transition hover:bg-foreground/10 hover:text-foreground"
                    :aria-label="ongelezen > 0 ? ongelezen + ' ongelezen meldingen' : 'Meldingen'"
                >
                    <Bell class="size-5" />
                    <span
                        v-if="ongelezen > 0"
                        class="tabular absolute right-1.5 top-2.5 flex min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-bold leading-4 text-primary-foreground"
                    >
                        {{ ongelezen > 9 ? '9+' : ongelezen }}
                    </span>
                </Link>

                <!-- De plus. Geen acties, geen knop: een plus die een leeg
                     lijstje opent is erger dan geen plus. -->
                <div v-if="acties.length" class="relative flex">
                    <button
                        type="button"
                        class="flex items-center bg-primary px-4 text-primary-foreground transition hover:opacity-90"
                        :aria-expanded="open === 'plus'"
                        aria-haspopup="true"
                        aria-label="Toevoegen"
                        @click="wissel('plus')"
                    >
                        <Plus class="size-5" />
                    </button>

                    <div
                        v-if="open === 'plus'"
                        class="absolute right-0 top-full mt-1 min-w-56 overflow-hidden rounded-xl border border-border bg-card py-1 shadow-lg"
                    >
                        <Link
                            v-for="actie in acties"
                            :key="actie.href + actie.title"
                            :href="actie.href"
                            class="flex items-center gap-3 px-3 py-2.5 text-sm transition hover:bg-foreground/10"
                            @click="sluit"
                        >
                            <component :is="navIconen[actie.icon] ?? Plus" class="size-4 shrink-0 opacity-70" />
                            {{ actie.title }}
                        </Link>
                    </div>
                </div>

                <div class="relative flex">
                    <button
                        type="button"
                        class="flex items-center gap-2 px-3 text-left transition hover:bg-foreground/10"
                        :aria-expanded="open === 'gebruiker'"
                        aria-haspopup="true"
                        @click="wissel('gebruiker')"
                    >
                        <!-- Je eigen foto als je er een hebt gezet; anders je
                             initialen. Zonder dit zet je op je profiel wel een
                             foto, maar zie je nergens dat het gelukt is. -->
                        <img
                            v-if="page.props.auth.user?.photo_url"
                            :src="page.props.auth.user.photo_url"
                            :alt="page.props.auth.user?.name ?? ''"
                            class="size-8 shrink-0 rounded-full object-cover"
                        />
                        <span
                            v-else
                            class="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/15 text-xs font-bold text-primary"
                        >
                            {{ initialen }}
                        </span>
                        <span class="hidden min-w-0 leading-tight sm:block">
                            <span class="block max-w-32 truncate text-sm font-medium">{{ page.props.auth.user?.name }}</span>
                            <span v-if="rol" class="block truncate text-[11px] text-foreground/60">{{ rol }}</span>
                        </span>
                        <ChevronDown class="hidden size-3.5 shrink-0 text-foreground/60 sm:block" />
                    </button>

                    <div
                        v-if="open === 'gebruiker'"
                        class="absolute right-0 top-full mt-1 min-w-48 overflow-hidden rounded-xl border border-border bg-card py-1 shadow-lg"
                    >
                        <!-- De rondleiding staat ook hier, met een woord erbij.
                             Het vraagteken in de balk is voor wie hem kent; wie
                             hem zoekt kijkt bij zijn naam. -->
                        <button
                            v-if="page.props.onboarding?.canRestartTour"
                            type="button"
                            class="flex w-full items-center gap-3 px-3 py-2.5 text-sm transition hover:bg-foreground/10"
                            @click="startRondleiding"
                        >
                            <CircleHelp class="size-4 shrink-0 opacity-70" />
                            Rondleiding starten
                        </button>

                        <!-- Verder alleen uitloggen: je instellingen staan al
                             onder Mijn bedrijf, en twee wegen naar hetzelfde
                             scherm laat je zoeken welke de goede is. -->
                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            class="flex w-full items-center gap-3 px-3 py-2.5 text-sm transition hover:bg-foreground/10"
                        >
                            <LogOut class="size-4 shrink-0 opacity-70" />
                            Uitloggen
                        </Link>
                    </div>
                </div>

                <button
                    v-if="!isApp"
                    type="button"
                    class="flex items-center px-3 text-foreground/75 transition hover:bg-foreground/10 hover:text-foreground lg:hidden"
                    :aria-expanded="mobiel"
                    aria-label="Menu"
                    @click="mobiel = !mobiel"
                >
                    <component :is="mobiel ? X : Menu" class="size-5" />
                </button>
            </div>
        </div>

        <!-- Klein scherm: alles onder elkaar, geen uitklappen in uitklappen -->
        <nav v-if="mobiel" class="border-t border-border px-3 pb-3 lg:hidden" aria-label="Hoofdmenu">
            <button
                v-if="page.props.onboarding?.canRestartTour"
                type="button"
                class="mt-3 flex min-h-11 w-full items-center gap-3 rounded-lg px-3 text-sm text-foreground/75"
                @click="startRondleiding"
            >
                <CircleHelp class="size-4 shrink-0 opacity-70" />
                Rondleiding opnieuw
            </button>

            <div v-for="groep in groepen" :key="groep.title" class="mt-3">
                <Link
                    v-if="groep.href"
                    :href="groep.href"
                    class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium"
                    :class="isActief(groep) ? 'bg-foreground/10' : 'text-foreground/75'"
                    @click="sluit"
                >
                    <component :is="groep.icon" class="size-4 shrink-0 opacity-70" />
                    {{ groep.title }}
                </Link>

                <template v-else>
                    <p class="flex items-center gap-2 px-3 text-xs font-medium uppercase tracking-wide text-foreground/50">
                        {{ groep.title }}
                        <span v-if="groep.badge" class="size-2 rounded-full bg-warning"></span>
                    </p>
                    <Link
                        v-for="item in groep.items"
                        :key="item.href"
                        :href="item.href"
                        class="mt-1 flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm"
                        :class="raakt(item.href) ? 'bg-primary/10 text-primary' : 'text-foreground/75'"
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
        </nav>
    </header>
</template>
