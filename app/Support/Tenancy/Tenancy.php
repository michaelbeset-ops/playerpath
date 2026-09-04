<?php

namespace App\Support\Tenancy;

use App\Models\School;
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

    /** Staat de scope tijdelijk uit? Alleen voor bewuste beheer-acties. */
    protected bool $disabled = false;

    public function set(?School $school): void
    {
        $this->school = $school;
    }

    public function forget(): void
    {
        $this->school = null;
    }

    public function school(): ?School
    {
        return $this->school;
    }

    public function id(): ?int
    {
        return $this->school?->id;
    }

    public function hasSchool(): bool
    {
        return $this->school !== null;
    }

    public function schoolOrFail(): School
    {
        return $this->school ?? throw new RuntimeException(
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
