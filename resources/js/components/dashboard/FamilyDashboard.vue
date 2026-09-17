<script setup lang="ts">
import AttentionPanel, { type AandachtItem } from '@/components/dashboard/AttentionPanel.vue';
import GradeChip from '@/components/GradeChip.vue';
import { useGrading } from '@/lib/grade';
import Avatar from '@/components/Avatar.vue';
import type { FamilyAanbod, FamilyBericht, FamilyKind, FamilyTraining } from '@/types/family';
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight, CalendarDays, Camera, ChevronRight, IdCard, MapPin, ShoppingBag, TrendingUp, UserCog } from 'lucide-vue-next';

/**
 * Het dashboard van een ouder, in deze volgorde: binnenkort, mijn kinderen,
 * inschrijven, berichten. Praktisch bovenaan, de kaart één tik verderop.
 *
 * Wat er nú van je gevraagd wordt (een openstaande rekening) staat bovenaan,
 * in hetzelfde blok als bij de school; zonder rekening is het weg. Een
 * ongelezen bericht staat bij het belletje. Alles noemt bij welk kind het
 * hoort; twee kinderen is het gewone geval.
 */
withDefaults(defineProps<{
    attention?: AandachtItem[];
    children: FamilyKind[];
    upcoming: FamilyTraining[];
    offerings: FamilyAanbod[];
    messages: FamilyBericht[];
}>(), { attention: () => [] });

const levelRand: Record<string, string> = {
    brons: 'border-level-brons/50',
    zilver: 'border-level-zilver/60',
    goud: 'border-gold/50',
    elite: 'border-primary/50',
};

// Kleuren of cijfers: in kleuren geen getal en geen "+3" bij het kind.
const { kleuren, inzet } = useGrading();
</script>

