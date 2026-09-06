<?php

namespace App\Models;

use App\Enums\BillingInterval;
use App\Enums\ProductType;
use App\Models\Concerns\BelongsToSchool;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'amount_cents',
        'credits',
        'validity_months',
        'vat_rate',
        'interval',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'amount_cents' => 'integer',
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

    /** Producten die als abonnement lopen; de rest is een losse aankoop. */
    public function scopeSubscriptions(Builder $query): Builder
    {
        return $query->where('type', ProductType::Abonnement->value);
    }

    public function scopePurchasable(Builder $query): Builder
    {
        return $query->where('type', '!=', ProductType::Abonnement->value);
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
