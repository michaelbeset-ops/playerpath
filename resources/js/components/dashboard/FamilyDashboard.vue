<script setup lang="ts">
import Avatar from '@/components/Avatar.vue';
import { toneChip } from '@/lib/tone';
import type { FamilyAanbod, FamilyBericht, FamilyKind, FamilyTaak, FamilyTraining } from '@/types/family';
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight, Bell, CalendarDays, ChevronRight, CreditCard, IdCard, MapPin, ShoppingBag, TrendingUp, UserCog } from 'lucide-vue-next';
import { type Component } from 'vue';

/**
 * Het dashboard van een ouder.
 *
 * Praktisch bovenaan, de kaart klein. Een ouder komt hier om te weten wanneer
 * de training is, of hij nog moet betalen en of er nieuws is. De spelerskaart
 * is het mooiste wat dit product maakt, maar hij beantwoordt geen van die
 * vragen — en als hij het hele scherm vult, moet je er elke keer omheen
 * scrollen. Eén tik op een kindkaartje opent hem alsnog helemaal.
 *
 * Alles noemt bij welk kind het hoort: twee kinderen is het gewone geval, en
 * een lijst met trainingen zonder naam is dan onbruikbaar.
 */
defineProps<{
    children: FamilyKind[];
    todo: FamilyTaak[];
    upcoming: FamilyTraining[];
    offerings: FamilyAanbod[];
    messages: FamilyBericht[];
}>();

const iconen: Record<string, Component> = {
    payment: CreditCard,
    message: Bell,
};

/**
 * De rand van een kindkaartje volgt het level.
 *
 * Subtiel: het is een hint naar de kaart eronder, geen tweede kaart. Het echte
 * frame met metaal en glans zit op de spelerskaart zelf, en die blijft daarmee
 * het bijzondere ding.
 */
const levelRand: Record<string, string> = {
    brons: 'border-amber-700/40',
    zilver: 'border-slate-400/50',
    goud: 'border-gold/50',
    elite: 'border-primary/50',
};
</script>

<template>
    <div class="space-y-6">
        <!-- 1. Binnenkort: wanneer moet je waar zijn. Het scherm opent hiermee,
             want dat is de vraag waarvoor een ouder zijn telefoon pakt. Drie
             trainingen; de rest staat achter "Bekijk meer", en dat is alleen
             wat de eigen kinderen aangaat. -->
        <section>
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="font-semibold">Binnenkort</h2>
                <Link href="/trainings" class="text-sm font-medium text-primary underline underline-offset-4">Bekijk meer</Link>
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

        <!-- 2. Wat er nú van je gevraagd wordt. Leeg is weg. -->
        <section v-if="todo.length" class="space-y-2">
            <div
                v-for="item in todo"
                :key="item.key"
                class="flex flex-col gap-3 rounded-2xl border p-4 sm:flex-row sm:items-center"
                :class="item.tone === 'bad' ? 'border-destructive/30 bg-destructive/5' : 'border-warning/30 bg-warning/5'"
            >
                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl" :class="toneChip[item.tone]">
                    <component :is="iconen[item.icon] ?? Bell" class="size-5" />
                </span>

                <div class="min-w-0 flex-1">
                    <p class="font-medium">{{ item.title }}</p>
                    <p class="text-sm text-muted-foreground">{{ item.body }}</p>
                </div>

                <Link
                    :href="item.href"
                    class="inline-flex h-11 shrink-0 items-center justify-center rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                >
                    {{ item.action }}
                </Link>
            </div>
        </section>

        <!-- 3. Waar je je kind voor kunt inschrijven. Leeg is weg. -->
        <section v-if="offerings.length">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="font-semibold">Inschrijven</h2>
                <Link href="/shop" class="text-sm font-medium text-primary underline underline-offset-4">Alles bekijken</Link>
            </div>

            <div class="mt-3 space-y-2">
                <article v-for="aanbod in offerings" :key="aanbod.id" class="rounded-xl border border-border bg-card p-4 shadow-sm">
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
                        <Link
                            href="/shop"
                            class="inline-flex h-11 items-center gap-2 rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                        >
                            <ShoppingBag class="size-4" />
                            Inschrijven
                        </Link>

                        <span
                            v-if="aanbod.spots_left !== null"
                            class="text-xs"
                            :class="aanbod.spots_left <= 3 ? 'text-warning' : 'text-muted-foreground'"
                        >
                            <template v-if="aanbod.spots_left === 1">Nog 1 plek</template>
                            <template v-else>Nog {{ aanbod.spots_left }} plekken</template>
                        </span>
                    </div>
                </article>
            </div>
        </section>

        <!-- 4. De kinderen, compact. Hier zit de kaart: één tik en hij is groot. -->
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
                            <span class="tabular block text-2xl font-bold leading-none">{{ kind.overall ?? '—' }}</span>
                            <span
                                v-if="kind.growth !== null && kind.growth !== 0"
                                class="tabular mt-0.5 flex items-center justify-end gap-0.5 text-xs font-medium"
                                :class="kind.growth > 0 ? 'text-success' : 'text-muted-foreground'"
                            >
                                <TrendingUp v-if="kind.growth > 0" class="size-3" />
                                {{ kind.growth > 0 ? '+' : '' }}{{ kind.growth }}
                            </span>
                        </span>
                    </Link>

                    <!-- De XP-balk: het spelelement, klein gehouden. -->
                    <div class="mt-3">
                        <div class="h-1.5 overflow-hidden rounded-full bg-secondary">
                            <div class="h-full rounded-full bg-primary transition-all" :style="{ width: kind.xp_progress + '%' }"></div>
                        </div>
                        <p class="tabular mt-1 text-[11px] text-muted-foreground">
                            {{ kind.xp }} XP<template v-if="kind.next_level"> · op weg naar {{ kind.next_level }}</template>
                        </p>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-2">
                        <Link
                            :href="'/players/' + kind.id + '/card'"
                            class="inline-flex h-10 flex-1 items-center justify-center gap-2 rounded-xl border border-border text-sm font-medium transition hover:border-primary"
                        >
                            <IdCard class="size-4" />
                            Spelerskaart
                        </Link>
                        <Link
                            :href="'/players/' + kind.id + '/progress'"
                            class="inline-flex h-10 flex-1 items-center justify-center gap-2 rounded-xl border border-border text-sm font-medium transition hover:border-primary"
                        >
                            <ArrowUpRight class="size-4" />
                            Voortgang
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <!-- 5. Nieuws van de school. Leeg is weg. -->
        <section v-if="messages.length">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="font-semibold">Berichten van de school</h2>
                <Link href="/notifications" class="text-sm font-medium text-primary underline underline-offset-4">Alles</Link>
            </div>

            <div class="mt-3 overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <Link
                    v-for="(bericht, index) in messages"
                    :key="bericht.id"
                    :href="bericht.url"
                    class="flex items-start gap-3 p-4 transition hover:bg-secondary/60"
                    :class="index > 0 ? 'border-t border-border' : ''"
                >
                    <!-- Ongelezen: hetzelfde bolletje als bij het belletje in de
                         balk, anders zegt de een iets anders dan de ander. -->
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
