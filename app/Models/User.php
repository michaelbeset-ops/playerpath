<?php

namespace App\Models;

use App\Enums\Role as RoleEnum;
use App\Support\Tenancy\Tenancy;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Let op: User gebruikt bewust NIET de BelongsToSchool-trait.
 *
 * Inloggen moet een gebruiker op e-mailadres kunnen vinden vóórdat er een
 * school bekend is; een global scope zou dat blokkeren. In plaats daarvan:
 * - de middleware SetCurrentSchool leidt de actieve school af uit de ingelogde
 *   gebruiker, dus de tenant komt nooit uit de URL;
 * - query gebruikers altijd via $school->users() of ->ofCurrentSchool();
 * - UserPolicy weigert alles buiten de eigen school.
 *
 * Zie CLAUDE.md 3.1.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'school_id',
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'notification_preferences' => 'array',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /** De rapporten die deze gebruiker als trainer heeft geschreven. */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'trainer_id');
    }

    /** De trainingen waar deze trainer bij staat. */
    public function trainings(): BelongsToMany
    {
        return $this->belongsToMany(Training::class)
            ->withPivotValue('school_id', $this->school_id ?? app(Tenancy::class)->id() ?? 0)
            ->withTimestamps();
    }

    /** Het spelersprofiel van deze gebruiker, als hij zelf speler is. */
    public function player(): HasOne
    {
        return $this->hasOne(Player::class);
    }

    /** De kinderen van deze gebruiker, als hij ouder is. */
    public function children(): BelongsToMany
    {
        return $this->belongsToMany(Player::class, 'guardian_player')
            ->withPivotValue('school_id', $this->school_id ?? app(Tenancy::class)->id() ?? 0)
            ->withPivot('relationship')
            ->withTimestamps();
    }

    /** Beperk een gebruikersquery tot de actieve school. */
    public function scopeOfCurrentSchool(Builder $query): Builder
    {
        $schoolId = app(Tenancy::class)->id();

        return $schoolId === null
            ? $query->whereRaw('1 = 0')
            : $query->where('school_id', $schoolId);
    }

    /**
     * Beheert dit account het platform in plaats van één school?
     *
     * Zo'n account heeft bewust geen school_id: het hoort nergens bij en kan
     * daardoor ook nooit per ongeluk als gewone gebruiker data van één school
     * meekrijgen.
     */
    public function isPlatformbeheerder(): bool
    {
        return $this->hasRole(RoleEnum::Platformbeheerder->value);
    }

    public function isEigenaar(): bool
    {
        return $this->hasRole(RoleEnum::Eigenaar->value);
    }

    public function isTrainer(): bool
    {
        return $this->hasRole(RoleEnum::Trainer->value);
    }

    public function isOuder(): bool
    {
        return $this->hasRole(RoleEnum::Ouder->value);
    }

    public function isSpeler(): bool
    {
        return $this->hasRole(RoleEnum::Speler->value);
    }

    /**
     * De spelers die deze gebruiker als "van hemzelf" mag beschouwen.
     *
     * Voor een ouder zijn dat zijn kinderen, voor een speler zijn eigen
     * profiel. Eigenaar en trainer hebben dit niet nodig: die zien de hele
     * school. Gebruikt om trainingen en aanwezigheid te filteren.
     *
     * @return list<int>
     */
    public function visiblePlayerIds(): array
    {
        if ($this->isOuder()) {
            return $this->children()->pluck('players.id')->all();
        }

        if ($this->isSpeler()) {
            return $this->player()->pluck('id')->all();
        }

        return [];
    }

    /** Hoort deze gebruiker bij dezelfde school als het gegeven model? */
    public function belongsToSameSchool(mixed $model): bool
    {
        return $this->school_id !== null
            && $model->school_id !== null
            && $this->school_id === $model->school_id;
    }

    /**
     * De soorten mail die een gebruiker kan uitzetten.
     *
     * In-app meldingen staan er bewust niet bij: die zijn niet uit te zetten.
     * Zou dat wel kunnen, dan mist iemand een afgelasting en heeft de school
     * geen enkele manier meer om hem te bereiken.
     *
     * @return array<string, string>
     */
    public static function notificationKinds(): array
    {
        return [
            'rapport' => 'Een nieuw rapport voor mijn kind',
            'doel' => 'Een doel dat gehaald is',
            'mededeling' => 'Mededelingen van de school',
            'samenvatting' => 'De maandelijkse samenvatting van mijn kind',
            'betaling' => 'Betalingen en herinneringen',
        ];
    }

    /**
     * Wil deze gebruiker dit soort bericht ook per mail?
     *
     * Onbekend of leeg betekent ja: een bestaande gebruiker mag niet stilletjes
     * zijn meldingen kwijtraken doordat er een voorkeur bijkomt.
     */
    public function wantsEmail(string $kind): bool
    {
        return (bool) ($this->notification_preferences[$kind] ?? true);
    }
}
