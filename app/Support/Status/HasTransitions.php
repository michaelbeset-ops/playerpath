<?php

namespace App\Support\Status;

/**
 * Een statusmachine op een enum: welke overgangen mogen, en welke niet.
 *
 * Geen losse vlaggetjes (`is_paid`, `is_cancelled`) die elkaar kunnen
 * tegenspreken, maar één status met een lijst van toegestane volgende
 * statussen. `canTransitionTo()` is de enige vraag die je stelt; de lijst
 * zelf staat per enum in `transitions()`.
 */
trait HasTransitions
{
    /**
     * Per status: naar welke statussen hij mag.
     *
     * @return array<string, list<string>>
     */
    abstract public static function transitions(): array;

    public function canTransitionTo(self $naar): bool
    {
        return in_array($naar->value, static::transitions()[$this->value] ?? [], strict: true);
    }

    /** Een status zonder uitgang: hier eindigt het. */
    public function isFinal(): bool
    {
        return (static::transitions()[$this->value] ?? []) === [];
    }

    /** @return list<self> */
    public function next(): array
    {
        return array_map(fn (string $waarde) => self::from($waarde), static::transitions()[$this->value] ?? []);
    }
}
