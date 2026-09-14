<?php

namespace App\Models;

use App\Enums\ProductAudience;
use App\Enums\TrainingEnrollmentStatus;
use App\Models\Concerns\BelongsToSchool;
use App\Support\Money\Money;
use App\Support\Rating\AgeCategory;
use Database\Factories\TrainingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Training extends Model
{
    /** @use HasFactory<TrainingFactory> */
    use BelongsToSchool, HasFactory;

    protected $fillable = [
        'group_id',
        'slot_id',
        'starts_at',
        'ends_at',
        'location',
        'location_id',
        'note',
        // Los inschrijven: wie mag meedoen, hoeveel, wat kost het, hoe betaal
        // je, en keurt de school eerst goed. Zie EnrollInTraining.
        'open_enrollment',
        'age_categories',
        'audience',
        'capacity',
        'price_cents',
        'payment_methods',
        'requires_approval',
    ];

    /**
     * De locatie, als er een gekozen is.
     *
     * Heet bewust `venue()` en niet `location()`: `location` is de tekstkolom
     * met de naam zoals die op dat moment was. Zou de relatie zo heten, dan
     * levert `$training->location` de ene keer een string en de andere keer een
     * model op, afhankelijk van wat er toevallig geladen is.
     *
     * Dat de naam ernaast blijft staan is dezelfde regel als bij een aankoop,
     * die naam en bedrag overneemt: een locatie hernoemen mag de agenda van
     * vorig seizoen niet herschrijven.
     */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    protected function casts(): array
    {
        return [
            'is_demo' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'open_enrollment' => 'boolean',
            'age_categories' => 'array',
            'audience' => ProductAudience::class,
            'capacity' => 'integer',
            'price_cents' => 'integer',
            'payment_methods' => 'array',
            'requires_approval' => 'boolean',
        ];
    }

    /** De losse aanmeldingen op deze training; zie TrainingEnrollment. */
    public function enrollments(): HasMany
    {
        return $this->hasMany(TrainingEnrollment::class);
    }

    // ------------------------------------------------------------------
    // Los inschrijven: de regels staan op de training zelf
    // ------------------------------------------------------------------

    /** Staat hij open voor losse aanmeldingen, en is hij nog niet geweest? */
    public function isOpenForEnrollment(): bool
    {
        return $this->open_enrollment && ! $this->isCancelled() && ! $this->hasPassed();
    }

    /**
     * De kinderen uit deze lijst die zich nog kunnen inschrijven.
     *
     * Dit is de ene plek waar "staat de knop er?" wordt beslist, voor de
     * detailpagina, de agenda en het overzicht tegelijk. Wie al is aangemeld
     * (aangevraagd, bevestigd of op de wachtlijst) of al in de groep zit, hoort
     * geen knop Inschrijven meer te zien - de detailpagina toonde hem wel, en
     * dan stuurt een ouder een tweede aanmelding in die de server weigert.
     *
     * @param  Collection<int, Player>  $kinderen
     * @return Collection<int, Player>
     */
    public function enrollableChildren(Collection $kinderen): Collection
    {
        if (! $this->isOpenForEnrollment() || $kinderen->isEmpty()) {
            return new Collection;
        }

        $aangemeld = $this->enrollments()
            ->whereIn('player_id', $kinderen->pluck('id'))
            ->active()
            ->pluck('player_id')
            ->all();

        $inGroep = $this->group_id === null
            ? []
            : $this->group->players()->whereIn('players.id', $kinderen->pluck('id'))->pluck('players.id')->all();

        return $kinderen->filter(fn (Player $kind) => $this->acceptsPlayer($kind)
            && ! in_array($kind->id, $aangemeld, true)
            && ! in_array($kind->id, $inGroep, true))->values();
    }

    /**
     * Mag dit kind meedoen? Leeftijdscategorie én positie, allebei.
     *
     * Dit is de echte grens; het scherm verbergt alleen wat toch niet kan.
     * Een leeg lijstje categorieën betekent iedereen.
     */
    public function acceptsPlayer(Player $player): bool
    {
        return $this->fitsAge($player) && $this->fitsPosition($player);
    }

    public function fitsAge(Player $player): bool
    {
        $categorieen = $this->age_categories ?? [];

        if ($categorieen === []) {
            return true;
        }

        $categorie = $player->age_category
            ?? ($player->date_of_birth ? AgeCategory::forBirthDate($player->date_of_birth) : null);

        return $categorie !== null && in_array($categorie, $categorieen, true);
    }

    public function fitsPosition(Player $player): bool
    {
        return ($this->audience ?? ProductAudience::All)->fits($player->position);
    }

    /** Waarom een kind niet mag: één zin, voor op het scherm. */
    public function rejectionReason(Player $player): ?string
    {
        if (! $this->fitsAge($player)) {
            return 'valt buiten de leeftijd ('.$this->ageLabel().')';
        }

        if (! $this->fitsPosition($player)) {
            return strtolower($this->audience->label());
        }

        return null;
    }

    /** "Onder 10, Onder 12" of "alle leeftijden". */
    public function ageLabel(): string
    {
        $categorieen = $this->age_categories ?? [];

        return $categorieen === []
            ? 'alle leeftijden'
            : implode(', ', array_map(fn (string $c) => AgeCategory::describe($c), $categorieen));
    }

    /** Welke betaalwijzen de school bij deze training toestaat. */
    public function allowsPayment(string $method): bool
    {
        return in_array($method, $this->payment_methods ?? ['online', 'cash'], true);
    }

    public function formattedPrice(): string
    {
        return Money::format($this->price_cents);
    }

    /**
     * Bezette plekken: de groep plus de bevestigde losse aanmeldingen.
     *
     * Geteld, niet opgeslagen - een opgeslagen "vol" blijft staan als iemand
     * zich afmeldt. Zonder capaciteit is er geen grens.
     */
    public function spotsTaken(): int
    {
        $groep = $this->group_id === null ? 0 : $this->group->players()->active()->count();

        return $groep + $this->enrollments()->where('status', TrainingEnrollmentStatus::Confirmed->value)->count();
    }

    public function isFull(): bool
    {
        return $this->capacity !== null && $this->spotsTaken() >= $this->capacity;
    }

    public function spotsLeft(): ?int
    {
        return $this->capacity === null ? null : max(0, $this->capacity - $this->spotsTaken());
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * De trainer(s) die bij deze training staan.
     *
     * Informatief: het bepaalt niet wie er bij mag. Elke trainer ziet het hele
     * rooster en kan overal afvinken, zodat invallen en ruilen niet vastloopt.
     */
    public function trainers(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivotValue('school_id', $this->pivotSchoolId())
            ->withTimestamps();
    }

    /** De inzetpunten bij deze training (inzetkaart). */
    public function effortRatings(): HasMany
    {
        return $this->hasMany(EffortRating::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /** Het geboekte moment, bij een privétraining. */
    public function slot(): BelongsTo
    {
        return $this->belongsTo(Slot::class);
    }

    /** Een privétraining hoort bij één kind en niet bij een groep. */
    public function isPrivate(): bool
    {
        return $this->group_id === null;
    }

    /**
     * Wie worden hier verwacht: de actieve spelers van de groep.
     *
     * Bewust niet vastgelegd bij het inplannen. Komt er morgen een speler bij
     * de groep, dan staat hij vanzelf op de lijst van de training van overmorgen.
     *
     * Bij een privétraining is er geen groep: dan is het het kind dat geboekt
     * heeft, en dat is er precies één.
     */
    public function expectedPlayers(): Collection
    {
        // Wie los is ingeschreven hoort er net zo goed bij als de groep.
        $los = Player::query()
            ->whereIn('id', $this->enrollments()->where('status', TrainingEnrollmentStatus::Confirmed->value)->pluck('player_id'))
            ->active()
            ->get();

        if ($this->group === null) {
            $speler = $this->slot?->player;

            $basis = $speler === null ? new Collection : new Collection([$speler]);
        } else {
            $basis = $this->group->players()->active()->get();
        }

        return $basis->merge($los)->unique('id')->sortBy('first_name')->values();
    }

    /** Is dit kind hier als losse aanmelding, en niet via de groep? */
    public function isLooseParticipant(int $playerId): bool
    {
        return $this->enrollments->contains(fn ($e) => $e->player_id === $playerId && $e->status === TrainingEnrollmentStatus::Confirmed);
    }

    /** Waar deze training over gaat, in het rooster. */
    public function label(): string
    {
        return $this->group?->name
            ?? ($this->slot?->product?->name ?? 'Privétraining');
    }

    /**
     * De trainingen die van deze trainer zijn.
     *
     * Een training zonder gekoppelde trainers telt als "van iedereen": koppelen
     * is informatief en veel scholen doen het niet. Zou dit strikt filteren,
     * dan is "Mijn trainingen" bij die scholen altijd leeg. Deze regel staat
     * hier op één plek, zodat de kalender en Mijn trainingen niet uit elkaar
     * kunnen lopen.
     */
    public function scopeForTrainer(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereDoesntHave('trainers')
            ->orWhereHas('trainers', fn (Builder $t) => $t->whereKey($user->id)));
    }

    /** Is deze training van die trainer? Zelfde regel als scopeForTrainer(). */
    public function belongsToTrainer(User $user): bool
    {
        return $this->trainers->isEmpty() || $this->trainers->contains('id', $user->id);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('starts_at', '>=', now()->startOfDay())->orderBy('starts_at');
    }

    public function scopePast(Builder $query): Builder
    {
        return $query->where('starts_at', '<', now()->startOfDay())->orderByDesc('starts_at');
    }

    public function hasPassed(): bool
    {
        return $this->starts_at->isPast();
    }

    /** Is dit een voorbeeldrij, neergezet bij het opstarten van de school? */
    public function scopeDemo(Builder $query): Builder
    {
        return $query->where('is_demo', true);
    }

    /**
     * Alleen wat de school zelf heeft ingevoerd.
     *
     * De startchecklist telt hiermee: anders is je school "af" zonder dat je
     * ooit een echte speler hebt toegevoegd.
     */
    public function scopeReal(Builder $query): Builder
    {
        return $query->where('is_demo', false);
    }
}
