<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { router } from '@inertiajs/vue3';
import { CheckCircle2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';

/**
 * Afmelden (of weer aanmelden) voor een training, in dezelfde pop-up als de
 * rest van de app. Eerst een korte vraag met een optionele reden; na het
 * bevestigen zegt de pop-up dat de trainer het weet, met de tip om bij
 * bijzonderheden zelf even contact op te nemen.
 */
const props = defineProps<{
    trainingId: number;
    trainingLabel: string;
    date: string;
    child: { id: number; first_name: string };
    /** 'declined' om af te melden, 'attending' om weer aan te melden. */
    mode: 'declined' | 'attending';
}>();

const open = defineModel<boolean>('open', { default: false });

const reden = ref('');
const bezig = ref(false);
const klaar = ref(false);

watch(open, (nu) => {
    if (nu) {
        reden.value = '';
        klaar.value = false;
    }
});

const bevestig = () => {
    bezig.value = true;

    router.post(
        `/trainings/${props.trainingId}/registration/${props.child.id}`,
        { registration: props.mode, reason: props.mode === 'declined' ? reden.value || null : null },
        {
            preserveScroll: true,
            onSuccess: () => (klaar.value = true),
            onFinish: () => (bezig.value = false),
        },
    );
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-w-md rounded-2xl">
            <template v-if="!klaar">
                <DialogHeader>
                    <DialogTitle>
                        {{ mode === 'declined' ? child.first_name + ' afmelden?' : child.first_name + ' weer aanmelden?' }}
                    </DialogTitle>
                    <DialogDescription
                        >{{ trainingLabel }} · <span class="first-letter:uppercase">{{ date }}</span></DialogDescription
                    >
                </DialogHeader>

                <div v-if="mode === 'declined'" class="grid gap-2">
                    <label for="afmeld_reden" class="text-sm font-medium"
                        >Reden <span class="font-normal text-muted-foreground">(optioneel)</span></label
                    >
                    <textarea
                        id="afmeld_reden"
                        v-model="reden"
                        rows="2"
                        maxlength="300"
                        placeholder="Bijvoorbeeld: ziek, of een schoolreisje"
                        class="w-full rounded-lg border border-input bg-background px-3 py-2 text-base outline-none focus:border-primary"
                    ></textarea>
                </div>
                <p v-else class="text-sm text-muted-foreground">De trainer rekent dan weer op {{ child.first_name }}.</p>

                <DialogFooter class="gap-2">
                    <Button variant="secondary" class="h-11" @click="open = false">Annuleren</Button>
                    <Button class="h-11" :disabled="bezig" @click="bevestig">
                        {{ mode === 'declined' ? 'Afmelden' : 'Aanmelden' }}
                    </Button>
                </DialogFooter>
            </template>

            <template v-else>
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <CheckCircle2 class="size-5 text-primary" />
                        {{ mode === 'declined' ? child.first_name + ' is afgemeld' : child.first_name + ' is weer aangemeld' }}
                    </DialogTitle>
                    <DialogDescription>
                        <template v-if="mode === 'declined'">
                            De trainer heeft bericht gekregen en ziet het in zijn aanwezigheidslijst. Is er iets bijzonders? Neem dan zelf even
                            contact op met de trainer.
                        </template>
                        <template v-else>De trainer weet het.</template>
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter>
                    <Button class="h-11" @click="open = false">Sluiten</Button>
                </DialogFooter>
            </template>
        </DialogContent>
    </Dialog>
</template>
