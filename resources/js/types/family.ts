/**
 * De vorm van het ouder-dashboard.
 *
 * In een eigen bestand omdat twee componenten hem delen: de pagina die de props
 * ontvangt en het blok dat ze tekent. Een type dat je twee keer overtikt loopt
 * vroeg of laat uit elkaar.
 */
import type { Tone } from '@/lib/tone';

export interface FamilyKind {
    id: number;
    name: string;
    first_name: string;
    photo: string | null;
    position: string;
    overall: number | null;
    level: string;
    level_label: string;
    xp: number;
    xp_progress: number;
    next_level: string | null;
    /** Groei over de laatste maand; null als er te weinig rapporten zijn. */
    growth: number | null;
    report_count: number;
}

export interface FamilyTraining {
    id: number;
    label: string;
    /** Voor welk kind (of welke kinderen) deze training is. */
    for: string;
    date: string;
    is_today: boolean;
    time: string;
    location: string | null;
    trainers: string[];
    cancelled: boolean;
}

export interface FamilyAanbod {
    id: number;
    name: string;
    type: string;
    description: string | null;
    amount: string;
    is_free: boolean;
    billing: string;
    period: string | null;
    location: string | null;
    spots_left: number | null;
}

export interface FamilyBericht {
    id: string;
    title: string;
    url: string;
    when: string;
    unread: boolean;
}

export interface FamilyTaak {
    key: string;
    tone: Tone;
    icon: string;
    title: string;
    body: string;
    href: string;
    action: string;
}
