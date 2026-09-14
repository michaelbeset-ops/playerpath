<script setup lang="ts">
import InstallSteps from '@/components/InstallSteps.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    CalendarDays,
    CircleHelp,
    ClipboardList,
    CreditCard,
    Download,
    Image,
    Inbox,
    Link2,
    Phone,
    PlayCircle,
    Smile,
    Sparkles,
    UserPlus,
    Users,
} from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * Hulp: hoe het werkt, per rol, in de volgorde waarin je het tegenkomt.
 *
 * Voor de school: speler, ouder, inschrijven, rapport en kaart, delen. Voor
 * de ouder: je kind, inschrijven, de kaart, delen, betalen. Voor een speler
 * alleen de kaart. Bovenaan overal hetzelfde: zet de app op je beginscherm.
 */
const props = defineProps<{
    audience: 'eigenaar' | 'trainer' | 'ouder' | 'speler';
    schoolName: string | null;
    /** Ons nummer: alleen voor de eigenaar. */
    supportPhone: string | null;
    schoolPhone: string | null;
    schoolEmail: string | null;
    firstChildId: number | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Help', href: '/help' }];

const school = computed(() => props.audience === 'eigenaar' || props.audience === 'trainer');

const startRondleiding = () => router.post('/onboarding/rondleiding/opnieuw');
</script>

<template>
    <Head title="Help" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-3xl p-4">
            <div class="flex items-start gap-3">
                <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                    <CircleHelp class="size-5" />
                </span>
                <div class="min-w-0">
                    <h1 class="text-2xl font-semibold tracking-tight">Zo werkt het</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{
                            school
                                ? 'Van speler tot spelerskaart, in de volgorde waarin je het tegenkomt.'
                                : audience === 'ouder'
                                  ? 'Wat je hier ziet, wat je kunt doen, en hoe de kaart van je kind groeit.'
                                  : 'Jouw kaart, en hoe je hem laat groeien.'
                        }}
                    </p>
                </div>
            </div>

            <!-- Op je beginscherm: voor iedereen, bovenaan -->
            <section class="mt-6 rounded-xl border border-primary/40 bg-primary/5 p-5">
                <p class="flex items-center gap-2 font-medium">
                    <Download class="size-4 text-primary" />
                    Zet {{ schoolName ?? 'PlayerPath' }} op je beginscherm
                </p>
                <p class="mt-1 text-sm text-muted-foreground">
                    Dan opent de app met één tik, zonder adresbalk, net als elke andere app. Doe dit op je telefoon; het kost tien seconden.
                </p>
                <InstallSteps class="mt-3" :naam="schoolName ?? undefined" />
            </section>

            <!-- ============ De school ============ -->
            <template v-if="school">
                <section class="mt-6 rounded-xl border border-border bg-card p-5 shadow-sm">
                    <p class="flex items-center gap-2 font-medium"><UserPlus class="size-4 text-primary" /> 1. Speler toevoegen</p>
                    <p class="mt-2 text-sm text-muted-foreground">Een speler is een profiel van het kind: naam, geboortedatum, positie en groep. Een kind heeft geen wachtwoord nodig: de ouder geeft het een eigen link.</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                        <li><span class="font-medium">Zelf toevoegen:</span> Klanten → Speler toevoegen. Voor kinderen die al trainen of bij een overstap.</li>
                        <li>
                            <span class="font-medium">Via de inschrijfpagina:</span> een ouder meldt zich aan, jij keurt goed, en de speler ontstaat vanzelf met het
                            ouderaccount eraan.
                        </li>
                    </ul>
                    <Link v-if="audience === 'eigenaar'" href="/players/create" class="mt-3 inline-flex min-h-11 items-center text-sm font-medium text-primary underline underline-offset-4">Speler toevoegen</Link>
                </section>

                <section class="mt-4 rounded-xl border border-border bg-card p-5 shadow-sm">
                    <p class="flex items-center gap-2 font-medium"><Users class="size-4 text-primary" /> 2. De ouder erbij</p>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Heb je de speler zelf aangemaakt, nodig dan de ouder uit vanaf de pagina van het kind: naam en e-mailadres, versturen. De ouder krijgt een
                        mail uit naam van de school, kiest een wachtwoord en zit meteen aan dat kind vast. Kwam de speler via de inschrijfpagina, dan is dit
                        al gebeurd.
                    </p>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Een ouder ziet daarna alleen zijn eigen kinderen: de trainingen, wat er openstaat, het aanbod, en per kind de spelerskaart.
                    </p>
                </section>

                <section class="mt-4 rounded-xl border border-border bg-card p-5 shadow-sm">
                    <p class="flex items-center gap-2 font-medium"><Inbox class="size-4 text-primary" /> 3. Inschrijven</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                        <li>
                            <span class="font-medium">Aanbod</span> (blok, kamp, abonnement) staat op je inschrijfpagina. De ouder kiest, vult het kind in, kiest online
                            of contant en bevestigt. Jij keurt goed; dan volgt de betaallink of "reken af bij de school".
                        </li>
                        <li>
                            <span class="font-medium">Losse training:</span> zet bij een training "los inschrijven" aan. Ouders zien hem dan onder Trainingen →
                            Inschrijven. Vol is wachtlijst.
                        </li>
                    </ul>
                    <div v-if="audience === 'eigenaar'" class="mt-3 flex flex-wrap gap-4">
                        <Link href="/onboarding/aanmeldpagina" class="inline-flex min-h-11 items-center text-sm font-medium text-primary underline underline-offset-4">Bekijk je inschrijfpagina</Link>
                        <Link href="/instellingen/inschrijven" class="inline-flex min-h-11 items-center text-sm font-medium text-primary underline underline-offset-4">Instellingen inschrijven en betalen</Link>
                    </div>
                </section>

                <section class="mt-4 rounded-xl border border-border bg-card p-5 shadow-sm">
                    <p class="flex items-center gap-2 font-medium"><ClipboardList class="size-4 text-primary" /> 4. Rapport en spelerskaart</p>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Na de training vult de trainer per speler zes cijfers in, in een halve minuut. De kaart rekent met de laatste drie rapporten, zodat
                        één mindere training de kaart niet verpest. Aanwezig zijn en rapporten leveren punten op; die brengen de kaart van brons naar zilver,
                        goud en special. Ouders krijgen bij elk rapport een bericht.
                    </p>
                    <Link href="/trainings/mijn" class="mt-3 inline-flex min-h-11 items-center text-sm font-medium text-primary underline underline-offset-4">Naar mijn trainingen</Link>
                </section>
            </template>

            <!-- ============ De ouder ============ -->
            <template v-else-if="audience === 'ouder'">
                <section class="mt-6 rounded-xl border border-border bg-card p-5 shadow-sm">
                    <p class="flex items-center gap-2 font-medium"><Users class="size-4 text-primary" /> Je kind en je account</p>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Je account hangt aan je kind(eren). Op het dashboard zie je de eerstvolgende trainingen, wat er van je gevraagd wordt en per kind een
                        kaartje dat opent naar de volle spelerskaart. Zet een foto op de kaart: dat maakt hem voor je kind de helft meer waard.
                    </p>
                </section>

                <section class="mt-4 rounded-xl border border-border bg-card p-5 shadow-sm">
                    <p class="flex items-center gap-2 font-medium"><CalendarDays class="size-4 text-primary" /> Inschrijven en afmelden</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                        <li>Onder <span class="font-medium">Trainingen</span> zie je Komend, Inschrijven en Geweest. Een training waar plek is schrijf je met één tik in.</li>
                        <li>Kan je kind een keer niet, meld het dan af bij de training. De trainer ziet dat meteen.</li>
                        <li>Nieuw aanbod (een blok, een kamp) staat op de inschrijfpagina van de school en onder Inschrijven op je dashboard.</li>
                    </ul>
                    <Link href="/trainings" class="mt-3 inline-flex min-h-11 items-center text-sm font-medium text-primary underline underline-offset-4">Naar de trainingen</Link>
                </section>

                <section class="mt-4 rounded-xl border border-border bg-card p-5 shadow-sm">
                    <p class="flex items-center gap-2 font-medium"><Sparkles class="size-4 text-primary" /> Hoe de kaart groeit</p>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Na elke training vult de trainer een rapport in met zes cijfers. De kaart toont het gemiddelde van de laatste drie rapporten, dus groei
                        zie je binnen een paar weken. Aanwezig zijn en rapporten leveren punten op; die brengen de kaart van brons naar zilver, goud en
                        special. Bij elk nieuw rapport krijg je een bericht.
                    </p>
                </section>

                <section class="mt-4 rounded-xl border border-border bg-card p-5 shadow-sm">
                    <p class="flex items-center gap-2 font-medium"><CreditCard class="size-4 text-primary" /> Betalen</p>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Onder Betalingen staat wat er openstaat en wat je betaald hebt. Online betalen gaat via een link in je mail; contant reken je af bij de
                        school. Een abonnement zeg je daar ook op.
                    </p>
                </section>
            </template>

            <!-- ============ De speler ============ -->
            <template v-else>
                <section class="mt-6 rounded-xl border border-border bg-card p-5 shadow-sm">
                    <p class="flex items-center gap-2 font-medium"><Sparkles class="size-4 text-primary" /> Jouw kaart</p>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Na elke training vult je trainer een rapport in. Daar komen je cijfers vandaan. Elke keer dat je er bent krijg je punten, en met genoeg
                        punten verandert je kaart van brons naar zilver, goud en special. Aan het eind van het seizoen bewaren we je kaart bij Mijn kaarten.
                    </p>
                </section>
            </template>

            <!-- ============ Delen: school en ouder ============ -->
            <section v-if="audience !== 'speler'" class="mt-4 rounded-xl border border-border bg-card p-5 shadow-sm">
                <p class="flex items-center gap-2 font-medium"><Image class="size-4 text-primary" /> {{ school ? '5. ' : '' }}De kaart delen</p>
                <p class="mt-2 text-sm text-muted-foreground">Drie manieren, allemaal op de kaartpagina van het kind:</p>
                <ul class="mt-2 space-y-2 text-sm">
                    <li class="flex items-start gap-2">
                        <Image class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                        <span><span class="font-medium">Als afbeelding.</span> Een plaatje in story-formaat met het logo van de school, voor WhatsApp, Instagram of Snapchat.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <Link2 class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                        <span><span class="font-medium">Deel-link.</span> Voor internet, bewust kaal: alleen voornaam met initiaal en wat er op de kaart staat. Aan- en uitzetten wanneer je wilt.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <Smile class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                        <span>
                            <span class="font-medium">Link voor het kind zelf.</span> Een privélink met QR-code die je kind zonder wachtwoord op zijn eigen account
                            brengt: de kaart, de voortgang en Mijn kaarten. Eén keer openen op de tablet, dan blijft het ingelogd; zet de app daarna
                            op het beginscherm.
                        </span>
                    </li>
                </ul>
                <Link
                    v-if="firstChildId"
                    :href="'/players/' + firstChildId + '/card'"
                    class="mt-3 inline-flex min-h-11 items-center text-sm font-medium text-primary underline underline-offset-4"
                >
                    Naar de kaart
                </Link>
            </section>

            <!-- Rondleiding opnieuw, alleen de eigenaar -->
            <section v-if="audience === 'eigenaar'" class="mt-4 rounded-xl border border-border bg-card p-5 shadow-sm">
                <p class="flex items-center gap-2 font-medium"><PlayCircle class="size-4 text-primary" /> De rondleiding nog eens</p>
                <p class="mt-2 text-sm text-muted-foreground">Zestien korte stappen door de app, met wat je ziet en wat je ermee doet. Je kunt intussen gewoon overal klikken.</p>
                <button type="button" class="mt-3 inline-flex min-h-11 items-center rounded-xl border border-border px-4 text-sm font-medium transition hover:border-primary" @click="startRondleiding">
                    Rondleiding starten
                </button>
            </section>

            <!-- Hulp: de eigenaar belt ons, iedereen anders zijn eigen voetbalschool -->
            <section class="mt-4 rounded-xl border border-border bg-card p-5 shadow-sm">
                <p class="flex items-center gap-2 font-medium"><Phone class="size-4 text-primary" /> Kom je er niet uit?</p>

                <template v-if="supportPhone">
                    <p class="mt-2 text-sm text-muted-foreground">Bel ons, dan lopen we het samen door.</p>
                    <a :href="'tel:' + supportPhone.replace(/\s/g, '')" class="mt-2 inline-flex min-h-11 items-center text-sm font-semibold text-primary underline underline-offset-4">{{ supportPhone }}</a>
                </template>

                <template v-else>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Neem contact op met {{ schoolName ?? 'je voetbalschool' }}. Die kent je kind en de afspraken, en helpt je verder.
                    </p>
                    <div v-if="schoolPhone || schoolEmail" class="mt-2 flex flex-wrap gap-x-4">
                        <a v-if="schoolPhone" :href="'tel:' + schoolPhone.replace(/\s/g, '')" class="inline-flex min-h-11 items-center text-sm font-semibold text-primary underline underline-offset-4">{{ schoolPhone }}</a>
                        <a v-if="schoolEmail" :href="'mailto:' + schoolEmail" class="inline-flex min-h-11 items-center text-sm font-semibold text-primary underline underline-offset-4">{{ schoolEmail }}</a>
                    </div>
                </template>
            </section>
        </div>
    </AppLayout>
</template>
