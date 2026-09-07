<?php

namespace App\Models;

use App\Enums\BillingInterval;
use App\Enums\BillingType;
use App\Enums\OfferingStatus;
use App\Enums\ParticipationStatus;
use App\Enums\ProductType;
use App\Models\Concerns\BelongsToSchool;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Wat een school verkoopt: een abonnement, een rittenkaart, een losse
 * training, een kamp.
 *
 * Eén lijst voor alles. Twee prijslijsten naast elkaar zou betekenen dat je
 * bij elke vraag moet nadenken waar iets ook alweer staat.
 *
 * Het bedrag staat hier als **richtprijs**. Zodra iemand het product afneemt
 * wordt het bedrag overgenomen in het abonnement of de aankoop; verhoogt de
 * school later haar prijs, dan verandert een lopende afspraak niet mee. Zie
 * ook de afspraken over geld in CLAUDE.md.
 */
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use BelongsToSchool, HasFactory;

    protected $fillable = [
        'name',
        'description',
        'type',
        'billing_type',
        'amount_cents',
        'credits',
        'validity_months',
        'vat_rate',
        'interval',
        'starts_on',
        'ends_on',
        'capacity',
        'min_participants',
        'min_age',
        'max_age',
        'location',
        'location_id',
        'status',
        'stops_at_end',
        'is_active',
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
            'type' => ProductType::class,
            'billing_type' => BillingType::class,
            'status' => OfferingStatus::class,
            'amount_cents' => 'integer',
            'capacity' => 'integer',
            'min_participants' => 'integer',
            'min_age' => 'integer',
            'max_age' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'stops_at_end' => 'boolean',
            'credits' => 'integer',
            'validity_months' => 'integer',
            'vat_rate' => 'integer',
            'interval' => BillingInterval::class,
            'is_active' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /** De eenmalige aankopen van dit product. */
    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Aanbod dat per maand loopt; de rest is een eenmalige aankoop.
     *
     * De administratie volgt de **betaalwijze**, niet het soort: een blok van
     * zes weken dat per maand betaald wordt is een abonnement met een
     * einddatum, en doorlopende training die je in één keer voor een jaar
     * afrekent is dat juist niet.
     */
    public function scopeRecurring(Builder $query): Builder
    {
        return $query->where('billing_type', BillingType::Maandelijks->value);
    }

    public function scopePurchasable(Builder $query): Builder
    {
        return $query->where('billing_type', '!=', BillingType::Maandelijks->value);
    }

    /** Loopt dit als abonnement? Zie scopeRecurring(). */
    public function isRecurring(): bool
    {
        return $this->billing_type === BillingType::Maandelijks;
    }

    /** Staat dit aanbod open én is er nog plek? */
    public function acceptsSignups(): bool
    {
        return $this->is_active && $this->status->acceptsSignups() && ! $this->isFull();
    }

    /**
     * Vol? Zonder capaciteit is er geen grens, en dan is dit nooit waar.
     *
     * Bewust geteld en niet opgeslagen: een opgeslagen "vol" blijft staan zodra
     * iemand afzegt, en dan weigert een school een plek die er wel is.
     */
    public function isFull(): bool
    {
        if ($this->capacity === null) {
            return false;
        }

        return $this->spotsTaken() >= $this->capacity;
    }

    public function spotsTaken(): int
    {
        return $this->participations_count
            ?? $this->participations()->where('status', ParticipationStatus::Confirmed->value)->count();
    }

    /** Hoeveel plekken er nog vrij zijn, of null als er geen grens is. */
    public function spotsLeft(): ?int
    {
        return $this->capacity === null ? null : max(0, $this->capacity - $this->spotsTaken());
    }

    /** Past deze leeftijd bij dit aanbod? Zonder grenzen mag iedereen mee. */
    public function fitsAge(?int $age): bool
    {
        if ($age === null) {
            return true;
        }

        return ($this->min_age === null || $age >= $this->min_age)
            && ($this->max_age === null || $age <= $this->max_age);
    }

    public function trainers(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivotValue('school_id', $this->pivotSchoolId())
            ->withTimestamps();
    }

    public function participations(): HasMany
    {
        return $this->hasMany(Participation::class);
    }

    /** De beschikbare momenten, bij een privétraining. */
    public function slots(): HasMany
    {
        return $this->hasMany(Slot::class);
    }

    /**
     * De groep die bij dit aanbod hoort.
     *
     * Hier zit de knoop met de rest van de app: de trainingen van een blok
     * hangen onder deze groep, en daardoor blijven aanwezigheid, rapporten en
     * de agenda werken zoals ze altijd al deden.
     */
    public function group(): HasOne
    {
        return $this->hasOne(Group::class);
    }

    /**
     * Het bedrag exclusief btw, in centen.
     *
     * De prijs die een school invult is wat de ouder betaalt, dus inclusief.
     * Voor een omzetoverzicht wil je het bedrag eronder zien; dat rekenen we
     * hier uit en niet in een view, want dan staat het op één plek.
     */
    public function amountExclVatCents(): int
    {
        return (int) round($this->amount_cents / (1 + $this->vat_rate / 100));
    }

    public function vatCents(): int
    {
        return $this->amount_cents - $this->amountExclVatCents();
    }
}
