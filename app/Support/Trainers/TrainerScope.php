<?php

namespace App\Support\Trainers;

use App\Models\Group;
use App\Models\Player;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Wat van deze trainer is.
 *
 * Een trainer is personeel en ziet zijn eigen werk: de groepen waar hij voor
 * staat en de spelers daarin. Niet het hele ledenbestand - een trainer van de
 * keepers hoeft niet te weten welke veldspelers er op de school zitten, en al
 * helemaal niet wie hun ouders zijn.
 *
 * Er is bewust geen koppeling trainer↔groep. Een trainer wordt aan
 * **trainingen** gekoppeld (dat gebeurt bij het inplannen), en "zijn groepen"
 * zijn de groepen van die trainingen. Dat is het eerlijkste antwoord: het volgt
 * de planning, en een trainer die invalt bij een andere groep hoort er vanaf
 * dat moment bij zonder dat iemand een tweede koppeling moet onderhouden.
 *
 * **Eén uitzondering, en die is bewust.** Is een trainer nérgens aan gekoppeld,
 * dan is de hele school van hem. Koppelen is bij veel scholen niet gebruikelijk
 * (zie `Training::scopeForTrainer`), en een trainer die na het inloggen nul
 * spelers en nul trainingen ziet, denkt dat het product stuk is. Zodra de
 * school hem ergens aan koppelt, sluit de scope zich vanzelf.
 *
 * Dit staat náást de global scope, niet in plaats daarvan: de schoolgrens wordt
 * al door `SchoolScope` bewaakt. Dit gaat over wat er bínnen de school van hem
 * is. En het is één plek - de policies, de lijsten, het dashboard en de
 * verjaardagen lezen allemaal hieruit, zodat "mijn spelers" overal hetzelfde
 * betekent.
 */
class TrainerScope
{
    /** @var array<int, list<int>|null> per gebruiker, want dit wordt per request vaak gevraagd */
    protected array $groepen = [];

    /**
     * Geldt de begrenzing voor deze gebruiker?
     *
     * Alleen voor wie trainer is en géén eigenaar: de eigenaar ziet altijd de
     * hele school, ook als hij zelf training geeft.
     */
    public function applies(User $user): bool
    {
        return $user->isTrainer() && ! $user->isEigenaar();
    }

    /**
     * Staat deze trainer ergens bij? Zo niet, dan is de hele school van hem.
     */
    public function isCoupled(User $user): bool
    {
        return $this->groupIds($user) !== null;
    }

    /**
     * De groepen waar deze trainer aan gekoppelde trainingen heeft.
     *
     * `null` betekent: nergens gekoppeld, dus geen begrenzing.
     *
     * @return list<int>|null
     */
    public function groupIds(User $user): ?array
    {
        if (array_key_exists($user->id, $this->groepen)) {
            return $this->groepen[$user->id];
        }

        $gekoppeld = $user->trainings()->exists();

        $this->groepen[$user->id] = $gekoppeld
            ? Group::query()
                ->whereHas('trainings.trainers', fn ($q) => $q->whereKey($user->id))
                ->pluck('id')
                ->all()
            : null;

        return $this->groepen[$user->id];
    }

    /**
     * De spelers die van deze trainer zijn, of `null` als alles van hem is.
     *
     * @return list<int>|null
     */
    public function playerIds(User $user): ?array
    {
        $groepen = $this->groupIds($user);

        if ($groepen === null) {
            return null;
        }

        return Player::query()
            ->whereHas('groups', fn ($g) => $g->whereIn('groups.id', $groepen))
            ->pluck('id')
            ->all();
    }

    /** Begrens een spelersquery tot wat van deze gebruiker is. */
    public function players(Builder $query, User $user): Builder
    {
        if (! $this->applies($user)) {
            return $query;
        }

        $groepen = $this->groupIds($user);

        if ($groepen === null) {
            return $query;
        }

        return $query->whereHas('groups', fn ($g) => $g->whereIn('groups.id', $groepen));
    }

    /** Begrens een groepenquery tot wat van deze gebruiker is. */
    public function groups(Builder $query, User $user): Builder
    {
        if (! $this->applies($user)) {
            return $query;
        }

        $groepen = $this->groupIds($user);

        return $groepen === null ? $query : $query->whereIn('groups.id', $groepen);
    }

    /** Hoort deze speler bij deze trainer? */
    public function ownsPlayer(User $user, Player $player): bool
    {
        if (! $this->applies($user)) {
            return true;
        }

        $groepen = $this->groupIds($user);

        if ($groepen === null) {
            return true;
        }

        return $player->groups()->whereIn('groups.id', $groepen)->exists();
    }

    /** Hoort deze groep bij deze trainer? */
    public function ownsGroup(User $user, Group $group): bool
    {
        if (! $this->applies($user)) {
            return true;
        }

        $groepen = $this->groupIds($user);

        return $groepen === null || in_array($group->id, $groepen, true);
    }

    /** Voor tests en langlevende processen: de cache leegmaken. */
    public function forget(): void
    {
        $this->groepen = [];
    }
}
