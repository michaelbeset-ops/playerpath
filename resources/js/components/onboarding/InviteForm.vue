<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { router, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Mail, RotateCcw, Send, Trash2, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';

/**
 * Uitnodigen: één tegelijk of een hele lijst.
 *
 * Bulk is hier geen luxe. Een school die overstapt heeft honderd ouders, en die
 * één voor één toevoegen is het soort werk waarna iemand besluit het toch maar
 * niet te doen. Vandaar één tekstvak met een regel per persoon — dat is wat
 * mensen tóch al plakken uit een spreadsheet of een groepsapp.
 *
 * Drie dingen die dit bruikbaar houden:
 *
 * 1. **Het formulier telt mee terwijl je typt** ("3 mensen"), zodat je vóór het
 *    versturen ziet of je lijst goed geplakt is.
 * 2. **Het accepteert wat mensen plakken**: "Naam <mail@x.nl>", "Naam,
 *    mail@x.nl", of een kaal adres. Een strikt formaat betekent dat iemand zijn
 *    lijst gaat zitten opschonen, en dat doet niemand.
 * 3. **Openstaande uitnodigingen staan eronder**, met opnieuw versturen en
 *    intrekken. Anders weet je na een bulkactie niet meer wie er nog moet.
 */
export interface Uitnodiging {
    id: number;
    name: string;
    email: string;
    status: string;
    expires_on: string;
    sent_count: number;
}

const props = withDefaults(
    defineProps<{
        role: 'trainer' | 'ouder';
        invitations: Uitnodiging[];
        validDays: number;
        /** Bij een ouder: aan welk kind hij gekoppeld wordt. */
        playerIds?: number[];
        title?: string;
    }>(),
    { playerIds: () => [], title: undefined },
);

const form = useForm<{ role: string; recipients: string; player_ids: number[]; relationship: string }>({
    role: props.role,
    recipients: '',
    player_ids: props.playerIds,
    relationship: '',
});

// Meetellen terwijl je typt: één regel met een @ is één persoon.
const aantal = computed(
    () =>
        form.recipients
            .split(/\r?\n/)
            .map((regel) => regel.trim())
            .filter((regel) => /[^\s<>,;]+@[^\s<>,;]+\.[^\s<>,;]+/.test(regel)).length,
);

const verstuur = () =>
    form
        .transform((data) => ({ ...data, player_ids: props.playerIds }))
        .post('/uitnodigingen', {
            preserveScroll: true,
            onSuccess: () => form.reset('recipients'),
        });

const bezig = ref<number | null>(null);

const opnieuw = (id: number) => {
    bezig.value = id;
    router.post('/uitnodigingen/' + id + '/opnieuw', {}, { preserveScroll: true, onFinish: () => (bezig.value = null) });
};

const trekIn = (id: number) => {
    bezig.value = id;
    router.delete('/uitnodigingen/' + id, { preserveScroll: true, onFinish: () => (bezig.value = null) });
};

const isOuder = computed(() => props.role === 'ouder');
</script>

<template>
    <section class="rounded-xl border border-border bg-card p-4 shadow-sm sm:p-5">
        <p class="font-medium">{{ title ?? (isOuder ? 'Ouders uitnodigen' : 'Trainers uitnodigen') }}</p>
        <p class="mt-1 text-sm text-muted-foreground">
            <template v-if="isOuder">
                Ze krijgen een e-mail met jouw naam en logo erboven, en zien na activatie meteen de kaart van hun kind.
            </template>
            <template v-else>
                Ze krijgen een e-mail met jouw naam en logo erboven, en kiezen zelf een wachtwoord. Jij hoeft er geen te bedenken.
            </template>
        </p>

        <form class="mt-4" @submit.prevent="verstuur">
            <label :for="'ontvangers-' + role" class="text-sm font-medium">Eén per regel</label>
            <textarea
                :id="'ontvangers-' + role"
                v-model="form.recipients"
                rows="4"
                class="mt-2 w-full rounded-lg border border-input bg-background p-3 text-sm outline-none focus:border-primary"
                placeholder="Sanne Bakker &lt;sanne@voorbeeld.nl&gt;&#10;Karim el Idrissi, karim@voorbeeld.nl&#10;marieke@voorbeeld.nl"
            ></textarea>
            <InputError class="mt-2" :message="form.errors.recipients" />

            <div v-if="isOuder" class="mt-3">
                <label :for="'relatie-' + role" class="text-sm font-medium">Relatie <span class="text-muted-foreground">(optioneel)</span></label>
                <input
                    :id="'relatie-' + role"
                    v-model="form.relationship"
                    type="text"
                    class="mt-1 h-11 w-full rounded-lg border border-input bg-background px-3 text-sm outline-none focus:border-primary"
                    placeholder="Bijvoorbeeld: moeder"
                />
            </div>

            <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-muted-foreground">
                    <template v-if="aantal">
                        <Users class="mr-1 inline size-3.5" />
                        <span class="tabular">{{ aantal }}</span> {{ aantal === 1 ? 'persoon' : 'mensen' }} &middot; de link is {{ validDays }} dagen
                        geldig
                    </template>
                    <template v-else> De link is {{ validDays }} dagen geldig. </template>
                </p>

                <button
                    type="submit"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground transition hover:opacity-90 disabled:opacity-60"
                    :disabled="form.processing || aantal === 0"
                >
                    <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                    <Send v-else class="size-4" />
                    {{ aantal > 1 ? aantal + ' uitnodigingen versturen' : 'Uitnodiging versturen' }}
                </button>
            </div>
        </form>

        <!-- Wie er nog niet binnen is. Leeg is weg: een kopje met niets eronder
             leest als een fout. -->
        <div v-if="invitations.length" class="mt-5 border-t border-border pt-4">
            <p class="text-sm font-medium">Nog niet geactiveerd</p>

            <ul class="mt-2 divide-y divide-border">
                <li v-for="rij in invitations" :key="rij.id" class="flex flex-col gap-2 py-2 sm:flex-row sm:items-center sm:gap-3">
                    <span class="flex min-w-0 flex-1 items-start gap-3">
                        <Mail class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-medium">{{ rij.name }}</span>
                            <span class="block truncate text-xs text-muted-foreground">
                                {{ rij.email }} &middot;
                                <span :class="rij.status === 'verlopen' ? 'text-warning' : ''">
                                    {{ rij.status === 'verlopen' ? 'verlopen' : 'geldig tot ' + rij.expires_on }}
                                </span>
                                <span v-if="rij.sent_count > 1"> &middot; {{ rij.sent_count }}× verstuurd</span>
                            </span>
                        </span>
                    </span>

                    <span class="flex shrink-0 items-center gap-1">
                        <button
                            type="button"
                            class="inline-flex min-h-11 items-center gap-1.5 rounded-lg border border-border bg-background px-3 text-xs font-medium transition hover:border-primary disabled:opacity-50"
                            :disabled="bezig === rij.id"
                            @click="opnieuw(rij.id)"
                        >
                            <RotateCcw class="size-3.5" />
                            Opnieuw
                        </button>
                        <button
                            type="button"
                            class="flex size-11 shrink-0 items-center justify-center rounded-lg text-muted-foreground transition hover:text-destructive disabled:opacity-50"
                            :aria-label="'Uitnodiging voor ' + rij.email + ' intrekken'"
                            :disabled="bezig === rij.id"
                            @click="trekIn(rij.id)"
                        >
                            <Trash2 class="size-4" />
                        </button>
                    </span>
                </li>
            </ul>
        </div>
    </section>
</template>
