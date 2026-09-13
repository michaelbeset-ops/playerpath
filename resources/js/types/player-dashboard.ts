/**
 * De vorm van het spelerdashboard.
 *
 * Eén bestand, gedeeld door de pagina die de props ontvangt en het blok dat ze
 * tekent - net als bij het ouder-dashboard.
 */
import type { Kaart } from '@/components/PlayerCardVisual.vue';
import type { FamilyTraining } from '@/types/family';

export interface SpelerTrend {
    key: 'sterk' | 'groei' | 'stabiel' | 'aandacht';
    label: string;
}

export interface SpelerCategorie {
    category: string;
    label: string;
    last: number | null;
    delta: number | null;
    trend: SpelerTrend | null;
}

export interface SpelerVolgendeStap {
    type: 'goal' | 'suggestion';
    category: string;
    label: string;
    hint?: string;
    from: number | null;
    to: number;
    on_track: boolean | null;
    days_left: number | null;
}

export interface SpelerBadge {
    key: string;
    label: string;
    description: string;
    earned: boolean;
}

export interface SpelerDashboardData {
    player: { id: number; first_name: string };
    card: Kaart;
    quarter: { reports: number; trainings: number; growth: number | null; best: string | null };
    categories: SpelerCategorie[];
    hasEnoughData: boolean;
    nextStep: SpelerVolgendeStap | null;
    nextBadge: SpelerBadge | null;
    nextTraining: FamilyTraining | null;
    /** De eerstvolgende trainingen, hooguit drie. */
    upcoming: FamilyTraining[];
    /** De mijlpalen die voor deze speler gelden, behaald of nog niet. */
    badges: SpelerBadge[];
    /** De deel-link, als een ouder of de school die heeft aangezet. */
    share: { url: string | null } | null;
}