<template>
    <!-- Alles leeg: dat is precies de eerste keer dat een ouder inlogt. Losse
         lege blokken laten we weg (dat is de regel), maar een scherm zonder
         iets is geen scherm - dan staat er één regel die zegt wat er komt. -->
    <div
        v-if="!attention.length && !upcoming.length && !children.length && !offerings.length && !messages.length"
        class="rounded-2xl border border-border bg-card p-6 text-center shadow-sm"
    >
        <CalendarDays class="mx-auto size-7 text-muted-foreground" />
        <p class="mt-3 font-medium">Hier komt het te staan</p>
        <p class="mx-auto mt-1 max-w-sm text-sm text-muted-foreground">
            Zodra je school je kind heeft ingedeeld, zie je hier wanneer de trainingen zijn, hoe het met hem gaat en wat er nog openstaat. Je hoeft
            zelf niets in te stellen.
        </p>
    </div>

    <div v-else class="space-y-6" data-tour="family">
        <!-- 0. Wat er nu van je gevraagd wordt. Leeg is weg: geen "alles loopt". -->
        <AttentionPanel v-if="attention.length" :items="attention" />

        <!-- 1. Binnenkort: wanneer moet je waar zijn. Het scherm opent hiermee. -->
        <section>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-semibold">Binnenkort</h2>
                <Link href="/trainings" class="inline-flex min-h-11 items-center text-sm font-medium text-primary underline underline-offset-4"
                    >Bekijk meer</Link
                >
            </div>

            <div v-if="upcoming.length" class="mt-3 space-y-2">
                <Link
                    v-for="training in upcoming"
                    :key="training.id"
                    :href="'/trainings/' + training.id"
                    class="flex min-w-0 items-start gap-3 rounded-xl border bg-card p-4 shadow-sm transition hover:border-primary"
                    :class="training.is_today ? 'border-primary/40' : 'border-border'"
                >
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        <CalendarDays class="size-5" />
                    </span>

                    <span class="min-w-0 flex-1">
                        <span class="flex flex-wrap items-center gap-2">
                            <span class="font-medium" :class="training.cancelled ? 'line-through' : ''">{{ training.label }}</span>
                            <span
                                v-if="training.for"
                                class="rounded-full bg-secondary px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground"
                            >
                                {{ training.for }}
                            </span>
                            <span
                                v-if="training.is_today"
                                class="rounded-full bg-primary px-2 py-0.5 text-[10px] font-semibold text-primary-foreground"
                            >
                                vandaag
                            </span>
                            <!-- Afgemeld zie je hier al; afmelden zelf doe je op de training. -->
                            <span
                                v-for="kind in training.children.filter((k) => k.registration === 'declined')"
                                :key="kind.id"
                                class="rounded-full bg-warning/15 px-2 py-0.5 text-[10px] font-semibold text-warning"
                            >
                                {{ kind.first_name }} afgemeld
                            </span>
                        </span>

                        <span class="tabular mt-0.5 block text-sm first-letter:uppercase">{{ training.date }} · {{ training.time }}</span>

                        <span v-if="training.location" class="mt-1 flex items-start gap-1.5 text-xs text-muted-foreground">
                            <MapPin class="mt-0.5 size-3.5 shrink-0" />
                            <span>{{ training.location }}</span>
                        </span>
                        <span v-if="training.trainers.length" class="mt-0.5 flex items-start gap-1.5 text-xs text-muted-foreground">
                            <UserCog class="mt-0.5 size-3.5 shrink-0" />
                            <span>{{ training.trainers.join(', ') }}</span>
                        </span>
                    </span>

                    <ChevronRight class="mt-1 size-4 shrink-0 text-muted-foreground" />
                </Link>
            </div>

            <p v-else class="mt-3 rounded-xl border border-dashed border-border bg-card/50 p-5 text-center text-sm text-muted-foreground">
                Er staat nog geen training gepland.
            </p>
        </section>

        <!-- 2. De kinderen, compact. Eén tik op het kaartje opent de kaart. -->
        <section v-if="children.length">
            <h2 class="font-semibold">{{ children.length === 1 ? 'Mijn kind' : 'Mijn kinderen' }}</h2>

            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <div
                    v-for="kind in children"
                    :key="kind.id"
                    class="rounded-2xl border-2 bg-card p-4 shadow-sm"
                    :class="levelRand[kind.level] ?? 'border-border'"
                >
                    <Link :href="'/players/' + kind.id + '/card'" class="flex min-w-0 items-center gap-3">
                        <Avatar :name="kind.name" :photo="kind.photo" size="size-12" />

                        <span class="min-w-0 flex-1">
                            <span class="block font-semibold">{{ kind.first_name }}</span>
                            <span class="block text-xs text-muted-foreground">{{ kind.position }} · {{ kind.level_label }}</span>
                        </span>

                        <span class="shrink-0 text-right">
                            <!-- Inzetkaart: de punten, geen cijfer -->
                            <span v-if="inzet" class="block leading-none">
                                <span class="tabular block text-2xl font-bold">{{ kind.xp }}</span>
                                <span class="block text-[10px] font-medium uppercase tracking-wide text-muted-foreground">punten</span>
                            </span>
                            <GradeChip v-else :rating="kind.overall">
                                <span class="tabular block text-2xl font-bold leading-none">{{ kind.overall ?? '-' }}</span>
                            </GradeChip>
                            <span
                                v-if="!kleuren && kind.growth !== null && kind.growth !== 0"
                                class="tabular mt-0.5 flex items-center justify-end gap-0.5 text-xs font-medium"
                                :class="kind.growth > 0 ? 'text-success' : 'text-muted-foreground'"
                            >
                                <TrendingUp v-if="kind.growth > 0" class="size-3" />
                                {{ kind.growth > 0 ? '+' : '' }}{{ kind.growth }}
                            </span>
                        </span>
                    </Link>

                    <div class="mt-3">
                        <div class="h-1.5 overflow-hidden rounded-full bg-secondary">
                            <div class="h-full rounded-full bg-primary transition-all" :style="{ width: kind.xp_progress + '%' }"></div>
                        </div>
                        <p class="tabular mt-1 text-[11px] text-muted-foreground">
                            {{ kind.xp }} punten<template v-if="kind.next_level"> · op weg naar {{ kind.next_level }}</template>
                        </p>
                    </div>

                    <!-- Blijft staan tot de foto er is: een kaart zonder gezicht is de helft minder waard. -->
                    <Link
                        v-if="!kind.photo"
                        :href="'/players/' + kind.id + '/card#foto'"
                        class="mt-3 inline-flex min-h-11 items-center gap-1.5 text-sm font-medium text-primary"
                    >
                        <Camera class="size-4" />
                        + Foto toevoegen
                    </Link>

                    <div class="mt-3 flex flex-wrap gap-2">
                        <Link
                            :href="'/players/' + kind.id + '/card'"
                            class="inline-flex min-h-11 flex-1 items-center justify-center gap-2 rounded-xl border border-border text-sm font-medium transition hover:border-primary"
                        >
                            <IdCard class="size-4" />
                            Spelerskaart
                        </Link>
                        <Link
                            :href="'/players/' + kind.id + '/progress'"
                            class="inline-flex min-h-11 flex-1 items-center justify-center gap-2 rounded-xl border border-border text-sm font-medium transition hover:border-primary"
                        >
                            <ArrowUpRight class="size-4" />
                            Voortgang
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <!-- 3. Inschrijven: direct naar dat aanbod. Leeg is weg. -->
        <section v-if="offerings.length">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-semibold">Inschrijven</h2>
                <Link href="/shop" class="inline-flex min-h-11 items-center text-sm font-medium text-primary underline underline-offset-4"
                    >Alles bekijken</Link
                >
            </div>

            <div class="mt-3 space-y-2">
                <article v-for="aanbod in offerings" :key="aanbod.id" class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                    <img v-if="aanbod.image" :src="aanbod.image" alt="" class="h-32 w-full object-cover" />

                    <div class="p-4">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="min-w-0 flex-1">
                                <p class="text-xs uppercase tracking-wide text-muted-foreground">{{ aanbod.type }}</p>
                                <p class="font-medium">{{ aanbod.name }}</p>
                            </div>
                            <p class="tabular shrink-0 text-right">
                                <span class="font-bold">{{ aanbod.is_free ? 'gratis' : aanbod.amount }}</span>
                                <span v-if="!aanbod.is_free" class="block text-xs text-muted-foreground">{{ aanbod.billing }}</span>
                            </p>
                        </div>

                        <p v-if="aanbod.description" class="mt-1 line-clamp-2 text-sm text-muted-foreground">{{ aanbod.description }}</p>

                        <p v-if="aanbod.period" class="tabular mt-2 flex items-center gap-1.5 text-xs text-muted-foreground">
                            <CalendarDays class="size-3.5 shrink-0" />
                            {{ aanbod.period }}
                        </p>
                        <p v-if="aanbod.location" class="mt-0.5 flex items-center gap-1.5 text-xs text-muted-foreground">
                            <MapPin class="size-3.5 shrink-0" />
                            {{ aanbod.location }}
                        </p>

                        <div class="mt-3 flex flex-wrap items-center gap-3">
                            <a
                                :href="aanbod.enroll_url"
                                class="inline-flex h-11 items-center gap-2 rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                            >
                                <ShoppingBag class="size-4" />
                                Inschrijven
                            </a>

                            <span
                                v-if="aanbod.spots_left !== null"
                                class="text-xs"
                                :class="aanbod.spots_left <= 3 ? 'text-warning' : 'text-muted-foreground'"
                            >
                                <template v-if="aanbod.spots_left === 1">Nog 1 plek</template>
                                <template v-else>Nog {{ aanbod.spots_left }} plekken</template>
                            </span>
                        </div>
                    </div>
                </article>
            </div>
        </section>

        <!-- 4. Nieuws van de school, ongelezen gemarkeerd. Leeg is weg. -->
        <section v-if="messages.length">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-semibold">Berichten van de school</h2>
                <Link href="/notifications" class="inline-flex min-h-11 items-center text-sm font-medium text-primary underline underline-offset-4"
                    >Alles</Link
                >
            </div>

            <div class="mt-3 overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <Link
                    v-for="(bericht, index) in messages"
                    :key="bericht.id"
                    :href="bericht.url"
                    class="flex items-start gap-3 p-4 transition hover:bg-secondary/60"
                    :class="index > 0 ? 'border-t border-border' : ''"
                >
                    <span class="mt-1.5 size-2 shrink-0 rounded-full" :class="bericht.unread ? 'bg-primary' : 'bg-transparent'"></span>

                    <span class="min-w-0 flex-1">
                        <span class="block text-sm" :class="bericht.unread ? 'font-medium' : ''">{{ bericht.title }}</span>
                        <span class="block text-xs text-muted-foreground">{{ bericht.when }}</span>
                    </span>

                    <ChevronRight class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                </Link>
            </div>
        </section>
    </div>
</template>
