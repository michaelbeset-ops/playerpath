<?php

namespace App\Policies;

use App\Models\Player;
use App\Models\User;

/**
 * Wie mag wat met een speler.
 *
 * Elke check begint met "zelfde school". De global scope vangt dit al af,
 * maar deze laag staat er bewust naast: twee sloten op dezelfde deur.
 */
class PlayerPolicy
{
    /**
     * Het volledige ledenbestand van de school.
     *
     * Bewust alleen eigenaar en trainer: een ouder hoort niet te zien welke
     * andere kinderen op de school zitten. Ouder en speler komen bij hun eigen
     * speler via view(), niet via dit overzicht.
     */
    public function viewAny(User $user): bool
    {
        return $user->isEigenaar() || $user->isTrainer();
    }

    public function view(User $user, Player $player): bool
    {
        if (! $user->belongsToSameSchool($player)) {
            return false;
        }

        if ($user->isEigenaar() || $user->isTrainer()) {
            return true;
        }

        if ($user->isOuder()) {
            return $user->children()->whereKey($player->getKey())->exists();
        }

        if ($user->isSpeler()) {
            return $player->user_id === $user->id;
        }

        return false;
    }

    /** Spelersbeheer is werk van de eigenaar (fase 3). */
    public function create(User $user): bool
    {
        return $user->isEigenaar();
    }

    public function update(User $user, Player $player): bool
    {
        return $user->belongsToSameSchool($player) && $user->isEigenaar();
    }

    public function delete(User $user, Player $player): bool
    {
        return $user->belongsToSameSchool($player) && $user->isEigenaar();
    }

    /**
     * De kaart publiek deelbaar maken.
     *
     * Bewust NIET de trainer: die beslist niet of het kind van iemand anders
     * op internet komt. Wel de eigenaar van de school en de ouders zelf.
     */
    public function share(User $user, Player $player): bool
    {
        if (! $user->belongsToSameSchool($player)) {
            return false;
        }

        if ($user->isEigenaar()) {
            return true;
        }

        return $user->isOuder() && $user->children()->whereKey($player->getKey())->exists();
    }

    /** Rapporten schrijven doet de trainer (fase 2). */
    public function createReport(User $user, Player $player): bool
    {
        return $user->belongsToSameSchool($player)
            && ($user->isTrainer() || $user->isEigenaar());
    }
}
