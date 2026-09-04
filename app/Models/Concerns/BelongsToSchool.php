<?php

namespace App\Models\Concerns;

use App\Models\School;
use App\Models\Scopes\SchoolScope;
use App\Support\Tenancy\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Zet een model vast aan één school.
 *
 * Elk model met klantdata gebruikt deze trait. Hij doet drie dingen:
 * 1. lezen wordt automatisch gefilterd op de actieve school;
 * 2. school_id wordt bij het opslaan automatisch ingevuld;
 * 3. school_id kan daarna niet meer veranderen.
 *
 * Uitzondering: User gebruikt deze trait bewust NIET voor de global scope —
 * inloggen moet een gebruiker kunnen vinden vóórdat er een school bekend is.
 * Zie CLAUDE.md 3.1.
 */
trait BelongsToSchool
{
    public static function bootBelongsToSchool(): void
    {
        static::addGlobalScope(new SchoolScope);

        static::creating(function ($model) {
            if ($model->school_id !== null) {
                return;
            }

            $tenancy = app(Tenancy::class);

            $model->school_id = $tenancy->id() ?? throw new RuntimeException(
                sprintf(
                    'Kan %s niet opslaan: er is geen actieve school. Zet er eerst één met Tenancy::set().',
                    class_basename($model)
                )
            );
        });

        static::updating(function ($model) {
            if ($model->isDirty('school_id')) {
                throw new RuntimeException(
                    sprintf('De school van een bestaande %s wijzigen mag niet.', class_basename($model))
                );
            }
        });
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /** Query zonder school-filter. Alleen voor bewuste beheer-acties. */
    public static function withoutSchoolScope(): Builder
    {
        return static::query()->withoutGlobalScope(SchoolScope::class);
    }
}
