/**
 * De kleur die bij een signaal hoort.
 *
 * De server bepaalt *of* iets goed of slecht is (zie Support\Dashboard\Signal);
 * hier staat alleen hoe dat eruitziet. Twee plekken, elk met één taak: zo kan
 * een drempel niet in een component gaan afwijken van wat het dashboard zelf
 * berekent.
 *
 * Groen is nooit versiering: als bijna alles gekleurd is, betekent kleur niets
 * meer. Vandaar dat "neutraal" gewoon grijs is.
 */
export type Tone = 'good' | 'warn' | 'bad' | 'neutral';

/** Tekstkleur, bijvoorbeeld voor een trendregel. */
export const toneText: Record<Tone, string> = {
    good: 'text-success',
    warn: 'text-warning',
    bad: 'text-destructive',
    neutral: 'text-muted-foreground',
};

/** Vlakvulling, bijvoorbeeld voor een voortgangsbalk. */
export const toneFill: Record<Tone, string> = {
    good: 'bg-success',
    warn: 'bg-warning',
    bad: 'bg-destructive',
    neutral: 'bg-border',
};

/** Een zacht vlak met tekst erop, bijvoorbeeld een chip. */
export const toneChip: Record<Tone, string> = {
    good: 'bg-success/10 text-success',
    warn: 'bg-warning/10 text-warning',
    bad: 'bg-destructive/10 text-destructive',
    neutral: 'bg-secondary text-muted-foreground',
};
