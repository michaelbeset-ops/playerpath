<?php

namespace App\Support\Tenancy;

use App\Models\School;
use Closure;
use RuntimeException;

/**
 * De actieve school van deze request (of dit commando).
 *
 * Dit is de enige plek waar "welke school zijn we nu?" wordt bijgehouden.
 * De global scope leest hem uit; de middleware zet hem. Zet hem nooit ergens
 * anders op basis van invoer van de gebruiker — altijd afgeleid van de
 * ingelogde gebruiker.
 */
class Tenancy
{
    protected ?School $school = null;

    /**
     * Terugval om de school alsnog te bepalen als hij nog niet gezet is.
     *
     * Nodig omdat route model binding eerder in de request draait dan de
     * SetCurrentSchool-middleware: zonder dit zou een URL met een {player}
     * altijd een 404 geven. De bron blijft dezelfde — het ingelogde account.
     */
    protected ?Closure $resolver = null;

    /** Staat de scope tijdelijk uit? Alleen voor bewuste beheer-acties. */
    protected bool $disabled = false;

    /**
     * Draaien we in de beheeromgeving van het platform?
     *
     * Dit is de enige manier waarop iemand over scholen heen mag kijken. Hij
     * wordt uitsluitend gezet door de middleware van /beheer, en alleen voor
     * een account met de rol platformbeheerder. Overal elders blijft de scope
     * fail-closed: geen school betekent geen data.
     *
     * Bewust een aparte stand naast $disabled. Die laatste is een tijdelijke
     * uitzondering rond één blok code (seeders, een webhook die zijn school nog
     * moet vinden); dit is een eigenschap van de hele request. Ze uit elkaar
     * houden maakt in de scope zichtbaar wélke van de twee geldt.
     */
    protected bool $platform = false;

    public function set(?School $school): void
    {
        $this->school = $school;
    }

    public function forget(): void
    {
        $this->school = null;
    }

    /**
     * De beheeromgeving betreden: vanaf hier kijkt de scope over alle scholen.
     *
     * Roep dit nooit aan vanuit een controller. De middleware van /beheer is
     * de enige plek die dit mag doen, want daar staat ook de rolcontrole.
     */
    public function enterPlatform(): void
    {
        $this->platform = true;
        $this->school = null;
        $this->resolver = null;
    }

    /**
     * De beheeromgeving weer verlaten.
     *
     * Alleen de middleware roept dit aan, na afloop van het verzoek. In een
     * langlevend proces blijft de stand anders hangen en draait het volgende
     * stuk werk met de scope open.
     */
    public function leavePlatform(): void
    {
        $this->platform = false;
        $this->school = null;
    }

    public function isPlatform(): bool
    {
        return $this->platform;
    }

    public function resolveUsing(Closure $resolver): void
    {
        $this->resolver = $resolver;
    }

    public function school(): ?School
    {
        if ($this->school === null && $this->resolver !== null) {
            $this->school = ($this->resolver)();
        }

        return $this->school;
    }

    public function id(): ?int
    {
        return $this->school()?->id;
    }

    public function hasSchool(): bool
    {
        return $this->school() !== null;
    }

    public function schoolOrFail(): School
    {
        return $this->school() ?? throw new RuntimeException(
            'Er is geen actieve school gezet. Gebruik Tenancy::set() of draai binnen een ingelogde request.'
        );
    }

    public function isDisabled(): bool
    {
        return $this->disabled;
    }

    /**
     * Draai een stuk code zonder school-scope. Uitsluitend voor beheer-acties
     * (seeders, migraties, platformbeheer) — nooit in een controller.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function withoutScope(callable $callback): mixed
    {
        $was = $this->disabled;
        $this->disabled = true;

        try {
            return $callback();
        } finally {
            $this->disabled = $was;
        }
    }

    /**
     * Draai een stuk code alsof je in een andere school zit.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function forSchool(School $school, callable $callback): mixed
    {
        $was = $this->school;
        $this->school = $school;

        try {
            return $callback();
        } finally {
            $this->school = $was;
        }
    }
}
