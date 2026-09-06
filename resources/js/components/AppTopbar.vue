<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import NotificationBell from '@/components/NotificationBell.vue';
import { type NavGroup, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import {
    Building2,
    CalendarDays,
    CalendarRange,
    ChevronDown,
    ClipboardList,
    Contact,
    CreditCard,
    FileCheck2,
    FileDown,
    Inbox,
    LayoutGrid,
    LogOut,
    Megaphone,
    Menu,
    Palette,
    Receipt,
    Settings,
    Tag,
    UserCog,
    Users,
    UsersRound,
    X,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch, type Component } from 'vue';

/**
 * De menubalk bovenin.
 *
 * Welke items er staan bepaalt de server (MainNavigation), op basis van de
 * policies. Hier vertalen we alleen de iconennaam naar een component, zodat
 * het menu nooit een item kan tonen dat je toch niet mag openen.
 *
 * De balk is donker en de werkvloer eronder licht. Dat is geen sier: het
 * scheidt "waar ben ik in de app" van "waar werk ik aan", en het is dezelfde
 * donkere kant van het merk als de spelerskaart.
 */
const iconen: Record<string, Component> = {
    dashboard: LayoutGrid,
    players: Users,
    guardians: Contact,
    groups: UsersRound,
    trainings: CalendarDays,
    calendar: CalendarRange,
    reports: ClipboardList,
    subscriptions: Receipt,
    payments: CreditCard,
    plans: Tag,
    exports: FileDown,
    enrollments: Inbox,
    branding: Palette,
    accountability: FileCheck2,
    announcements: Megaphone,
    business: Building2,
    staff: UserCog,
    settings: Settings,
};

const page = usePage<SharedData>();

const groepen = computed<NavGroup[]>(() =>
    (page.props.nav ?? []).map((groep) => ({
        title: groep.title,
        href: groep.href,
        icon: iconen[groep.icon] ?? LayoutGrid,
        items: (groep.items ?? []).map((item) => ({
            title: item.title,
            href: item.href,
            icon: iconen[item.icon] ?? LayoutGrid,
        })),
    })),
);

const huidig = computed(() => page.url.split('?')[0]);

// Ook actief op onderliggende schermen: /players/1/reports/create hoort bij
// Rapporten. /clients mag niet oplichten op /clients/guardians, dus de
// langste treffer wint.
const raakt = (href: string) => huidig.value === href || huidig.value.startsWith(href + '/');

const isActief = (groep: NavGroup) => {
    if (groep.href) {
        return raakt(groep.href);
    }

    return groep.items.some((item) => raakt(item.href));
};

// Welke uitklap staat open. Eén tegelijk, en klikken sluit hem weer.
const open = ref<string | null>(null);
const mobiel = ref(false);

const wissel = (titel: string) => (open.value = open.value === titel ? null : titel);

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
    <header class="theme-donker sticky top-0 z-40 bg-background text-foreground">
        <!-- Klik naast een open uitklap sluit hem. Zit achter de balk, dus
             hij vangt niets af zolang er niets openstaat. -->
        <div v-if="open" class="fixed inset-0 -z-10" @click="sluit"></div>

        <div class="mx-auto flex h-14 w-full max-w-7xl items-center gap-2 px-3 sm:px-4">
            <!-- Alleen het merkteken: de naam van de school staat al in de
                 titel van het tabblad, en tweemaal dezelfde naam in een balk
                 van veertien pixels hoog is verspilde ruimte. -->
            <Link href="/dashboard" class="flex shrink-0 items-center rounded-lg p-1" :title="page.props.branding?.name" @click="sluit">
                <AppLogo :with-name="false" />
            </Link>

            <!-- Groot scherm: de balk zelf -->
            <nav class="ml-2 hidden min-w-0 flex-1 items-center gap-0.5 lg:flex" aria-label="Hoofdmenu">
                <template v-for="groep in groepen" :key="groep.title">
                    <Link
                        v-if="groep.href"
                        :href="groep.href"
                        class="rounded-lg px-3 py-2 text-sm font-medium transition"
                        :class="isActief(groep) ? 'bg-card text-foreground' : 'text-muted-foreground hover:bg-card/60 hover:text-foreground'"
                        @click="sluit"
                    >
                        {{ groep.title }}
                    </Link>

                    <div v-else class="relative">
                        <button
                            type="button"
                            class="flex items-center gap-1 rounded-lg px-3 py-2 text-sm font-medium transition"
                            :class="
                                isActief(groep) || open === groep.title
                                    ? 'bg-card text-foreground'
                                    : 'text-muted-foreground hover:bg-card/60 hover:text-foreground'
                            "
                            :aria-expanded="open === groep.title"
                            aria-haspopup="true"
                            @click="wissel(groep.title)"
                        >
                            {{ groep.title }}
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
                                :class="raakt(item.href) ? 'bg-primary/10 text-primary' : 'hover:bg-background/60'"
                                @click="sluit"
                            >
                                <component :is="item.icon" class="size-4 shrink-0 opacity-70" />
                                {{ item.title }}
                            </Link>
                        </div>
                    </div>
                </template>
            </nav>

            <div class="ml-auto flex shrink-0 items-center gap-1">
                <NotificationBell />

                <Link
                    href="/settings/profile"
                    class="hidden rounded-lg px-3 py-2 text-sm font-medium text-muted-foreground transition hover:bg-card/60 hover:text-foreground sm:block"
                >
                    <!-- inline-block, want max-width doet niets op een inline span -->
                    <span class="inline-block max-w-40 truncate align-bottom">{{ page.props.auth.user.name }}</span>
                </Link>

                <Link
                    href="/logout"
                    method="post"
                    as="button"
                    class="rounded-lg p-2 text-muted-foreground transition hover:bg-card/60 hover:text-foreground"
                    aria-label="Uitloggen"
                >
                    <LogOut class="size-4" />
                </Link>

                <button
                    type="button"
                    class="rounded-lg p-2 text-muted-foreground transition hover:bg-card/60 hover:text-foreground lg:hidden"
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
            <div v-for="groep in groepen" :key="groep.title" class="mt-3">
                <Link
                    v-if="groep.href"
                    :href="groep.href"
                    class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium"
                    :class="isActief(groep) ? 'bg-card' : 'text-muted-foreground'"
                    @click="sluit"
                >
                    <component :is="groep.icon" class="size-4 shrink-0 opacity-70" />
                    {{ groep.title }}
                </Link>

                <template v-else>
                    <p class="px-3 text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ groep.title }}</p>
                    <Link
                        v-for="item in groep.items"
                        :key="item.href"
                        :href="item.href"
                        class="mt-1 flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm"
                        :class="raakt(item.href) ? 'bg-primary/10 text-primary' : 'text-muted-foreground'"
                        @click="sluit"
                    >
                        <component :is="item.icon" class="size-4 shrink-0 opacity-70" />
                        {{ item.title }}
                    </Link>
                </template>
            </div>
        </nav>
    </header>
</template>
