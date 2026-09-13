<?php

namespace App\Support\Rating;

use App\Models\School;
use Carbon\CarbonImmutable;

/**
 * Het lopende seizoen (of blok) van een school: naam, begin, eind, weken.
 *
 * De XP en het level van een kaart zijn seizoensgebonden: aan het eind van
 * het seizoen wordt de kaart bewaard als seizoenskaart en beginnen de punten
 * opnieuw. De rating niet: die zegt hoe goed een speler is, en dat verdwijnt
 * niet op een datum. Zo blijft er iets om naartoe te werken, elk blok weer.
 *
 * Opgeslagen in `schools.rating_settings['season']`, dezelfde afspraak als
 * de rest van het ratingsysteem: standaard in code (geen seizoen: dan telt
 * alle XP, zoals vroeger), in de database alleen wat de school koos.
 *
 * `xp_from` is de datum vanaf wanneer XP meetelt. Bij het instellen van een
 * seizoen is dat de startdatum; bij het afsluiten wordt het de dag erna, ook
 * als er nog geen nieuw seizoen is gekozen. Anders zou de kaart na het
 * afsluiten meteen weer vol staan.
 */
final class SchoolSeason
{
    public function __construct(
        public readonly ?string $name,
        public readonly ?CarbonImmutable $startsOn,
        public readonly ?CarbonImmutable $endsOn,
        public readonly ?int $weeks,
        public readonly ?CarbonImmutable $closedAt,
        public readonly ?CarbonImmutable $xpFrom,
    ) {}

    public static function for(?School $school): self
    {
        $s = $school?->rating_settings['season'] ?? [];

        $datum = fn (?string $w) => $w ? CarbonImmutable::parse($w) : null;

        return new self(
            name: $s['name'] ?? null,
            startsOn: $datum($s['starts_on'] ?? null),
            endsOn: $datum($s['ends_on'] ?? null),
            weeks: isset($s['weeks']) ? (int) $s['weeks'] : null,
            closedAt: $datum($s['closed_at'] ?? null),
            xpFrom: $datum($s['xp_from'] ?? null),
        );
    }

    /** @param  array<string, mixed>  $waarden */
    public static function save(School $school, array $waarden): void
    {
        $instellingen = $school->rating_settings ?? [];
        $instellingen['season'] = array_merge($instellingen['season'] ?? [], $waarden);
        $school->forceFill(['rating_settings' => $instellingen])->save();
    }

    /** Er is een seizoen ingesteld dat nog niet is afgesloten. */
    public function isSet(): bool
    {
        return $this->name !== null && $this->startsOn !== null && $this->endsOn !== null && $this->closedAt === null;
    }

    public function isActive(): bool
    {
        return $this->isSet() && ! now()->lt($this->startsOn) && ! now()->gt($this->endsOn->endOfDay());
    }

    /** Voorbij de einddatum en nog niet afgesloten: dan mag hij dicht. */
    public function hasEnded(): bool
    {
        return $this->isSet() && now()->gt($this->endsOn->endOfDay());
    }

    /** "Najaar 2026", of het gewone seizoensjaar als er niets is ingesteld. */
    public function label(int $seasonStartMonth = 8): string
    {
        return $this->isSet() ? (string) $this->name : AgeCategory::seasonLabel(now(), $seasonStartMonth);
    }

    /** Hoeveel weken het seizoen telt, uit de datums als het niet is ingevuld. */
    public function totalWeeks(): ?int
    {
        if (! $this->isSet()) {
            return null;
        }

        return $this->weeks ?? max(1, (int) ceil(($this->startsOn->diffInDays($this->endsOn) + 1) / 7));
    }

    /** In welke week we zitten, 1-gebaseerd; null buiten het seizoen. */
    public function currentWeek(): ?int
    {
        if (! $this->isActive()) {
            return null;
        }

        return min($this->totalWeeks() ?? PHP_INT_MAX, (int) floor($this->startsOn->diffInDays(now()) / 7) + 1);
    }

    /** Hoeveel dagen nog, vanaf vandaag; 0 op de laatste dag. */
    public function daysLeft(): ?int
    {
        return $this->isActive() ? (int) now()->startOfDay()->diffInDays($this->endsOn->startOfDay()) : null;
    }
}
