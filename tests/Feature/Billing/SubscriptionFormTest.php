<?php

namespace Tests\Feature\Billing;

use App\Enums\BillingType;
use App\Enums\Role;
use App\Models\Product;
use App\Models\School;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Het abonnementenscherm biedt alleen aanbod aan dat écht als abonnement loopt.
 *
 * Op alleen "actief" filteren liet ook een kamp in de keuzelijst komen. Dat
 * brak het scherm — een kamp heeft geen interval — maar erger was wat eronder
 * zat: je kon een kamp als abonnement kiezen, en dan brengt een blok van één
 * week elke maand een rekening voort.
 */
class SubscriptionFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_alleen_aanbod_dat_per_maand_loopt_staat_in_de_keuzelijst(): void
    {
        $this->seed(RoleSeeder::class);

        $school = School::factory()->create();
        app(Tenancy::class)->set($school);

        $eigenaar = User::factory()->for($school)->create();
        $eigenaar->assignRole(Role::Eigenaar->value);

        $abonnement = Product::factory()->for($school)->create([
            'name' => 'Keeperstraining per maand',
            'billing_type' => BillingType::Maandelijks,
            'interval' => 'monthly',
            'is_active' => true,
        ]);

        // Een kamp: eenmalig, en dus zonder interval.
        $kamp = Product::factory()->for($school)->create([
            'name' => 'Zomerkamp',
            'billing_type' => BillingType::Eenmalig,
            'interval' => null,
            'is_active' => true,
        ]);

        $this->actingAs($eigenaar)
            ->get('/subscriptions')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('products', function ($producten) use ($abonnement, $kamp) {
                $ids = collect($producten)->pluck('id');

                $this->assertTrue($ids->contains($abonnement->id));
                $this->assertFalse($ids->contains($kamp->id));

                return true;
            }));
    }
}
