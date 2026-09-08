<script setup lang="ts">
import { Download, X } from 'lucide-vue-next';
import { onMounted, onUnmounted, ref } from 'vue';

/**
 * Het aanbod om de app op je beginscherm te zetten.
 *
 * Eén keer vragen en daarna niet meer: wie wegklikt heeft nee gezegd, en een
 * balk die elke week terugkomt is precies waarom mensen apps wantrouwen.
 * De keuze staat in localStorage, dus per apparaat — wat klopt, want het gaat
 * ook over dít apparaat.
 */
const BEWAARSLEUTEL = 'playerpath.install-afgewezen';

const gebeurtenis = ref<any>(null);
const zichtbaar = ref(false);

const onBeforeInstall = (event: Event) => {
    event.preventDefault();

    if (localStorage.getItem(BEWAARSLEUTEL) === 'ja') {
        return;
    }

    gebeurtenis.value = event;
    zichtbaar.value = true;
};

const installeer = async () => {
    if (!gebeurtenis.value) {
        return;
    }

    zichtbaar.value = false;
    gebeurtenis.value.prompt();
    await gebeurtenis.value.userChoice;
    gebeurtenis.value = null;
};

const sluit = () => {
    zichtbaar.value = false;
    try {
        localStorage.setItem(BEWAARSLEUTEL, 'ja');
    } catch {
        // Privémodus of geblokkeerde opslag: dan vragen we hooguit nog een keer.
    }
};

onMounted(() => window.addEventListener('beforeinstallprompt', onBeforeInstall));
onUnmounted(() => window.removeEventListener('beforeinstallprompt', onBeforeInstall));
</script>

<template>
    <div
        v-if="zichtbaar"
        class="fixed inset-x-3 bottom-[calc(var(--pp-tabbar)+0.75rem)] z-50 flex items-center gap-3 rounded-xl border border-border bg-card p-3 shadow-lg sm:left-auto sm:w-96"
    >
        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
            <Download class="size-4" />
        </span>

        <div class="min-w-0 flex-1">
            <p class="text-sm font-medium">Op je beginscherm zetten?</p>
            <p class="text-xs text-muted-foreground">Dan opent hij als een app, zonder adresbalk.</p>
        </div>

        <button type="button" class="h-9 shrink-0 rounded-lg bg-primary px-3 text-sm font-medium text-primary-foreground" @click="installeer">
            Installeren
        </button>
        <button type="button" class="shrink-0 text-muted-foreground hover:text-foreground" aria-label="Niet installeren" @click="sluit">
            <X class="size-4" />
        </button>
    </div>
</template>
