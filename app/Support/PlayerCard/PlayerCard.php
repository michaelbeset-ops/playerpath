<?php

namespace App\Support\PlayerCard;

/**
 * De doorgerekende kaart van één speler.
 *
 * De cijfers staan hier **precies** in, met decimalen. Afronden gebeurt pas bij
 * het tonen, op één plek: CalculatePlayerCard::afronden(). Zou het hier al
 * gebeuren, dan telt een halve punt groei verderop als een hele.
 *
 * @param  array<string, float>  $categoryRatings
 */
readonly class PlayerCard
{
    public function __construct(
        public ?float $overall,
        public array $categoryRatings,
        public int $reportCount,
    ) {}

    public function hasRating(): bool
    {
        return $this->overall !== null;
    }
}
