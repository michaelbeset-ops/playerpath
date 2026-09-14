import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Beoordelen in kleuren of cijfers, aan de Vue-kant.
 *
 * De grenzen staan op de server (Support\Rating\Grade) en komen mee als
 * gedeelde prop `grading`. Hier staat alleen een kopie als terugval, voor een
 * pagina zonder gedeelde props (de publieke kaart draagt zijn eigen
 * `card.grading`).
 */
export interface Niveau {
    key: 'rood' | 'oranje' | 'groen' | 'blauw';
    label: string;
    /** Vanaf welke kaartwaarde (0-100). */
    from: number;
    /** Het rapportcijfer dat de trainer met deze kleur opslaat. */
    score: number;
}

export const STANDAARD_NIVEAUS: Niveau[] = [
    { key: 'rood', label: 'Werkpunt', from: 0, score: 4.5 },
    { key: 'oranje', label: 'Op weg', from: 55, score: 6.2 },
    { key: 'groen', label: 'Goed', from: 70, score: 7.7 },
    { key: 'blauw', label: 'Top', from: 85, score: 9.2 },
];

/** Een niveau op de voortgangsschaal van de inzetkaart (instelbaar per school). */
export interface VoortgangNiveau {
    key: string;
    label: string;
    color: string;
}

export const STANDAARD_VOORTGANG: VoortgangNiveau[] = [
    { key: 'n1', label: 'Werkpunt', color: 'rood' },
    { key: 'n2', label: 'Op weg', color: 'oranje' },
    { key: 'n3', label: 'Goed', color: 'groen' },
    { key: 'n4', label: 'Sterk', color: 'blauw' },
];

/** De kleur bij een kaartwaarde (0-100). */
export function niveauVoor(rating: number | null | undefined, niveaus: Niveau[] = STANDAARD_NIVEAUS): Niveau | null {
    if (rating === null || rating === undefined) {
        return null;
    }

    let gevonden = niveaus[0];

    for (const niveau of niveaus) {
        if (rating >= niveau.from) {
            gevonden = niveau;
        }
    }

    return gevonden;
}

/** De css-kleur van een niveau, uit de tokens (hsl-kanalen). */
export const kleurVan = (key: string | undefined | null, alpha = 1) => (key ? `hsl(var(--grade-${key}) / ${alpha})` : 'hsl(var(--muted-foreground))');

/** Alles wat een scherm nodig heeft: werkt deze school in kleuren, en welke kleur hoort bij een waarde. */
export function useGrading(override?: () => string | undefined) {
    const page = usePage();

    const gedeeld = computed(
        () => (page.props as { grading?: { mode: string; levels: Niveau[]; card?: string; progress?: VoortgangNiveau[] } | null }).grading ?? null,
    );

    const kleuren = computed(() => (override?.() ?? gedeeld.value?.mode ?? 'kleuren') === 'kleuren');
    const niveaus = computed<Niveau[]>(() => gedeeld.value?.levels ?? STANDAARD_NIVEAUS);

    /** De inzetkaart: geen cijfers, punten voor inzet. */
    const inzet = computed(() => gedeeld.value?.card === 'inzet');
    const voortgang = computed<VoortgangNiveau[]>(() => gedeeld.value?.progress ?? STANDAARD_VOORTGANG);

    return {
        kleuren,
        inzet,
        voortgang,
        niveaus,
        niveauVoor: (rating: number | null | undefined) => niveauVoor(rating, niveaus.value),
    };
}
