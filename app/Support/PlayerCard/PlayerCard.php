<?php

namespace App\Support\PlayerCard;

/**
 * De doorgerekende kaart van één speler.
 *
 * @param  array<string, int>  $categoryRatings
 */
readonly class PlayerCard
{
    public function __construct(
        public ?int $overall,
        public array $categoryRatings,
        public int $reportCount,
    ) {}

    public function hasRating(): bool
    {
        return $this->overall !== null;
    }
}
