<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

/**
 * Een oud adres van de inschrijfpagina van een school.
 *
 * Wijzigt een school haar slug, dan blijft de oude hier staan en stuurt
 * /inschrijven/{oude-slug} permanent door naar de nieuwe. Neemt een school
 * (dezelfde of een andere) die slug later weer in gebruik, dan verdwijnt de
 * doorverwijzing: de echte school wint altijd.
 *
 * Bijgehouden door School::booted(), zodat het niet uitmaakt wáár de slug
 * gewijzigd wordt (wizard, platformbeheer, commando).
 *
 * Opzoeken gebeurt zonder ingelogde gebruiker, dus buiten de scope om, net
 * als PublicEnrollmentController de school uit de URL haalt. `school_id`
 * wordt altijd expliciet gezet en staat niet in $fillable.
 */
class SchoolSlugRedirect extends Model
{
    use BelongsToSchool;

    protected $fillable = ['slug'];

    /** De school waar een oude slug nu naartoe wijst, of null. */
    public static function schoolFor(string $slug): ?School
    {
        $redirect = static::withoutSchoolScope()->where('slug', $slug)->first();

        if ($redirect === null) {
            return null;
        }

        return School::query()->whereKey($redirect->school_id)->where('is_active', true)->first();
    }

    /** Leg vast dat $slug een oud adres van $school is. */
    public static function remember(School $school, string $slug): void
    {
        // Hoorde dit oude adres eerder bij een andere school, dan vervalt dat:
        // school_id op een bestaand record ligt vast, dus weg en opnieuw.
        static::withoutSchoolScope()
            ->where('slug', $slug)
            ->where('school_id', '!=', $school->id)
            ->delete();

        if (! static::withoutSchoolScope()->where('slug', $slug)->exists()) {
            (new static)->forceFill(['slug' => $slug, 'school_id' => $school->id])->save();
        }
    }

    /** Een slug is weer van een school: dan is het geen oud adres meer. */
    public static function forget(string $slug): void
    {
        static::withoutSchoolScope()->where('slug', $slug)->delete();
    }
}
