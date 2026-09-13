<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { FlaskConical, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

/**
 * "Wat je hier ziet is een voorbeeld."
 *
 * Voorbeelddata is wat een lege omgeving draaglijk maakt, maar het is ook het
 * gevaarlijkste wat je in een product over kinderen kunt neerzetten: over een
 * half jaar staat er anders een verzonnen kind in een echt ledenbestand. Deze
 * balk is de tegenhanger die het verantwoord maakt - hij staat op elke pagina,
 * is niet weg te klikken, en heeft één knop.
 *
 * Verwijderen is definitief en vraagt daarom om een bevestiging waarin staat
 * wát er weggaat. Geen `confirm()` van de browser: die klik je weg zonder te
 * lezen.
 */
defineProps<{ compact?: boolean }>();

const open = ref(false);
const bezig = ref(false);

const verwijder = () => {
    bezig.value = true;

    router.delete('/onboarding/voorbeelddata', {
        onFinish: () => {
            bezig.value = false;
            open.value = false;
        },
    });
};
</script>

<template>
    <div>
        <div class="flex flex-col gap-2 rounded-xl border border-warning/40 bg-warning/5 p-3 sm:flex-row sm:items-center sm:gap-3">
            <div class="flex min-w-0 flex-1 items-start gap-3">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-warning/10 text-warning">
                    <FlaskConical class="size-4" />
                </span>

                <div class="min-w-0">
                    <p class="text-sm font-medium">Je kijkt naar voorbeelddata</p>
                    <p class="text-xs text-muted-foreground">
                        Vier spelers met rapporten, twee trainingen, een aanbod en een bericht - zodat je meteen ziet hoe het werkt. Ze staan overal
                        met het label <span class="font-medium">voorbeeld</span> erbij.
                    </p>
                </div>
            </div>

            <button
                type="button"
                class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-lg border border-border bg-background px-3 text-sm font-medium transition hover:border-destructive hover:text-destructive"
                @click="open = true"
            >
                <Trash2 class="size-4" />
                Voorbeelddata verwijderen
            </button>
        </div>

        <!-- De bevestiging zegt wat er weggaat. Verwijderen is definitief, en
             dat hoort te blijken vóór de klik en niet erna. -->
        <Teleport to="body">
            <div v-if="open" class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center">
                <div class="absolute inset-0 bg-foreground/40" @click="open = false"></div>

                <div class="relative w-full max-w-md rounded-2xl border border-border bg-card p-5 shadow-2xl">
                    <p class="text-lg font-semibold">Voorbeelddata verwijderen?</p>
                    <p class="mt-2 text-sm text-muted-foreground">
                        De vier voorbeeldspelers gaan weg, met hun rapporten en spelerskaarten. Ook de voorbeeldgroep, de twee trainingen, het
                        voorbeeldaanbod, de locatie en het bericht verdwijnen.
                    </p>
                    <p class="mt-2 text-sm text-muted-foreground">Wat je zelf hebt toegevoegd blijft staan. Dit kun je niet terugdraaien.</p>

                    <div class="mt-5 flex flex-col gap-2 sm:flex-row-reverse">
                        <button
                            type="button"
                            class="inline-flex min-h-11 flex-1 items-center justify-center gap-2 rounded-xl bg-destructive px-4 text-sm font-semibold text-destructive-foreground transition hover:opacity-90 disabled:opacity-60"
                            :disabled="bezig"
                            @click="verwijder"
                        >
                            <Trash2 class="size-4" />
                            Ja, verwijderen
                        </button>
                        <button
                            type="button"
                            class="inline-flex min-h-11 flex-1 items-center justify-center rounded-xl border border-border bg-background px-4 text-sm font-medium transition hover:border-primary"
                            @click="open = false"
                        >
                            Laat maar staan
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
